<?php

namespace Drupal\varbase_components\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for varbase_components.
 */
class VarbaseComponentsHooks {

  /**
   * Constructs a VarbaseComponentsHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Prepares global variables for all templates.
   *
   * @param array $variables
   *   An associative array containing variables for the template.
   */
  #[Hook('preprocess')]
  public function preprocess(array &$variables): void {
    // Get the default active theme.
    $variables['active_theme'] = $this->configFactory->get('system.theme')->get('default');
  }

}
