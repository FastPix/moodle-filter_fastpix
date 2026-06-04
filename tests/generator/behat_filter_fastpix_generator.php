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
 * Behat data generator for filter_fastpix.
 *
 * Wires the Gherkin step:
 *   Given the following "filter_fastpix > assets" exist:
 *     | playback_id | title      |
 *     | abc123      | Demo video |
 *
 * into filter_fastpix_generator::create_asset() which inserts a row into
 * local_fastpix_asset (the upstream local_fastpix plugin is frozen; this
 * generator writes directly to the table rather than calling internal APIs).
 *
 * @package    filter_fastpix
 * @category   test
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for filter_fastpix.
 *
 * @package    filter_fastpix
 * @category   test
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_filter_fastpix_generator extends behat_generator_base {
    /**
     * Declare the entities that can be created via Behat tables.
     *
     * The "assets" entity maps to filter_fastpix_generator::create_asset().
     * Only playback_id is required; every other column falls back to the safe
     * defaults defined in create_asset() (public, ready, drm_required=0).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function get_creatable_entities(): array {
        return [
            'assets' => [
                'singular'      => 'asset',
                'datagenerator' => 'asset',
                'required'      => ['playback_id'],
            ],
        ];
    }
}
