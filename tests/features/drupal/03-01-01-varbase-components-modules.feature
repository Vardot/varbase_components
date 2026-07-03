@varbase_components @modules
Feature: Varbase Components - component stack enabled
  As a site administrator
  I want Varbase Components to enable its UI Patterns and UI Icons component
  stack so that the components handler is ready out of the box

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The modules page lists Varbase Components and its component stack
    When I open the administration page "/admin/modules"
    Then I should see "Varbase Components"
    And I should see "UI Patterns"
    And I should see "UI Icons"
    And I should not see "The website encountered an unexpected error"
