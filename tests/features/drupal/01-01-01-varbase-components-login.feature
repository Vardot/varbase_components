@varbase_components @login
Feature: Varbase Components - login page
  As a visitor
  I want the login page and authentication to keep working with Varbase
  Components and Drupal Canvas enabled

  Scenario: The login form renders its fields for an anonymous visitor
    Given I am an anonymous visitor
    When I am on "/user/login"
    Then "#user-login-form" should be visible
    And I should see "Username"
    And I should see "Password"
    And "#edit-name" should be editable
    And "#edit-pass" should be editable
    And "#edit-submit" should have value "Log in"
    And I should not see "Page not found"
    And I should not see "The website encountered an unexpected error"

  Scenario: The Webmaster can authenticate and reach the account page
    Given I am a logged in user with the "Webmaster" user
    When I am on "/user"
    Then I should see "Member for"
    And I should not see "Access denied"
    And I should not see "The website encountered an unexpected error"
