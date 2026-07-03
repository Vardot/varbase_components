@varbase_components @admin
Feature: Varbase Components - administration pages
  As a site administrator
  I want the administration pages to be reachable with Varbase Components and its
  UI Patterns / UI Icons component stack enabled

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The core administration pages are reachable for the administrator
    When I open the administration page "/admin/content"
    Then I should not see "Page not found"
    When I open the administration page "/admin/structure"
    Then I should not see "Page not found"
    When I open the administration page "/admin/config"
    Then I should not see "Page not found"
    When I open the administration page "/admin/appearance"
    Then I should not see "Page not found"
    When I open the administration page "/admin/reports/status"
    Then I should not see "Page not found"
