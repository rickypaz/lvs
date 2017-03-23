@mod @mod_chatlv
Feature: Chatlv reset
  In order to reuse past chatlv activities
  As a teacher
  I need to remove all previous data.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Tina | Teacher1 | teacher1@example.com |
      | student1 | Sam | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name           | Description           | course | idnumber |
      | chatlv     | Test chatlv name | Test chatlv description | C1     | chatlv1    |

  Scenario: Use course reset to update chatlv start date
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I navigate to "Edit settings" node in "Course administration"
    And I set the following fields to these values:
      | startdate[day]       | 1 |
      | startdate[month]     | January |
      | startdate[year]      | 2020 |
    And I press "Save and display"
    And I follow "Test chatlv name"
    And I navigate to "Edit settings" node in "Chatlv administration"
    And I set the following fields to these values:
      | chatlvtime[day]       | 1 |
      | chatlvtime[month]     | January |
      | chatlvtime[year]      | 2020 |
      | chatlvtime[hour]      | 12 |
      | chatlvtime[minute]    | 00 |
    And I press "Save and display"
    When I navigate to "Reset" node in "Course administration"
    And I set the following fields to these values:
      | id_reset_start_date_enabled | 1  |
      | reset_start_date[day]       | 1 |
      | reset_start_date[month]     | January |
      | reset_start_date[year]      | 2030 |
    And I press "Reset course"
    And I should see "Date changed" in the "Chats" "table_row"
    And I press "Continue"
    Then I follow "Course 1"
    And I follow "Test chatlv name"
    And I navigate to "Edit settings" node in "Chatlv administration"
    And I expand all fieldsets
    And the "id_chatlvtime_year" select box should contain "2030"
