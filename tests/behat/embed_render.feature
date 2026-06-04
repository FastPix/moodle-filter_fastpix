@filter @filter_fastpix
Feature: FastPix shortcode embeds in forum posts
  In order to share video content without creating a graded activity
  As a teacher
  I need to paste a {fastpix:pb_<id>} shortcode into a forum post and have
  authorised students see the player wrapper while unauthorised viewers see
  only the escaped literal text

  # No @javascript tag: the <fastpix-player> web component is mounted by AMD
  # after page load, but the server-rendered wrapper div is present in the
  # static HTML.  We assert [data-region='fastpix-player-wrapper'] which is
  # emitted by filter.php → templates/player.mustache on every authorised,
  # public-asset match.  Selenium / a real FastPix CDN is not required.
  #
  # Capability model reminder (system-overview §7):
  #   filter_fastpix does NOT define its own capability.  It gates every
  #   shortcode match on has_capability('mod/fastpix:view', $context).
  #   The student archetype has this capability by default (mod_fastpix
  #   db/access.php).  Scenario 5 strips it via a Prohibit override so the
  #   filter emits the escaped literal — proving the T6 isolation works purely
  #   through the capability-in-context check (no asset-to-course binding).

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name         | intro         | idnumber |
      | forum    | C1     | Course forum | Forum intro.  | forum1   |
    And the following "filter_fastpix > assets" exist:
      | playback_id | title      |
      | abc123test  | Demo video |
    And the "fastpix" filter is "on"

  Scenario: An authorised student viewing a forum post that contains a shortcode sees the player wrapper
    # Teacher posts the shortcode into a forum discussion.  The forum renders
    # its message through Moodle's format_text() pipeline which invokes all
    # enabled text filters, including filter_fastpix.  Because student1 is
    # enrolled with the student role, has_capability('mod/fastpix:view') is
    # true and the filter emits the server-side player wrapper div.
    Given the following "mod_forum > discussions" exist:
      | forum  | user     | name              | message                                                  |
      | forum1 | teacher1 | Video post        | Watch this: {fastpix:pb_abc123test} — enjoy the lecture. |
    When I am on the "Course forum" "forum activity" page logged in as "student1"
    And I follow "Video post"
    Then "[data-region='fastpix-player-wrapper']" "css_element" should exist
    And I should not see "{fastpix:pb_abc123test}"

  Scenario: A viewer without mod/fastpix:view in the rendering context sees the escaped literal not a player
    # This scenario exercises the T6 cross-context isolation rule.
    # The asset belongs to no course — filter_fastpix never binds assets to
    # courses.  Isolation is enforced entirely by the capability-in-context
    # check inside filter.php: if has_capability('mod/fastpix:view') is false
    # the filter emits s($full_shortcode) — the visible escaped literal — and
    # never a player.
    #
    # We strip mod/fastpix:view from the student role in Course 1's context
    # using a Prohibit override.  This is the cleanest faithful approach:
    # it removes the capability from the rendering context without removing
    # the enrolment (the user can still read the forum post) so we can verify
    # what the filter outputs for an authenticated-but-unauthorised viewer.
    Given the following "permission overrides" exist:
      | capability        | permission | role    | contextlevel | reference |
      | mod/fastpix:view  | Prohibit   | student | Course       | C1        |
    And the following "mod_forum > discussions" exist:
      | forum  | user     | name                   | message                                                  |
      | forum1 | teacher1 | Isolation test post    | Watch this: {fastpix:pb_abc123test} — enjoy the lecture. |
    When I am on the "Course forum" "forum activity" page logged in as "student1"
    And I follow "Isolation test post"
    Then I should see "{fastpix:pb_abc123test}"
    And "[data-region='fastpix-player-wrapper']" "css_element" should not exist
