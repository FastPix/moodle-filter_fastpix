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
 * Event: FastPix embed capability denied.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_fastpix\event;

/**
 * Fired once per text-filter render in which one or more {fastpix:pb_<id>}
 * shortcodes were denied because the viewer lacks mod/fastpix:view in the
 * rendering context (the T6 capability gate). Carries the number of denied
 * shortcodes in that render, so the event is emitted once — not once per
 * shortcode — and a heavily-embedded post cannot flood the log.
 *
 * Replaces the previous raw server-log denial line: a standard Moodle event is
 * the idiomatic, Plugins-Directory-safe way to record access denials. The event
 * records the acting user's id via the core log store like every other event;
 * no additional personal data is placed in `other`.
 *
 * The class name is snake_case by Moodle Frankenstyle — the event system and
 * autoloader resolve \filter_fastpix\event\capability_denied by this exact name.
 *
 * @package    filter_fastpix
 * @copyright  2026 FastPix Inc. <support@fastpix.io>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capability_denied extends \core\event\base {
    /**
     * Initialise the event metadata.
     *
     * Read-level ('r') because a denied render is a (failed) view attempt; it
     * mutates nothing. LEVEL_OTHER and no objecttable because the filter owns
     * no database table — it stores nothing.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = null;
    }

    /**
     * Create the event from the rendering context and the denied-shortcode count.
     *
     * @param \context $context The context the capability check was denied in.
     * @param int $deniedcount How many shortcodes were denied in this render.
     * @return self The constructed (untriggered) event.
     */
    public static function create_from_context(\context $context, int $deniedcount): self {
        return self::create([
            'context' => $context,
            'other' => ['deniedcount' => $deniedcount],
        ]);
    }

    /**
     * Get the localised event name.
     *
     * @return string The event name.
     */
    public static function get_name() {
        return get_string('eventcapabilitydenied', 'filter_fastpix');
    }

    /**
     * Get a human-readable description of what happened.
     *
     * @return string The event description.
     */
    public function get_description() {
        $deniedcount = (int)($this->other['deniedcount'] ?? 0);
        return "The user with id '{$this->userid}' was denied {$deniedcount} FastPix " .
            "video embed(s) in context '{$this->contextid}' (missing mod/fastpix:view).";
    }
}
