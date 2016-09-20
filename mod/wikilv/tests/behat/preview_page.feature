@mod @mod_wikilv
Feature: Edited wikilv pages may be previewed before saving
  In order to avoid silly mistakes
  As a user
  I need to preview pages before saving changes

  Background:
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
    And I add a "Wikilv" to section "1" and I fill the form with:
      | Wikilv name | Test wikilv name |
      | Description | Test wikilv description |
      | First page name | First page |
      | Wikilv mode | Collaborative wikilv |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test wikilv name"
    When I press "Create page"
    And I set the following fields to these values:
      | HTML format | Student page contents to be previewed |
    And I press "Preview"
    Then I expand all fieldsets
    And I should see "This is a preview. Changes have not been saved yet"
    And I should see "Student page contents to be previewed"
    And I press "Save"
    And I should see "Student page contents to be previewed"
    And I follow "Edit"

  @javascript
  Scenario: Page contents preview before saving with Javascript enabled
    Then the field "HTML format" matches value "Student page contents to be previewed"
    And I press "Cancel"

  Scenario: Page contents preview before saving with Javascript disabled
    Then the field "HTML format" matches value "Student page contents to be previewed"
    And I press "Cancel"
