@mod @mod_wikilv
Feature: A teacher can set a wikilv to be collaborative or individual
  In order to allow both collaborative wikilvs and individual journals with history register
  As a teacher
  I need to select whether the wikilv is collaborative or individual

  @javascript
  Scenario: Collaborative and individual wikilvs
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Wikilv" to section "1" and I fill the form with:
      | Wikilv name | Collaborative wikilv name |
      | Description | Collaborative wikilv description |
      | First page name | Collaborative index |
      | Wikilv mode | Collaborative wikilv |
    And I follow "Collaborative wikilv name"
    And I press "Create page"
    And I set the following fields to these values:
      | HTML format | Collaborative teacher1 edition |
    And I press "Save"
    And I follow "Course 1"
    And I add a "Wikilv" to section "1" and I fill the form with:
      | Wikilv name | Individual wikilv name |
      | Description | Individual wikilv description |
      | First page name | Individual index |
      | Wikilv mode | Individual wikilv |
    And I follow "Individual wikilv name"
    And I press "Create page"
    And I set the following fields to these values:
      | HTML format | Individual teacher1 edition |
    And I press "Save"
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    When I follow "Collaborative wikilv name"
    Then I should see "Collaborative teacher1 edition"
    And I follow "Edit"
    And I set the following fields to these values:
      | HTML format | Collaborative student1 edition |
    And I press "Save"
    And I should not see "Collaborative teacher1 edition"
    And I should see "Collaborative student1 edition"
    And I follow "Course 1"
    And I follow "Individual wikilv name"
    And I should not see "Individual teacher1 edition"
    And I press "Create page"
    And I set the following fields to these values:
      | HTML format | Individual student1 edition |
    And I press "Save"
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Individual wikilv name"
    And I should not see "Individual teacher1 edition"
    And I should not see "Individual student1 edition"
    And I press "Create page"
    And I set the following fields to these values:
      | HTML format | Individual student2 edition |
    And I press "Save"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Collaborative wikilv name"
    And I should see "Collaborative student1 edition"
    And I follow "Course 1"
    And I follow "Individual wikilv name"
    And I should see "Individual teacher1 edition"
    And I should not see "Individual student1 edition"
    And I set the field "uid" to "Student 1"
    And I should see "Individual student1 edition"
    And I should not see "Individual teacher1 edition"
    And I set the field "uid" to "Student 2"
    And I should see "Individual student2 edition"
    And I should not see "Individual teacher1 edition"


