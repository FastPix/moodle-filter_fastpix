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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_filter_fastpix.php');

/**
 * Tests for the FastPix shortcode filter.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_fastpix\text_filter
 */
final class filter_test extends \advanced_testcase {
    /**
     * Build a counting filter instance bound to the system context.
     *
     * @return testable_filter_fastpix
     */
    private function make_filter(): testable_filter_fastpix {
        return new testable_filter_fastpix(\context_system::instance(), []);
    }

    /**
     * Data provider for {@see self::test_match_and_no_match()}.
     *
     * Each case: input text, expected match count, expected playback id
     * (with the pb_ prefix) for single-match cases, expected attribute tail.
     *
     * @return array<string, array{0: string, 1: int, 2: ?string, 3: ?string}>
     */
    public static function match_and_no_match_provider(): array {
        return [
            'vanilla' => ['{fastpix:pb_88dc1234}', 1, 'pb_88dc1234', ''],
            'with attributes' => ['{fastpix:pb_abc start=10}', 1, 'pb_abc', ' start=10'],
            'hyphen and underscore in id' => ['{fastpix:pb_a-b_c}', 1, 'pb_a-b_c', ''],
            'embedded in prose' => ['Watch this: {fastpix:pb_x} now.', 1, 'pb_x', ''],
            'inside html attribute' => ['<p title="see {fastpix:pb_y}">…</p>', 1, 'pb_y', ''],
            'malformed empty id' => ['{fastpix:pb_}', 0, null, null],
            'malformed no pb prefix' => ['{fastpix:abc}', 0, null, null],
            'malformed unterminated' => ['{fastpix:pb_x', 0, null, null],
            'no fastpix token at all' => ['lorem ipsum', 0, null, null],
        ];
    }

    /**
     * The regex matches exactly the shortcodes it should, with correct captures.
     *
     * @dataProvider match_and_no_match_provider
     * @param string $input Text to filter.
     * @param int $expectedcount Number of expected matches.
     * @param string|null $expectedid Expected playback id (pb_ prefixed) when one match.
     * @param string|null $expectedtail Expected attribute-tail capture when one match.
     */
    public function test_match_and_no_match(
        string $input,
        int $expectedcount,
        ?string $expectedid,
        ?string $expectedtail
    ): void {
        $this->resetAfterTest(true);

        $filter = $this->make_filter();
        $output = $filter->filter($input);

        $this->assertCount($expectedcount, $filter->lastmatches);

        if ($expectedcount === 1) {
            $match = $filter->lastmatches[0];
            $this->assertSame($expectedid, 'pb_' . $match[1][0]);
            // Optional attribute tail; absent when the group did not participate.
            $tail = $match[2][0] ?? '';
            $this->assertSame($expectedtail, $tail);
        }

        // This phase only collects matches; the spliced replacement is the
        // verbatim shortcode, so the text is returned unchanged.
        $this->assertSame($input, $output);
    }

    /**
     * The strpos guard short-circuits before any regex on no-match text, and
     * the regex runs exactly once when the token is present.
     */
    public function test_short_circuit(): void {
        $this->resetAfterTest(true);

        // A large block with no '{fastpix:' token must never reach the regex.
        $haystack = str_repeat('lorem ipsum dolor sit amet {fast} {pix:} ', 30000);
        $this->assertGreaterThan(1000000, strlen($haystack));

        $filter = $this->make_filter();
        $output = $filter->filter($haystack);

        $this->assertSame(0, $filter->collectcalls, 'Regex must not run when the token is absent.');
        $this->assertSame($haystack, $output);

        // Positive control: when the token is present, the regex runs once.
        $filter = $this->make_filter();
        $filter->filter('intro {fastpix:pb_abc123} outro');
        $this->assertSame(1, $filter->collectcalls);
    }

    /**
     * The empty / non-string guard returns input untouched and never runs the regex.
     */
    public function test_empty_and_nonstring_guard(): void {
        $this->resetAfterTest(true);

        $filter = $this->make_filter();

        $this->assertSame('', $filter->filter(''));
        $this->assertSame(0, $filter->collectcalls);
    }

    /**
     * T6 mitigation: a user without mod/fastpix:view gets the escaped literal
     * shortcode text — never a player, never an empty string.
     */
    public function test_capability_denied_emits_escaped_literal(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        // Enrol but do NOT grant mod/fastpix:view in this context.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'guest');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        $shortcode = '{fastpix:pb_88dc1234}';
        $output = $filter->filter("before {$shortcode} after", ['context' => $context]);

        $this->assertStringContainsString(s($shortcode), $output);
        $this->assertStringNotContainsString('<fastpix-player', $output);
    }

    /**
     * Denial logging is collapsed to one structured line per filter() call: a
     * post with many denied embeds must not write one log line per shortcode.
     * Every denied match in a render shares the same context and user, so a
     * single line carrying the count is equivalent and bounds log volume.
     */
    public function test_capability_denied_logs_once_per_render(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        // Enrol but do NOT grant mod/fastpix:view: every match is denied.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'guest');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $filter = new testable_filter_fastpix($context, []);
        // Three shortcodes, all denied, in one filter() invocation.
        $text = '{fastpix:pb_a1} mid {fastpix:pb_b2} end {fastpix:pb_c3}';
        $output = $filter->filter($text, ['context' => $context]);

        $this->assertSame(1, $filter->denialogcalls, 'Denials must log once per render, not per shortcode.');
        $this->assertSame(3, $filter->lastdeniedcount, 'The single log line must carry the denied count.');
        // The user-facing behaviour is unchanged: each shortcode still becomes
        // its own escaped literal, and no player renders.
        $this->assertStringContainsString(s('{fastpix:pb_a1}'), $output);
        $this->assertStringContainsString(s('{fastpix:pb_c3}'), $output);
        $this->assertStringNotContainsString('<fastpix-player', $output);
    }

    /**
     * Denial is recorded via a standard Moodle event
     * (\filter_fastpix\event\capability_denied), fired once per render and
     * carrying the rendering context and the denied-shortcode count. This is the
     * reviewer-safe replacement for the old raw error_log() line. Uses the real
     * filter (not the counting fixture, which suppresses the trigger).
     */
    public function test_capability_denied_triggers_event(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        // Enrol but do NOT grant mod/fastpix:view: every match is denied.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'guest');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);

        $sink = $this->redirectEvents();
        // Two denied shortcodes in one render → exactly one event, count 2.
        $filter->filter('{fastpix:pb_a1} and {fastpix:pb_b2}', ['context' => $context]);
        $events = $sink->get_events();
        $sink->close();

        $denials = array_values(array_filter($events, static function ($e) {
            return $e instanceof \filter_fastpix\event\capability_denied;
        }));
        $this->assertCount(1, $denials, 'Exactly one denial event per render.');
        $this->assertSame($context->id, (int)$denials[0]->contextid);
        $this->assertSame((int)$user->id, (int)$denials[0]->userid);
        $this->assertSame(2, $denials[0]->other['deniedcount']);
    }

    /**
     * T6, sharper: the escaped-literal fallback must HTML-escape special
     * characters in the (attacker-controllable) attribute tail. Distinguishes a
     * real s() escape from passing the raw shortcode through verbatim.
     */
    public function test_capability_denied_escapes_html_in_shortcode(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'guest');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        // The attribute tail [^}]* permits HTML metacharacters — the XSS vector.
        $shortcode = '{fastpix:pb_x a="<b>&\'"}';
        $output = $filter->filter($shortcode, ['context' => $context]);

        // Escaped form is present; raw angle bracket from the tail is not.
        $this->assertStringContainsString(s($shortcode), $output);
        $this->assertStringNotContainsString('a="<b>', $output);
        $this->assertStringNotContainsString('<fastpix-player', $output);
    }

    /**
     * An authorised user referencing a playback id that is NOT in this Moodle's
     * library gets a tokenless public player rendered straight from the id — it
     * may be a public video from another FastPix workspace. The render is
     * tokenless (empty data-playback-token) and never the unavailable
     * placeholder. A private/DRM foreign id simply fails to play in the browser;
     * nothing leaks, because no token is minted server-side.
     */
    public function test_unknown_id_emits_tokenless_public_player(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        // Student archetype grants mod/fastpix:view, so the gate passes and we
        // reach the asset lookup.
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        // A playback id that is not in the asset table → get_by_playback_id null
        // → render straight from the id (cross-workspace public embed).
        $output = $filter->filter('{fastpix:pb_doesnotexist123}', ['context' => $context]);

        $this->assertStringContainsString('data-region="fastpix-player-wrapper"', $output);
        $this->assertStringContainsString('data-playback-id="doesnotexist123"', $output);
        // Public = tokenless: no server-minted playback token in the markup.
        $this->assertStringContainsString('data-playback-token=""', $output);
        $this->assertStringNotContainsString('filter_fastpix-unavailable', $output);
    }

    /**
     * Insert a ready, public asset and return its bare playback id.
     *
     * Public assets resolve tokenless — no signing key / JWT mint / FastPix
     * call — which keeps the render test deterministic and offline.
     *
     * @return string The playback id to use in a shortcode.
     */
    private function create_ready_public_asset(): string {
        global $DB;
        $playbackid = 'play' . random_string(8);
        $now = time();
        $DB->insert_record('local_fastpix_asset', (object)[
            'fastpix_id'          => 'fp' . random_string(10),
            'playback_id'         => $playbackid,
            'owner_userid'        => 0,
            'title'               => 'Embed render test asset',
            'status'              => 'ready',
            'access_policy'       => 'public',
            'drm_required'        => 0,
            'no_skip_required'    => 0,
            'has_captions'        => 0,
            'gdpr_delete_attempts' => 0,
            'timecreated'         => $now,
            'timemodified'        => $now,
        ]);
        return $playbackid;
    }

    /**
     * An authorised user with a resolvable asset gets the player wrapper.
     */
    public function test_authorised_render_emits_player(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $playbackid = $this->create_ready_public_asset();
        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        $output = $filter->filter("watch {fastpix:pb_{$playbackid}} now", ['context' => $context]);

        $this->assertStringContainsString('data-region="fastpix-player-wrapper"', $output);
        $this->assertStringContainsString('data-playback-id="' . $playbackid . '"', $output);
        $this->assertStringNotContainsString('filter_fastpix-unavailable', $output);
    }

    /**
     * No inline CSS: the rendered player carries the scoping class (sizing lives
     * in styles.css) and emits no inline style="" attribute. Moodle forbids
     * inline CSS, so this locks the move of the wrapper style into styles.css.
     */
    public function test_player_has_no_inline_style(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $playbackid = $this->create_ready_public_asset();
        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        $output = $filter->filter("{fastpix:pb_{$playbackid}}", ['context' => $context]);

        // The scoping hook styles.css targets must be present...
        $this->assertStringContainsString('filter_fastpix-player-wrapper', $output);
        // ...and there must be no inline style attribute on the emitted markup.
        $this->assertStringNotContainsString('style=', $output);
    }

    /**
     * Self-nesting lock: emitted player HTML must contain no shortcode the
     * filter would re-trigger on. Locks the "output must not re-trigger the
     * filter" footgun (CLAUDE.md testing posture).
     */
    public function test_output_contains_no_shortcode(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $playbackid = $this->create_ready_public_asset();
        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        $output = $filter->filter("{fastpix:pb_{$playbackid}}", ['context' => $context]);

        $this->assertStringContainsString('fastpix-player-wrapper', $output);
        $matched = preg_match_all(\filter_fastpix\text_filter::SHORTCODE_REGEX, $output);
        $this->assertSame(0, $matched, 'Rendered player HTML must not contain a re-triggering shortcode.');
    }

    /**
     * DRM-required assets are out of scope for casual embeds: the filter shows
     * the placeholder rather than a player it cannot mint a fresh token for.
     */
    public function test_drm_asset_emits_unavailable(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $playbackid = $this->create_ready_public_asset();
        // Promote the fixture asset to DRM-required.
        $DB->set_field('local_fastpix_asset', 'drm_required', 1, ['playback_id' => $playbackid]);
        $DB->set_field('local_fastpix_asset', 'access_policy', 'drm', ['playback_id' => $playbackid]);

        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        $output = $filter->filter("{fastpix:pb_{$playbackid}}", ['context' => $context]);

        $this->assertStringContainsString('filter_fastpix-unavailable', $output);
        $this->assertStringNotContainsString('fastpix-player-wrapper', $output);
        // A DRM asset shows the DRM-specific reason, not the generic placeholder,
        // so authors understand the embed limitation rather than a silent failure.
        $this->assertStringContainsString(
            get_string('drmunavailable', 'filter_fastpix'),
            $output
        );
    }

    /**
     * Private (non-DRM) assets are out of scope for casual embeds: a render-time
     * playback token would go stale in long-lived content and casual embeds have
     * no client refresh, so embeds are public-only. The filter shows the
     * private-specific placeholder rather than a player.
     */
    public function test_private_asset_emits_unavailable(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $playbackid = $this->create_ready_public_asset();
        // Demote the fixture asset to private (non-DRM).
        $DB->set_field('local_fastpix_asset', 'access_policy', 'private', ['playback_id' => $playbackid]);

        $context = \context_course::instance($course->id);
        $filter = new \filter_fastpix\text_filter($context, []);
        $output = $filter->filter("{fastpix:pb_{$playbackid}}", ['context' => $context]);

        $this->assertStringContainsString('filter_fastpix-unavailable', $output);
        $this->assertStringNotContainsString('fastpix-player-wrapper', $output);
        // Private shows its own reason, distinct from the DRM and generic messages.
        $this->assertStringContainsString(
            get_string('privateunavailable', 'filter_fastpix'),
            $output
        );
    }

    /**
     * The test data generator used by the Behat scenarios inserts a valid,
     * resolvable asset row. Exercises the same create_asset() the Behat step
     * "the following \"filter_fastpix > assets\" exist" calls, so the Behat
     * fixture path is covered even where Behat itself is not configured.
     */
    public function test_behat_asset_generator_inserts_valid_row(): void {
        global $DB;
        $this->resetAfterTest();

        /** @var \filter_fastpix_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('filter_fastpix');
        $id = $generator->create_asset(['playback_id' => 'genplay123', 'title' => 'Generated']);

        $row = $DB->get_record('local_fastpix_asset', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('genplay123', $row->playback_id);
        $this->assertSame('ready', $row->status);
        $this->assertSame('public', $row->access_policy);
        $this->assertSame(0, (int)$row->drm_required);

        // The row resolves through the same path the filter renders with.
        $asset = \local_fastpix\service\asset_service::get_by_playback_id('genplay123');
        $this->assertNotNull($asset);
    }
}
