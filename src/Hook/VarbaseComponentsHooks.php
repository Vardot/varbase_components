<?php

namespace Drupal\varbase_components\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for varbase_components.
 */
class VarbaseComponentsHooks {

  /**
   * Prepares global variables for all templates.
   *
   * @param array $variables
   *   An associative array containing variables for the template.
   */
  #[Hook('preprocess')]
  public function preprocess(array &$variables): void {
    // Get the default active theme.
    $variables['active_theme'] = \Drupal::config('system.theme')->get('default');
  }

}
