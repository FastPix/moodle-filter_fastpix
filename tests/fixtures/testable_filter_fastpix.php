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
 * Counting test double for the FastPix shortcode filter.
 *
 * @package    filter_fastpix
 * @category   test
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_fastpix;

/**
 * Counting subclass: exposes how often the regex pass ran and the last match
 * set, so tests can prove the fast-path short-circuit fires before any regex.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_filter_fastpix extends \filter_fastpix\text_filter {
    /** @var int Number of times collect_matches() (the regex pass) was invoked. */
    public $collectcalls = 0;

    /** @var array The match sets returned by the most recent regex pass. */
    public $lastmatches = [];

    #[\Override]
    protected function collect_matches(string $text): array {
        $this->collectcalls++;
        $this->lastmatches = parent::collect_matches($text);
        return $this->lastmatches;
    }
}
