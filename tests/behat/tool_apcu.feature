@tool @tool_apcu @javascript
Feature: Using the admin tool APCu management
  In order to show the APCu management
  As admin
  I need to be able open the APCu management page

  Scenario: Calling the APCu management page
    When I log in as "admin"
    And I navigate to "Server > APCu management" in site administration
    Then I should see "General Cache Information"
    And I should see "Cache Information"
    And I should see "Runtime Settings"
    And I should see "Host Status Diagrams"

  Scenario: Clearing the APCu cache from the APCu management page
    Given I log in as "admin"
    And I visit "/admin/tool/apcu/tests/behat/fixtures/store_apcu_entry.php"
    And I should see "APCu cache entry stored"
    And I visit "/admin/tool/apcu/index.php?OB=2&SEARCH=tool_apcu_behat_entry"
    And "tool_apcu_behat_entry" "table_row" should exist
    When I navigate to "Server > APCu management" in site administration
    Then the "href" attribute of "Clear Cache" "link" should contain "sesskey="
    And I click on "Clear Cache" "link" confirming the dialogue
    And I should see "The APCu cache has been cleared." in the ".alert-success" "css_element"
    And I should see "General Cache Information"
    And I visit "/admin/tool/apcu/index.php?OB=2&SEARCH=tool_apcu_behat_entry"
    And "tool_apcu_behat_entry" "table_row" should not exist

  Scenario: Deleting a user cache entry from the APCu management page
    Given I log in as "admin"
    And I visit "/admin/tool/apcu/tests/behat/fixtures/store_apcu_entry.php"
    And I should see "APCu cache entry stored"
    When I visit "/admin/tool/apcu/index.php?OB=2&SEARCH=tool_apcu_behat_entry"
    Then "tool_apcu_behat_entry" "table_row" should exist
    And the "href" attribute of "Delete Now" "link" should contain "sesskey="
    And I click on "Delete Now" "link" in the "tool_apcu_behat_entry" "table_row"
    And I should see "has been deleted." in the ".alert-success" "css_element"
    And I should see "tool_apcu_behat_entry" in the ".alert-success" "css_element"
    And the field "SEARCH" matches value "tool_apcu_behat_entry"
    And "tool_apcu_behat_entry" "table_row" should not exist
