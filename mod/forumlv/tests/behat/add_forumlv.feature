@mod @mod_forumlv @_file_upload
Feature: Add forumlv activities and discussions
  In order to discuss topics with other users
  As a teacher
  I need to add forumlv activities to moodle courses

  @javascript
  Scenario: Add a forumlv and a discussion attaching files
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Forumlv" to section "1" and I fill the form with:
      | Forumlv name | Test forumlv name |
      | Forumlv type | Standard forumlv for general use |
      | Description | Test forumlv description |
    And I add a new discussion to "Test forumlv name" forumlv with:
      | Subject | Forumlv post 1 |
      | Message | This is the body |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    When I add a new discussion to "Test forumlv name" forumlv with:
      | Subject | Post with attachment |
      | Message | This is the body |
      | Attachment | lib/tests/fixtures/empty.txt |
    And I reply "Forumlv post 1" post from "Test forumlv name" forumlv with:
      | Subject | Reply with attachment |
      | Message | This is the body |
      | Attachment | lib/tests/fixtures/upload_users.csv |
    Then I should see "Reply with attachment"
    And I should see "upload_users.csv"
    And I follow "Test forumlv name"
    And I follow "Post with attachment"
    And I should see "empty.txt"
    And I follow "Edit"
    And the field "Attachment" matches value "empty.txt"
