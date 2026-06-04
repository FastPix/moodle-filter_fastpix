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

/**
 * Renderable for a single FastPix embed.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_fastpix\output;

use local_fastpix\dto\playback_payload;
use renderer_base;

/**
 * Maps a playback_payload onto the filter_fastpix/player template context.
 *
 * No business logic: this only shuttles the resolved payload into template
 * variables. The DRM token is deliberately NOT exported — it is fetched
 * client-side by player_boot.js on first play (invariant 8), so the rendered
 * HTML carries an empty drm-token slot.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class player_embed implements \renderable, \templatable {
    /** @var playback_payload The resolved playback payload. */
    protected playback_payload $payload;

    /** @var string The asset title, surfaced as the player's metadata-video-title. */
    protected string $videotitle;

    /**
     * Constructor.
     *
     * @param playback_payload $payload The payload from playback_service::resolve.
     * @param string $videotitle The asset title (CC9 metadata-video-title).
     */
    public function __construct(playback_payload $payload, string $videotitle = '') {
        $this->payload = $payload;
        $this->videotitle = $videotitle;
    }

    /**
     * Export the embed for the filter_fastpix/player template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context. Keys match the player.mustache placeholders.
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'playbackid'    => $this->payload->playbackid,
            'playbacktoken' => $this->payload->playbacktoken,
            'drmrequired'   => $this->payload->drmrequired,
            'accentcolor'   => $this->payload->accentcolor,
            'expiresatts'   => $this->payload->expiresatts,
            'videotitle'    => $this->videotitle,
        ];
    }
}
