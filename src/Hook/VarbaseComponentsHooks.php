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
   * Removes orphaned Canvas component configs when a theme is uninstalled.
   *
   * Canvas creates an `sdc.<theme>.<name>` component config entity for every
   * SDC the theme provides, but these are not theme-dependent config, so core
   * does not delete them when the theme is uninstalled (unlike page_region
   * configs). They linger as orphans (e.g. `canvas.component.sdc.vartheme_bs5.*`
   * after switching away from and uninstalling Vartheme BS5). Drupal forbids
   * uninstalling the default theme, and the theme-switch subscriber has already
   * migrated content/config to the new theme, so the uninstalled theme's
   * components are unreferenced and safe to remove.
   *
   * @param array $themes
   *   The uninstalled theme machine names.
   */
  #[Hook('themes_uninstalled')]
  public function themesUninstalled(array $themes): void {
    if (!\Drupal::moduleHandler()->moduleExists('canvas')) {
      return;
    }
    $entity_type_manager = \Drupal::entityTypeManager();
    if (!$entity_type_manager->hasDefinition('component')) {
      return;
    }

    try {
      $storage = $entity_type_manager->getStorage('component');
      foreach ($themes as $theme) {
        $ids = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('id', 'sdc.' . $theme . '.', 'STARTS_WITH')
          ->execute();
        if (!empty($ids)) {
          $storage->delete($storage->loadMultiple($ids));
          \Drupal::logger('varbase_components')->info(
            'Removed @count orphaned Canvas component config(s) for uninstalled theme @theme.',
            ['@count' => count($ids), '@theme' => $theme]
          );
        }
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('varbase_components')->error('Orphaned Canvas component cleanup failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
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
