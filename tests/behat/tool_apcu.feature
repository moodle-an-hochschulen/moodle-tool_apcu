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
