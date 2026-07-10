<?php

namespace Drupal\varbase_components\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for varbase_components.
 */
class VarbaseComponentsHooks {

  /**
   * Gets the Drupal Canvas components kept out of the component library.
   *
   * The list ships in the `hidden_canvas_components` setting of
   * `varbase_components.settings` (no settings UI): administrative and
   * duplicate components that content editors and site builders should not
   * place on pages — dashboard feeds, Project Browser blocks, AI
   * administration blocks, and the Webshare Share block and component that
   * duplicate the theme Share component. Drupal Canvas only lets the source
   * discovery compute the initial status of a Component config entity and
   * provides no alter hook for it, so the entity presave hook is the
   * supported way to set the initial status. Components on this list are
   * created disabled no matter when the module or recipe providing them gets
   * enabled. Only the initial status is enforced: a site builder can still
   * re-enable any of them manually.
   *
   * @return string[]
   *   The hidden Component config entity IDs.
   *
   * @see \Drupal\canvas\ComponentSource\ComponentSourceManager::generateComponents()
   * @see \Drupal\canvas\ComponentSource\ComponentCandidatesDiscoveryInterface::computeInitialComponentStatus()
   */
  public static function getHiddenCanvasComponents(): array {
    return \Drupal::config('varbase_components.settings')->get('hidden_canvas_components') ?? [];
  }

  /**
   * Creates hidden-list Drupal Canvas components disabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $component
   *   The Drupal Canvas Component config entity being saved.
   *
   * @see self::getHiddenCanvasComponents()
   */
  #[Hook('component_presave')]
  public function componentPresave(EntityInterface $component): void {
    if (!$component->isNew() || !$component->status()) {
      return;
    }
    if (\in_array($component->id(), self::getHiddenCanvasComponents(), TRUE)) {
      $component->disable();
    }
  }

  /**
   * Disables the hidden-list Drupal Canvas components that already exist.
   *
   * Covers components created before this module got installed; components
   * created afterwards are created disabled by componentPresave().
   *
   * @see self::getHiddenCanvasComponents()
   */
  public static function disableHiddenComponents(): void {
    $entity_type_manager = \Drupal::entityTypeManager();
    if (!$entity_type_manager->hasDefinition('component')) {
      return;
    }
    $hidden = self::getHiddenCanvasComponents();
    if (empty($hidden)) {
      return;
    }
    $storage = $entity_type_manager->getStorage('component');
    foreach ($storage->loadMultiple($hidden) as $component) {
      if ($component->status()) {
        $component->disable()->save();
      }
    }
  }

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
