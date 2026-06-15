<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace filter_fastpix;

/**
 * FastPix text filter.
 *
 * Renders {fastpix:pb_<id>} shortcodes as DRM-aware video players inside any
 * rich-text surface. This is a thin UI/endpoint layer: it makes no FastPix HTTP
 * calls and runs no SQL — all asset/playback/watermark/token operations route
 * through \local_fastpix\service\*.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /**
     * Shortcode pattern. Capture 1: playback id without the `pb_` prefix.
     * Capture 2: optional space-prefixed attribute tail. Do not modify.
     */
    const SHORTCODE_REGEX = '/\{fastpix:pb_([a-zA-Z0-9_-]+)( [^}]*)?\}/';

    /**
     * Replace {fastpix:pb_<id>} shortcodes with rendered players.
     *
     * @param string $text The text to filter.
     * @param array $options Filter options.
     * @return string The filtered text.
     */
    #[\Override]
    public function filter($text, array $options = []) {
        if (!is_string($text) || $text === '') {
            return $text;
        }

        // Fast-path short-circuit before any regex (invariant 7). filter() runs
        // on every text block — the no-match path must be effectively free.
        if (strpos($text, '{fastpix:') === false) {
            return $text;
        }

        $matches = $this->collect_matches($text);
        if (!empty($matches)) {
            global $PAGE;

            // A real renderer_base (not the early bootstrap_renderer $OUTPUT may
            // be) so player_embed::export_for_template gets the type it declares.
            $output = $PAGE->get_renderer('core');
            $context = $this->resolve_context($options);
            $rendered = false;
            $denied = 0;

            // Iterate matches in reverse so byte offsets stay valid as we splice.
            foreach (array_reverse($matches) as $match) {
                [$full, $offset] = $match[0];
                // Capture 1 is the bare playback id; `pb_` is only the shortcode
                // marker. The asset table stores the bare playback id.
                $playbackid = $match[1][0];

                $replacement = $this->resolve_replacement($output, $context, $full, $playbackid, $rendered, $denied);
                $text = substr_replace($text, $replacement, $offset, strlen($full));
            }

            // One structured denial log per filter() invocation, not one per
            // shortcode. A forum post with many embeds, viewed by many
            // unauthorised users, would otherwise flood the server log. Every
            // denied match in a single filter() call shares the same context and
            // user, so a single line carrying the count is fully equivalent.
            if ($denied > 0) {
                $this->log_capability_denied($context, $denied);
            }

            // Attach the boot module once per filter() invocation, only if at
            // least one player actually rendered. The DRM token mint stays
            // client-deferred (invariant 8) — this attaches no FastPix call,
            // just the ESM lib URLs.
            if ($rendered) {
                $PAGE->requires->js_call_amd('filter_fastpix/player_boot', 'init', [[
                    // Player ESM is served by local_fastpix (ADR-017); consume the
                    // documented surface (CC1/CC7), not mod_fastpix's removed
                    // PLAYER_LIB_URL constant. Already absolute (wwwroot-prefixed).
                    'playerliburl' => \local_fastpix\service\playback_service::player_lib_url(),
                    // HLS_LIB_URL is a root-relative path to mod_fastpix's vendored
                    // lib; resolve to an absolute URL for the native import(), the
                    // same way mod_fastpix's view_state_player does.
                    'hlsliburl'    => (new \moodle_url(\mod_fastpix\service\playback_service::HLS_LIB_URL))->out(false),
                ]]);
            }
        }

        return $text;
    }

    /**
     * Resolve the replacement HTML for one matched shortcode.
     *
     * Per-match decision, in priority order: capability gate (escaped literal on
     * denial); then, for an id NOT in this Moodle's library, a tokenless public
     * player rendered straight from the id (cross-workspace public embed); for a
     * known asset, the DRM and private placeholders, and finally a public player
     * (degrading to the placeholder when playback cannot resolve).
     *
     * @param \renderer_base $output The renderer to template with.
     * @param \context $context The context the capability check runs against.
     * @param string $full The full matched shortcode text (for the literal fallback).
     * @param string $playbackid The captured bare playback id.
     * @param bool $rendered Set to true (by reference) when a player is rendered.
     * @param int $denied Incremented (by reference) when the capability gate denies.
     * @return string The replacement HTML for this shortcode.
     */
    protected function resolve_replacement(
        \renderer_base $output,
        \context $context,
        string $full,
        string $playbackid,
        bool &$rendered,
        int &$denied
    ): string {
        // T6 capability gate. has_capability (not require_capability): throwing
        // inside a text filter blanks the entire surrounding page. Denial emits
        // the escaped literal — never a player and never an empty string. The
        // denial is counted here and logged once per filter() call (see filter())
        // rather than per match, to keep heavily-embedded posts from flooding
        // the log.
        if (!has_capability('mod/fastpix:view', $context)) {
            $denied++;
            return s($full);
        }

        // The shortcode carries a playback id, so resolve via get_by_playback_id
        // (the playback_id column), not get_by_fastpix_id (the asset-id column).
        $asset = \local_fastpix\service\asset_service::get_by_playback_id($playbackid);
        if ($asset === null) {
            // Not in this Moodle's FastPix library. It may be a PUBLIC video from
            // another FastPix workspace/account — public playback needs no token
            // and no account auth, so render a tokenless public player straight
            // from the id and let the browser play the stream. The filter still
            // makes no FastPix HTTP call (the <fastpix-player> fetches the
            // manifest client-side). A private/DRM id simply fails to authorise
            // in the browser; nothing leaks, because no token is minted here.
            $rendered = true;
            return $this->render_public_by_id($output, $playbackid);
        }
        return $this->resolve_for_known_asset($output, $asset, $rendered);
    }

    /**
     * Resolve the replacement HTML for a shortcode whose asset IS in this
     * Moodle's library (post-capability-gate). DRM and private (non-DRM) assets
     * each get their own placeholder reason; only a public, resolvable asset
     * renders a player (degrading to the generic placeholder otherwise).
     *
     * @param \renderer_base $output The renderer to template with.
     * @param \stdClass $asset The asset row from get_by_playback_id.
     * @param bool $rendered Set to true (by reference) when a player is rendered.
     * @return string The replacement HTML for this shortcode.
     */
    protected function resolve_for_known_asset(
        \renderer_base $output,
        \stdClass $asset,
        bool &$rendered
    ): string {
        // DRM-protected and private (non-DRM) assets are not embedded — each
        // shows its own placeholder reason. DRM: there is no activity-agnostic
        // endpoint to mint a fresh DRM token on play. Private: the render-time
        // playback token would go stale in long-lived content (a forum post
        // opened later) and casual embeds have no client-side token refresh, so
        // embeds are public videos only. DRM is checked first.
        $blockkey = null;
        if (!empty($asset->drm_required)) {
            $blockkey = 'drmunavailable';
        } else if (($asset->access_policy ?? '') !== 'public') {
            $blockkey = 'privateunavailable';
        }
        if ($blockkey !== null) {
            return $this->render_unavailable($output, $blockkey);
        }

        $player = $this->render_player($output, $asset);
        if ($player === null) {
            // Not-ready / unresolvable — degrade to the placeholder, never a
            // fatal that would blank the page.
            return $this->render_unavailable($output);
        }

        $rendered = true;
        return $player;
    }

    /**
     * Render a tokenless public player straight from a bare playback id.
     *
     * Used when the id is not in this Moodle's FastPix library — it may be a
     * PUBLIC video from another FastPix workspace or account. Public playback
     * needs no signed token and no account credentials, so the browser can play
     * the HLS stream directly; this filter still makes no FastPix HTTP call (the
     * <fastpix-player> web component fetches the manifest client-side, mounted by
     * player_boot.js). If the id is actually private or DRM-protected, the stream
     * fails to authorise in the browser — no content leaks, because no token is
     * ever minted here. Metadata we cannot know for a foreign id (title, accent
     * colour) is omitted; the player uses its own defaults.
     *
     * @param \renderer_base $output The renderer to template with.
     * @param string $playbackid The captured bare playback id.
     * @return string The rendered player HTML.
     */
    protected function render_public_by_id(\renderer_base $output, string $playbackid): string {
        $payload = new \local_fastpix\dto\playback_payload(
            playbackid:          $playbackid,
            playbacktoken:       '',
            expiresatts:         0,
            drmrequired:         false,
            accentcolor:         null,
            defaultshowcaptions: false,
            drmtoken:            '',
            accesspolicy:        'public',
        );
        $renderable = new \filter_fastpix\output\player_embed($payload, '');
        return $output->render_from_template(
            'filter_fastpix/player',
            $renderable->export_for_template($output)
        );
    }

    /**
     * Render the "unavailable" placeholder, optionally with a specific reason.
     *
     * @param \renderer_base $output The renderer to template with.
     * @param string|null $messagekey A filter_fastpix lang key naming the reason
     *                                (e.g. 'drmunavailable'), or null for the
     *                                generic "unavailable" message.
     * @return string The rendered placeholder HTML.
     */
    protected function render_unavailable(\renderer_base $output, ?string $messagekey = null): string {
        $data = [];
        if ($messagekey !== null) {
            $data['message'] = get_string($messagekey, 'filter_fastpix');
        }
        return $output->render_from_template('filter_fastpix/unavailable', $data);
    }

    /**
     * Resolve playback for a found asset and render the player template.
     *
     * @param \renderer_base $output The renderer to template with.
     * @param \stdClass $asset The asset row from get_by_playback_id.
     * @return string|null The rendered player HTML, or null when the asset
     *                      cannot be rendered (non-public, not yet ready, or
     *                      otherwise unresolvable) — caller shows the placeholder.
     */
    protected function render_player(\renderer_base $output, \stdClass $asset): ?string {
        global $USER;

        // Only PUBLIC assets are embedded. The caller already diverts DRM and
        // private assets to the placeholder; this is the defensive backstop so a
        // render-time playback/DRM token is never minted for a non-public asset.
        // Such a short-TTL token would go stale in long-lived content (a forum
        // post opened later) and casual embeds have no client-side token
        // refresh. DRM/private playback belongs in mod_fastpix, which owns the
        // session + token-refresh machinery.
        if (($asset->access_policy ?? '') !== 'public') {
            return null;
        }

        try {
            // The resolve() service looks the asset up by fastpix_id internally,
            // so bridge from the playback-id row we already hold to its fastpix_id.
            $payload = \local_fastpix\service\playback_service::resolve(
                (string)$asset->fastpix_id,
                (int)$USER->id
            );
        } catch (\moodle_exception $e) {
            // The asset_not_ready / asset_not_found cases — show the placeholder,
            // not a fatal that would blank the page.
            return null;
        }

        $renderable = new \filter_fastpix\output\player_embed($payload, (string)($asset->title ?? ''));
        return $output->render_from_template(
            'filter_fastpix/player',
            $renderable->export_for_template($output)
        );
    }

    /**
     * Resolve the context the capability check runs against.
     *
     * Precedence: an explicit \context in $options, then a contextid to load,
     * then the filter's own stored context, then the system context. $PAGE is
     * deliberately not consulted — the filter also runs in AJAX, WS and cron
     * where $PAGE->context is unreliable.
     *
     * @param array $options The filter options passed to filter().
     * @return \context The context to gate against.
     */
    protected function resolve_context(array $options): \context {
        if (isset($options['context']) && $options['context'] instanceof \context) {
            $context = $options['context'];
        } else if (!empty($options['contextid'])) {
            $context = \context::instance_by_id($options['contextid']);
        } else if (!empty($this->context) && $this->context instanceof \context) {
            $context = $this->context;
        } else {
            $context = \context_system::instance();
        }
        return $context;
    }

    /**
     * Record a denial via a standard Moodle event, once per filter() render
     * (carrying the denied count), not once per shortcode — so a heavily-embedded
     * post cannot flood the log. The core log store records the acting user via
     * the event itself; no extra personal data is added.
     *
     * @param \context $context The context the check was denied in.
     * @param int $deniedcount How many shortcodes were denied in this render.
     * @return void
     */
    protected function log_capability_denied(\context $context, int $deniedcount): void {
        \filter_fastpix\event\capability_denied::create_from_context($context, $deniedcount)->trigger();
    }

    /**
     * Collect all shortcode matches in the text.
     *
     * Split out so the fast-path short-circuit in filter() can be verified to
     * run before any regex work. Returns PREG_SET_ORDER | PREG_OFFSET_CAPTURE
     * sets so callers get both the captured id and the byte offset for splicing.
     *
     * @param string $text The text to scan (already passed the short-circuit).
     * @return array The match sets, or an empty array when there are none.
     */
    protected function collect_matches(string $text): array {
        if (!preg_match_all(self::SHORTCODE_REGEX, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }
        return $matches;
    }
}
