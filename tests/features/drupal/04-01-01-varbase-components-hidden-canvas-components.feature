@varbase_components @hidden_components
Feature: Varbase Components - hidden Drupal Canvas components
  As a site builder
  I want administrative components kept out of the Drupal Canvas component
  library so that only content components are offered when building pages

  The Drupal Canvas component config endpoint at
  /canvas/api/v0/config/component returns a JSON object keyed by the IDs of the
  components that are ENABLED (offered in the library). Components listed in
  varbase_components.settings:hidden_canvas_components are created disabled by
  the component presave hook and the module install hook, so their IDs are
  absent from that response while ordinary components remain.

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Hidden administrative components are withheld while ordinary components remain
    When I am on "/canvas/api/v0/config/component"
    # The endpoint returned the real component library: an ordinary content
    # component is enabled and therefore offered.
    Then I should see "block.system_branding_block"
    # A component on the hidden list is created disabled, so its ID is not in
    # the enabled set returned by the endpoint.
    And I should not see "sdc.navigation.message"
    And I should not see "Access denied"
    And I should not see "The website encountered an unexpected error"
