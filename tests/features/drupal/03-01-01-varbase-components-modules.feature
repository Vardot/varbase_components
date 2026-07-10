@varbase_components @modules
Feature: Varbase Components - module enabled with Drupal Canvas
  As a site administrator
  I want Varbase Components enabled next to Drupal Canvas and the core
  Navigation module so that the hidden-components handler is active out of the
  box

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The recipe modules are installed and locked on the modules page
    When I open the administration page "/admin/modules"
    Then I should see "Varbase Components"
    And I should see "Drupal Canvas"
    And the checkbox "#edit-modules-varbase-components-enable" should be checked
    And the checkbox "#edit-modules-canvas-enable" should be checked
    And the checkbox "#edit-modules-navigation-enable" should be checked
    And "#edit-modules-varbase-components-enable" should be disabled
    And I should not see "The website encountered an unexpected error"
