@mod @mod_forumlv
Feature: New discussions and discussions with recently added replies are displayed first
  In order to use forumlv as a discussion tool
  As a user
  I need to see currently active discussions first

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | teacher1  | Teacher   | 1         | teacher1@example.com  |
      | student1  | Student   | 1         | student1@example.com  |
    And the following "courses" exist:
      | fullname  | shortname | category  |
      | Course 1  | C1        | 0         |
    And the following "course enrolments" exist:
      | user      | course    | role            |
      | teacher1  | C1        | editingteacher  |
      | student1  | C1        | student         |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Forumlv" to section "1" and I fill the form with:
      | Forumlv name  | Course general forumlv                |
      | Description | Single discussion forumlv description |
      | Forumlv type  | Standard forumlv for general use      |
    And I log out

  #
  # We need javascript/wait to prevent creation of the posts in the same second. The threads
  # would then ignore each other in the prev/next navigation as the Forumlv is unable to compute
  # the correct order.
  #
  @javascript
  Scenario: Replying to a forumlv post or editing it puts the discussion to the front
    Given I log in as "student1"
    And I follow "Course 1"
    And I follow "Course general forumlv"
    #
    # Add three posts into the forumlv.
    #
    When I add a new discussion to "Course general forumlv" forumlv with:
      | Subject | Forumlv post 1            |
      | Message | This is the first post  |
    And I add a new discussion to "Course general forumlv" forumlv with:
      | Subject | Forumlv post 2            |
      | Message | This is the second post |
    And I add a new discussion to "Course general forumlv" forumlv with:
      | Subject | Forumlv post 3            |
      | Message | This is the third post  |
    #
    # Edit one of the forumlv posts.
    #
    And I follow "Forumlv post 2"
    And I click on "Edit" "link" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' forumlvpost ')][contains(., 'Forumlv post 2')]" "xpath_element"
    And I set the following fields to these values:
      | Subject | Edited forumlv post 2     |
    And I press "Save changes"
    And I wait to be redirected
    And I log out
    #
    # Reply to another forumlv post.
    #
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Course general forumlv"
    And I follow "Forumlv post 1"
    And I click on "Reply" "link" in the "//div[@aria-label='Forumlv post 1 by Student 1']" "xpath_element"
    And I set the following fields to these values:
      | Message | Reply to the first post |
    And I press "Post to forumlv"
    And I wait to be redirected
    And I am on site homepage
    And I follow "Course 1"
    And I follow "Course general forumlv"
    #
    # Make sure the order of the forumlv posts is as expected (most recently participated first).
    #
    Then I should see "Forumlv post 3" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=3]" "xpath_element"
    And I should see "Edited forumlv post 2" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=2]" "xpath_element"
    And I should see "Forumlv post 1" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=1]" "xpath_element"
    #
    # Make sure the next/prev navigation uses the same order of the posts.
    #
    And I follow "Edited forumlv post 2"
    And "//a[@aria-label='Next discussion: Forumlv post 1']" "xpath_element" should exist
    And "//a[@aria-label='Previous discussion: Forumlv post 3']" "xpath_element" should exist
