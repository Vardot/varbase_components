<?php

namespace Drupal\varbase_components\Commands;

use Drupal\varbase_components\EventSubscriber\ActiveThemeChangeSubscriber;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Varbase Components theme switching utilities.
 *
 * These commands mirror and expose the automated logic in
 * ActiveThemeChangeSubscriber, allowing manual runs, diagnostics, and
 * re-runs after edge-case failures.
 */
class VarbaseComponentsCommands extends DrushCommands {

  /**
   * Constructs VarbaseComponentsCommands.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    protected MessengerInterface $messenger,
    protected ConfigFactoryInterface $configFactory,
    protected ThemeHandlerInterface $themeHandler,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
    parent::__construct();
  }

  /**
   * Switch all component references from an old theme to a new theme.
   *
   * Updates all config entities and content entity field data that reference
   * the old theme's SDC component IDs (e.g. sdc.vartheme_bs5.*) to use the
   * new theme's IDs (e.g. sdc.mytheme.*).  Also fixes stale component version
   * hashes so the new theme's active versions are used.
   *
   * @param string $old_theme
   *   The old theme machine name (e.g. vartheme_bs5).
   * @param string $new_theme
   *   The new theme machine name (e.g. mytheme).
   * @param array $options
   *   The command options.
   *
   * @command varbase-components:switch-theme
   * @aliases vc-switch,vcs
   *
   * @usage varbase-components:switch-theme vartheme_bs5 mytheme
   *   Switch all component references from vartheme_bs5 to mytheme.
   *
   * @option dry-run   Show what would change without saving.
   *
   * @bootstrap full
   */
  public function switchTheme(string $old_theme, string $new_theme, array $options = ['dry-run' => FALSE]): void {
    if (!$this->themeHandler->themeExists($old_theme)) {
      $this->logger()->warning(dt('Theme @theme is not installed; continuing anyway.', ['@theme' => $old_theme]));
    }
    if (!$this->themeHandler->themeExists($new_theme)) {
      $this->logger()->error(dt('Theme @theme is not installed. Aborting.', ['@theme' => $new_theme]));
      return;
    }

    $dry_run = $options['dry-run'];
    if ($dry_run) {
      $this->output()->writeln('<comment>Dry-run mode: no changes will be saved.</comment>');
    }

    // Use the subscriber's logic via reflection to avoid code duplication.
    $subscriber = $this->buildSubscriber();

    $this->output()->writeln(dt('Switching config entities from @old to @new …', [
      '@old' => $old_theme,
      '@new' => $new_theme,
    ]));
    if (!$dry_run) {
      $this->callMethod($subscriber, 'replaceAndSaveThemeInActiveConfigs', [$old_theme, $new_theme]);
    }
    else {
      $count = $this->dryRunConfigScan($old_theme);
      $this->output()->writeln(dt('  Would update @count config(s) containing "@old".', [
        '@count' => $count,
        '@old' => $old_theme,
      ]));
    }

    $this->output()->writeln(dt('Switching component_tree field data in content entities …'));
    if (!$dry_run) {
      $this->callMethod($subscriber, 'replaceThemeInContentEntityComponentFields', [$old_theme, $new_theme]);
    }
    else {
      $count = $this->dryRunEntityScan($old_theme);
      $this->output()->writeln(dt('  Would update @count row(s) in component_tree field tables.', ['@count' => $count]));
    }

    $this->output()->writeln(dt('Replacing theme filesystem paths in text fields …'));
    if (!$dry_run) {
      $this->callMethod($subscriber, 'replaceThemePathsInTextFields', [$old_theme, $new_theme]);
    }

    $this->output()->writeln(dt('Fixing stale component version hashes for @new …', ['@new' => $new_theme]));
    if (!$dry_run) {
      // Reset entity static cache so version lookups see the latest config.
      $this->entityTypeManager->getStorage('component')->resetCache();
      $this->callMethod($subscriber, 'fixComponentVersionsInConfigs', [$new_theme]);
    }

    $this->output()->writeln('<info>Done.</info>');
  }

  /**
   * Fix stale component version hashes in all configs for a theme.
   *
   * After a theme switch the component_version hashes stored in config
   * entities (content templates, entity view displays, canvas pages) may
   * reference versions that only exist in the old theme.  This command
   * replaces any such invalid hashes with the active version of the
   * corresponding new-theme component.
   *
   * @param string $theme
   *   The theme machine name whose component versions should be fixed
   *   (e.g. mytheme).
   *
   * @command varbase-components:fix-versions
   * @aliases vc-fix-versions,vcfv
   *
   * @usage varbase-components:fix-versions mytheme
   *   Fix all stale component version hashes for the mytheme theme.
   *
   * @bootstrap full
   */
  public function fixVersions(string $theme): void {
    if (!$this->themeHandler->themeExists($theme)) {
      $this->logger()->warning(dt('Theme @theme is not installed; attempting fix anyway.', ['@theme' => $theme]));
    }

    // Reset entity static cache so version lookups are fresh.
    $this->entityTypeManager->getStorage('component')->resetCache();

    $subscriber = $this->buildSubscriber();
    $this->output()->writeln(dt('Fixing stale component version hashes for @theme …', ['@theme' => $theme]));
    // Pass TRUE to scan every component reference (block.*, js.*, etc.), not
    // only SDC components for this theme — drush fix-versions is the general
    // "repair everything" entry point, so stale block versions imported by
    // recipes must be rewritten too.
    $this->callMethod($subscriber, 'fixComponentVersionsInConfigs', [$theme, TRUE]);
    $this->output()->writeln('<info>Done.</info>');
  }

  /**
   * Scan configs and entities for references to a theme name.
   *
   * Useful for auditing what still references an old theme before or after
   * a switch, or to verify the switch was complete.
   *
   * @param string $theme
   *   The theme machine name to search for (e.g. vartheme_bs5).
   *
   * @command varbase-components:scan-refs
   * @aliases vc-scan,vcscan
   * @field-labels
   *   source: Source
   *   location: Location
   *   note: Note
   *
   * @usage varbase-components:scan-refs vartheme_bs5
   *   List all configs and entity rows that still reference vartheme_bs5.
   *
   * @bootstrap full
   */
  public function scanRefs(string $theme): RowsOfFields {
    $rows = [];

    // --- Config scan --------------------------------------------------------
    $all_configs = $this->configFactory->listAll();
    foreach ($all_configs as $config_name) {
      $raw = serialize($this->configFactory->get($config_name)->getRawData());
      if (strpos($raw, $theme) !== FALSE) {
        $note = str_starts_with($config_name, 'canvas.component.sdc.' . $theme)
          ? '(component definition — expected)'
          : '';
        $rows[] = ['source' => 'config', 'location' => $config_name, 'note' => $note];
      }
    }

    // --- Content entity scan ------------------------------------------------
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      try {
        $field_defs = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      }
      catch (\Exception $e) {
        continue;
      }
      foreach ($field_defs as $field_name => $field_storage) {
        if ($field_storage->getType() !== 'component_tree') {
          continue;
        }
        try {
          $storage = $this->entityTypeManager->getStorage($entity_type_id);
        }
        catch (\Exception $e) {
          continue;
        }
        if (!$storage instanceof SqlEntityStorageInterface) {
          continue;
        }
        $table_mapping = $storage->getTableMapping();
        $tables = $table_mapping->getAllFieldTableNames($field_name);
        $column_name = $table_mapping->getFieldColumnName($field_storage, 'component_id');
        foreach ($tables as $table) {
          try {
            $count = $this->database->select($table, 't')
              ->condition($column_name, 'sdc.' . $theme . '.%', 'LIKE')
              ->countQuery()->execute()->fetchField();
            if ($count > 0) {
              $rows[] = [
                'source' => 'entity_field',
                'location' => "$table.$column_name",
                'note' => "$count row(s) in $entity_type_id.$field_name",
              ];
            }
          }
          catch (\Exception $e) {
            // Table may not exist yet.
          }
        }
      }
    }

    return new RowsOfFields($rows);
  }

  // ---------------------------------------------------------------------------
  // Internal helpers
  // ---------------------------------------------------------------------------

  /**
   * Builds a subscriber instance wired to all required services.
   *
   * @return \Drupal\varbase_components\EventSubscriber\ActiveThemeChangeSubscriber
   *   A fully-constructed subscriber.
   */
  protected function buildSubscriber(): ActiveThemeChangeSubscriber {
    return new ActiveThemeChangeSubscriber(
      $this->messenger,
      $this->configFactory,
      $this->themeHandler,
      $this->loggerFactory,
      $this->database,
      $this->entityTypeManager,
      $this->entityFieldManager,
    );
  }

  /**
   * Calls a protected method on the subscriber via reflection.
   *
   * @param object $object
   *   The object to call the method on.
   * @param string $method
   *   The method name.
   * @param array $args
   *   Method arguments.
   */
  protected function callMethod(object $object, string $method, array $args = []): void {
    $ref = new \ReflectionMethod($object, $method);
    $ref->setAccessible(TRUE);
    $ref->invokeArgs($object, $args);
  }

  /**
   * Counts configs referencing the given theme name (dry-run helper).
   *
   * @param string $theme
   *   The theme machine name.
   *
   * @return int
   *   Number of configs that contain the theme name.
   */
  protected function dryRunConfigScan(string $theme): int {
    $count = 0;
    foreach ($this->configFactory->listAll() as $name) {
      $raw = serialize($this->configFactory->get($name)->getRawData());
      if (strpos($raw, $theme) !== FALSE) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Counts entity field rows referencing the given theme (dry-run helper).
   *
   * @param string $theme
   *   The theme machine name.
   *
   * @return int
   *   Total rows found.
   */
  protected function dryRunEntityScan(string $theme): int {
    $total = 0;
    $prefix = 'sdc.' . $theme . '.';
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      try {
        $field_defs = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      }
      catch (\Exception $e) {
        continue;
      }
      foreach ($field_defs as $field_name => $field_storage) {
        if ($field_storage->getType() !== 'component_tree') {
          continue;
        }
        try {
          $storage = $this->entityTypeManager->getStorage($entity_type_id);
          if (!$storage instanceof SqlEntityStorageInterface) {
            continue;
          }
          $table_mapping = $storage->getTableMapping();
          $column_name = $table_mapping->getFieldColumnName($field_storage, 'component_id');
          foreach ($table_mapping->getAllFieldTableNames($field_name) as $table) {
            try {
              $count = $this->database->select($table, 't')
                ->condition($column_name, $prefix . '%', 'LIKE')
                ->countQuery()->execute()->fetchField();
              $total += (int) $count;
            }
            catch (\Exception $e) {
              // Table may not exist.
            }
          }
        }
        catch (\Exception $e) {
          continue;
        }
      }
    }
    return $total;
  }

}
