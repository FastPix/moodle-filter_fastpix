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
 * Version metadata for the FastPix text filter.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'filter_fastpix';
$plugin->version      = 2026061503;
$plugin->requires     = 2024100100; // Moodle 4.5 LTS.
$plugin->maturity     = MATURITY_STABLE;
$plugin->release      = '1.0.3';
$plugin->dependencies = [
    // Reuses mod/fastpix:view (capability) and mirrors its <fastpix-player>
    // markup (CC9). All asset/playback/watermark/token operations route through
    // local_fastpix services — this filter makes no FastPix HTTP calls itself.
    'mod_fastpix'   => 2026061500,
    'local_fastpix' => 2026061500,
];
