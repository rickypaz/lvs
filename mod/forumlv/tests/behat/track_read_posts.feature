@mod @mod_forumlv
Feature: A teacher can set one of 3 possible options for tracking read forumlv posts
  In order to ease the forumlv posts follow up
  As a user
  I need to distinct the unread posts from the read ones

  Background:
    Given the following "users" exists:
      | username | firstname | lastname | email | trackforumlvs |
      | student1 | Student | 1 | student1@asd.com | 1 |
    And the following "courses" exists:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exists:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"
    And I follow "Course 1"
    And I turn editing mode on

  @javascript
  Scenario: Tracking forumlv posts on
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test forumlv name |
      | Forum type | Standard forumlv for general use |
      | Description | Test forumlv description |
      | Read tracking for this forumlv | On |
    And I add a new discussion to "Test forumlv name" forumlv with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I wait "6" seconds
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I follow "Course 1"
    And I should not see "1 unread post"

  @javascript
  Scenario: Tracking forumlv posts off
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test forumlv name |
      | Forum type | Standard forumlv for general use |
      | Description | Test forumlv description |
      | Read tracking for this forumlv | Off |
    And I add a new discussion to "Test forumlv name" forumlv with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I wait "6" seconds
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test forumlv name"
    And I should not see "Track unread posts"

  @javascript
  Scenario: Tracking forumlv posts optional
    Given I add a "Forum" to section "1" and I fill the form with:
      | Forum name | Test forumlv name |
      | Forum type | Standard forumlv for general use |
      | Description | Test forumlv description |
      | Read tracking for this forumlv | Optional |
    And I add a new discussion to "Test forumlv name" forumlv with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I wait "6" seconds
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "Test forumlv name"
    And I follow "Don't track unread posts"
    And I wait "4" seconds
    And I follow "Course 1"
    And I should not see "1 unread post"
    And I follow "Test forumlv name"
    And I follow "Track unread posts"
    And I wait "4" seconds
    And I follow "1"
    And I follow "Course 1"
    And I should not see "1 unread post"
