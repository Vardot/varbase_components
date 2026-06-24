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

  /**
   * Heals stale component versions after a cache rebuild.
   *
   * Canvas regenerates SDC component versions during rebuild; varbase_components
   * is weighted to run after Canvas (see varbase_components_install()), so the
   * components are already current when this heals any config/content that still
   * pins an old version hash.
   */
  #[Hook('rebuild')]
  public function rebuild(): void {
    $this->healComponentVersions();
  }

  /**
   * Heals stale component versions after modules are installed.
   *
   * Covers a fresh Varbase build, where demo content and content templates are
   * imported with version hashes that may not match the freshly generated
   * components.
   *
   * @param array $modules
   *   The installed module machine names.
   * @param bool $is_syncing
   *   Whether the install is part of a configuration import.
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    if ($is_syncing) {
      return;
    }
    $this->healComponentVersions();
  }

  /**
   * Heals stale component versions after a theme is installed.
   *
   * Covers generating and enabling a sub-theme, whose components get fresh
   * version hashes that authored/migrated content may not yet reference.
   *
   * @param array $themes
   *   The installed theme machine names.
   */
  #[Hook('themes_installed')]
  public function themesInstalled(array $themes): void {
    $this->healComponentVersions();
  }

  /**
   * Runs the idempotent component-version heal when Canvas is installed.
   */
  protected function healComponentVersions(): void {
    if (!\Drupal::moduleHandler()->moduleExists('canvas')) {
      return;
    }
    try {
      /** @var \Drupal\varbase_components\EventSubscriber\ActiveThemeChangeSubscriber $subscriber */
      $subscriber = \Drupal::service('varbase_components.active_theme_change_subscriber');
      $subscriber->heal();
    }
    catch (\Throwable $e) {
      \Drupal::logger('varbase_components')->error('Automatic component version heal failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
