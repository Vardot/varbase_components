@varbase_components @admin
Feature: Varbase Components - administration pages
  As a site administrator
  I want the core administration pages to render their own content with Varbase
  Components and Drupal Canvas enabled - not just return a 200 response

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The core administration pages render their headings
    When I open the administration page "/admin/content"
    Then "h1" should contain text "Content"
    When I open the administration page "/admin/structure"
    Then "h1" should contain text "Structure"
    When I open the administration page "/admin/config"
    Then "h1" should contain text "Configuration"
    When I open the administration page "/admin/appearance"
    Then "h1" should contain text "Appearance"

  Scenario: The status report renders its content without unexpected errors
    When I open the administration page "/admin/reports/status"
    Then "h1" should contain text "Status report"
    And I should see "Drupal"
    And I should see "PHP"
    And I should not see "The website encountered an unexpected error"
