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
 * PHPUnit/Behat data generator for filter_fastpix.
 *
 * Provides create_asset() so both PHPUnit and the Behat generator can insert a
 * row into local_fastpix_asset without touching local_fastpix's internal code
 * (that plugin is frozen for Plugins Directory review).
 *
 * @package    filter_fastpix
 * @category   test
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator for filter_fastpix tests.
 *
 * @package    filter_fastpix
 * @category   test
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_fastpix_generator extends testing_data_generator {
    /**
     * Insert a public, ready asset row into local_fastpix_asset.
     *
     * Only the columns that are NOT NULL without a DB-level default are
     * required; everything else gets a safe default. Using access_policy=public
     * and drm_required=0 means playback_service::resolve() returns tokenless —
     * no FastPix HTTP call, deterministic in CI.
     *
     * Required NOT-NULL columns (no schema-level default):
     *   fastpix_id, owner_userid, title, status, access_policy,
     *   drm_required, no_skip_required, has_captions, gdpr_delete_attempts,
     *   timecreated, timemodified.
     *
     * playback_id is nullable in the schema but is the shortcode key; callers
     * must supply it.
     *
     * @param array|object $record Column overrides; playback_id is required.
     * @return int The id of the inserted row.
     * @throws coding_exception When playback_id is not supplied.
     */
    public function create_asset($record = null): int {
        global $DB;

        $record = (array) $record;

        if (empty($record['playback_id'])) {
            throw new coding_exception('create_asset() requires playback_id to be set.');
        }

        $now = time();

        // Apply defaults for every NOT-NULL column that has no schema default
        // and was not supplied by the caller.
        $defaults = [
            'fastpix_id'           => 'fp' . substr(md5(uniqid('', true)), 0, 12),
            'owner_userid'         => 0,
            'title'                => 'Test asset ' . $record['playback_id'],
            'status'               => 'ready',
            'access_policy'        => 'public',
            'drm_required'         => 0,
            'no_skip_required'     => 0,
            'has_captions'         => 0,
            'gdpr_delete_attempts' => 0,
            'timecreated'          => $now,
            'timemodified'         => $now,
        ];

        foreach ($defaults as $col => $val) {
            if (!array_key_exists($col, $record)) {
                $record[$col] = $val;
            }
        }

        return $DB->insert_record('local_fastpix_asset', (object) $record);
    }
}
