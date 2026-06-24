<?php

namespace Drupal\varbase_components\EventSubscriber;

use Drupal\canvas\Entity\VersionedConfigEntityInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles theme changes and updates configurations automatically.
 */
class ActiveThemeChangeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs an ActiveThemeChangeSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    MessengerInterface $messenger,
    ConfigFactoryInterface $config_factory,
    ThemeHandlerInterface $theme_handler,
    LoggerChannelFactoryInterface $logger_factory,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
  ) {
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->themeHandler = $theme_handler;
    $this->loggerFactory = $logger_factory;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::SAVE => 'onActiveThemeChange',
    ];
  }

  /**
   * Responds to theme change events.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onActiveThemeChange(ConfigCrudEvent $event): void {
    $config = $event->getConfig();

    if ($config->getName() !== 'system.theme') {
      return;
    }

    $original_data = $config->getOriginal();
    if (!isset($original_data['default'])) {
      return;
    }

    $old_theme = $original_data['default'];
    $new_theme = $config->get('default');

    if ($old_theme === $new_theme) {
      return;
    }

    if (!$this->themeHasFlag($old_theme, 'auto_switch_components') ||
        !$this->themeHasFlag($new_theme, 'auto_switch_components')) {
      return;
    }

    $this->replaceAndSaveThemeInActiveConfigs($old_theme, $new_theme);
    $this->migratePageRegions($old_theme, $new_theme);
    $this->replaceThemeInContentEntityComponentFields($old_theme, $new_theme);
    $this->replaceThemePathsInTextFields($old_theme, $new_theme);
    $this->fixComponentVersionsInConfigs($new_theme);
    $this->fixComponentVersionsInContentEntities($new_theme);

    $this->messenger->addStatus($this->t('Theme changed from %old to %new. Updating active configurations...', [
      '%old' => $old_theme,
      '%new' => $new_theme,
    ]));
  }

  /**
   * Heals stale component version hashes across all configs and content.
   *
   * Canvas regenerates its SDC component config entities on cache rebuild,
   * module install and theme install: createVersion() records the new active
   * version and deleteVersionIfExists() drops the previous one. Any config or
   * content that still pins the previous version hash is therefore left stale.
   * Page render tolerates this (the component falls back to its active version),
   * but the Canvas editor layout/auto-save API surfaces the mismatch and the
   * "Component version … not found, falling back to active version" warning is
   * logged on every render.
   *
   * This performs the same full-scope repair as the
   * varbase-components:fix-versions Drush command, and is invoked from the
   * rebuild / modules_installed / themes_installed hooks (which run after Canvas
   * has regenerated the components) so a fresh build, a module install or a
   * theme enable self-heal with no manual command. It is idempotent and a no-op
   * when nothing is stale, and bails out when Canvas is not installed.
   *
   * @param string|null $theme
   *   Reserved for future per-theme scoping; healing is full-scope.
   */
  public function heal(?string $theme = NULL): void {
    if (!$this->entityTypeManager->hasDefinition('component')) {
      return;
    }
    // Ensure version lookups reflect the freshly regenerated components.
    $this->entityTypeManager->getStorage('component')->resetCache();
    // TRUE = validate every component reference, not only one theme's SDC.
    $this->fixComponentVersionsInConfigs($theme ?? '', TRUE);
    $this->fixComponentVersionsInContentEntities(NULL);
  }

  /**
   * Replaces theme name in active config and saves back to database.
   *
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   */
  protected function replaceAndSaveThemeInActiveConfigs(string $old_theme, string $new_theme): void {
    $all_configs = $this->configFactory->listAll();
    $old_theme_escaped = preg_quote($old_theme, '/');

    // Process entity view display configs first.
    $this->processEntityViewDisplayConfigs($all_configs, $old_theme_escaped, $old_theme, $new_theme);

    // Process all other configs.
    $this->processAllConfigs($all_configs, $old_theme_escaped, $old_theme, $new_theme);
  }

  /**
   * Clones Canvas page_region entities from the old theme to the new theme.
   *
   * Canvas stores theme-specific page regions (the header/footer built in
   * Canvas) as config entities named `canvas.page_region.<theme>.<region>`.
   * The theme name is baked into the config ID, the `theme` property, and the
   * theme dependency, so it cannot be migrated by the in-place YAML string
   * replacement that handles component IDs. Without this step the new theme has
   * no page regions and, once the old theme is uninstalled, its page_region
   * configs are deleted — leaving the site with no Canvas header/footer.
   *
   * For every page region owned by the old theme this creates an equivalent
   * region for the new theme (when one does not already exist). The source
   * region's component_tree has already had its component IDs migrated by
   * replaceAndSaveThemeInActiveConfigs(), so the clone inherits the new theme's
   * SDC component references.
   *
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   */
  protected function migratePageRegions(string $old_theme, string $new_theme): void {
    // The page_region entity type ships with Canvas; bail out gracefully if it
    // is not available (Canvas not installed).
    if (!$this->entityTypeManager->hasDefinition('page_region')) {
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('page_region');
    }
    catch (\Exception $e) {
      return;
    }

    $old_prefix = 'canvas.page_region.' . $old_theme . '.';
    foreach ($this->configFactory->listAll($old_prefix) as $config_name) {
      $region = substr($config_name, strlen($old_prefix));
      $new_id = $new_theme . '.' . $region;

      // Don't clobber a region the new theme already provides.
      if ($storage->load($new_id)) {
        continue;
      }

      $source = $storage->load($old_theme . '.' . $region);
      if (!$source) {
        continue;
      }

      try {
        $data = $source->toArray();
        $data['id'] = $new_id;
        $data['theme'] = $new_theme;
        $data['region'] = $region;
        // Let the entity API assign a fresh UUID and recalculate dependencies.
        unset($data['uuid'], $data['_core']);

        $storage->create($data)->save();

        $this->loggerFactory->get('varbase_components')->info(
          'Migrated Canvas page region "@region" from theme @old to @new.',
          ['@region' => $region, '@old' => $old_theme, '@new' => $new_theme]
        );
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('varbase_components')->error(
          'Failed to migrate Canvas page region "@region" to theme @new: @message',
          ['@region' => $region, '@new' => $new_theme, '@message' => $e->getMessage()]
        );
      }
    }
  }

  /**
   * Replaces the old theme name in component_tree field tables for all content entities.
   *
   * Discovers all content entity types that have fields of type 'component_tree'
   * and updates the component_id column in both the field data table and the
   * revision table, replacing occurrences of the old theme name with the new
   * theme name in SDC component IDs (e.g. sdc.vartheme_bs5.button ->
   * sdc.mytheme.button).
   *
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   */
  protected function replaceThemeInContentEntityComponentFields(string $old_theme, string $new_theme): void {
    $old_prefix = 'sdc.' . $old_theme . '.';
    $new_prefix = 'sdc.' . $new_theme . '.';

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // Only process content entity types.
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }

      try {
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      }
      catch (\Exception $e) {
        continue;
      }

      foreach ($field_storage_definitions as $field_name => $field_storage) {
        if ($field_storage->getType() !== 'component_tree') {
          continue;
        }

        // Get the storage handler for the entity type.
        try {
          $storage = $this->entityTypeManager->getStorage($entity_type_id);
        }
        catch (\Exception $e) {
          continue;
        }

        // Only SQL-backed storage has table mappings.
        if (!$storage instanceof SqlEntityStorageInterface) {
          continue;
        }

        $table_mapping = $storage->getTableMapping();
        $tables = $table_mapping->getAllFieldTableNames($field_name);

        $column_name = $table_mapping->getFieldColumnName($field_storage, 'component_id');

        foreach ($tables as $table) {
          $this->updateComponentIdColumnInTable($table, $column_name, $old_prefix, $new_prefix, $old_theme, $entity_type_id, $field_name);
        }
      }
    }
  }

  /**
   * Replaces old theme filesystem paths in text/body fields across all content entities.
   *
   * Scans all text-type fields (text, text_long, text_with_summary) for
   * hardcoded filesystem paths like "themes/contrib/vartheme_bs5/" and
   * replaces them with the new theme's actual path (e.g.
   * "themes/custom/mytheme/").
   *
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   */
  protected function replaceThemePathsInTextFields(string $old_theme, string $new_theme): void {
    // Determine the new theme's actual filesystem path.
    if (!$this->themeHandler->themeExists($new_theme)) {
      return;
    }
    $new_theme_info = $this->themeHandler->getTheme($new_theme);
    if (!$new_theme_info) {
      return;
    }
    $new_theme_path = $new_theme_info->getPath();

    // Build search/replace pairs for both slash-prefixed and non-prefixed paths.
    $old_patterns = [
      'themes/contrib/' . $old_theme . '/',
      'themes/custom/' . $old_theme . '/',
    ];
    $new_replacement = $new_theme_path . '/';

    $text_field_types = ['text', 'text_long', 'text_with_summary'];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }

      try {
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      }
      catch (\Exception $e) {
        continue;
      }

      foreach ($field_storage_definitions as $field_name => $field_storage) {
        if (!in_array($field_storage->getType(), $text_field_types, TRUE)) {
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

        // Get all column names for this field (value, summary, format, etc.).
        $columns = $table_mapping->getColumnNames($field_name);
        $value_columns = array_filter($columns, fn($col) => str_contains($col, 'value') || str_contains($col, 'summary'));

        foreach ($tables as $table) {
          foreach ($value_columns as $column_name) {
            $this->replaceThemePathInTextColumn($table, $column_name, $old_patterns, $new_replacement, $entity_type_id, $field_name);
          }
        }
      }
    }
  }

  /**
   * Replaces theme path references in a single text column.
   *
   * @param string $table
   *   The database table name.
   * @param string $column_name
   *   The column name.
   * @param array $old_patterns
   *   Array of old path patterns to search for.
   * @param string $new_replacement
   *   The new path to replace with.
   * @param string $entity_type_id
   *   Entity type ID (for logging).
   * @param string $field_name
   *   Field name (for logging).
   */
  protected function replaceThemePathInTextColumn(
    string $table,
    string $column_name,
    array $old_patterns,
    string $new_replacement,
    string $entity_type_id,
    string $field_name,
  ): void {
    try {
      $schema = $this->database->schema();
      if (!$schema->tableExists($table) || !$schema->fieldExists($table, $column_name)) {
        return;
      }

      foreach ($old_patterns as $old_pattern) {
        // Check if any rows need updating before attempting the update.
        $count = $this->database->select($table, 't')
          ->condition($column_name, '%' . $this->database->escapeLike($old_pattern) . '%', 'LIKE')
          ->countQuery()
          ->execute()
          ->fetchField();

        if (!$count) {
          continue;
        }

        // Use a direct UPDATE with an expression to avoid relying on a
        // specific primary-key column name (which differs per entity type).
        // REPLACE() is supported on all Drupal-compatible databases.
        $updated = $this->database->update($table)
          ->expression($column_name, "REPLACE($column_name, :old, :new)", [
            ':old' => $old_pattern,
            ':new' => $new_replacement,
          ])
          ->condition($column_name, '%' . $this->database->escapeLike($old_pattern) . '%', 'LIKE')
          ->execute();

        if ($updated > 0) {
          $this->loggerFactory->get('varbase_components')->info(
            'Replaced theme path "@old" → "@new" in @table.@column (@count row(s); @entity_type.@field).',
            [
              '@old' => $old_pattern,
              '@new' => $new_replacement,
              '@table' => $table,
              '@column' => $column_name,
              '@count' => $updated,
              '@entity_type' => $entity_type_id,
              '@field' => $field_name,
            ]
          );
        }
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('varbase_components')->error(
        'Failed to replace theme paths in @table.@column: @message',
        ['@table' => $table, '@column' => $column_name, '@message' => $e->getMessage()]
      );
    }
  }

  /**
   * Updates the component_id column in a single table.
   *
   * Selects rows where the component_id starts with the old SDC prefix, then
   * updates them one by one using Drupal's database abstraction layer (no raw
   * SQL statements).
   *
   * @param string $table
   *   The database table name.
   * @param string $column_name
   *   The column name storing the component ID.
   * @param string $old_prefix
   *   The old SDC prefix, e.g. 'sdc.vartheme_bs5.'.
   * @param string $new_prefix
   *   The new SDC prefix, e.g. 'sdc.mytheme.'.
   * @param string $old_theme
   *   The old theme machine name (used for logging).
   * @param string $entity_type_id
   *   The entity type ID (used for logging).
   * @param string $field_name
   *   The field name (used for logging).
   */
  protected function updateComponentIdColumnInTable(
    string $table,
    string $column_name,
    string $old_prefix,
    string $new_prefix,
    string $old_theme,
    string $entity_type_id,
    string $field_name,
  ): void {
    try {
      // Select rows that need updating.
      $results = $this->database->select($table, 't')
        ->fields('t', [$column_name])
        ->condition($column_name, $old_prefix . '%', 'LIKE')
        ->distinct()
        ->execute()
        ->fetchCol();

      if (empty($results)) {
        return;
      }

      foreach ($results as $old_component_id) {
        if (!str_starts_with($old_component_id, $old_prefix)) {
          continue;
        }

        $new_component_id = $new_prefix . substr($old_component_id, strlen($old_prefix));

        $this->database->update($table)
          ->fields([$column_name => $new_component_id])
          ->condition($column_name, $old_component_id)
          ->execute();
      }

      $this->loggerFactory->get('varbase_components')->info(
        'Updated component IDs in table @table (field @field on @entity_type): replaced "sdc.@old.*" with "sdc.@new.*".',
        [
          '@table' => $table,
          '@field' => $field_name,
          '@entity_type' => $entity_type_id,
          '@old' => $old_theme,
          '@new' => $new_prefix,
        ]
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('varbase_components')->error(
        'Failed to update component IDs in table @table: @message',
        ['@table' => $table, '@message' => $e->getMessage()]
      );
    }
  }

  /**
   * Fixes stale component version hashes in content entity component trees.
   *
   * Mirrors fixComponentVersionsInConfigs() but for content entities: any
   * stored component_version in a `component_tree` field that is neither the
   * component's active version nor one of its known historical versions is
   * rewritten to the active version. This is required after a component's
   * schema/template changes its version hash (e.g. editing an SDC component)
   * — config templates are repaired by fixComponentVersionsInConfigs(), but
   * demo/authored content (canvas_page, nodes, …) pins the old hash and would
   * otherwise make Canvas's editor layout API reject the page with
   * "requested version … is not available".
   *
   * @param string|null $theme
   *   When given, only components whose ID starts with `sdc.<theme>.` are
   *   touched. NULL repairs every component reference.
   */
  protected function fixComponentVersionsInContentEntities(?string $theme = NULL): void {
    $component_storage = $this->entityTypeManager->getStorage('component');
    $prefix = $theme !== NULL ? 'sdc.' . $theme . '.' : '';

    // Cache of active version + known versions, keyed by component ID.
    $version_cache = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }

      try {
        $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      }
      catch (\Exception $e) {
        continue;
      }

      foreach ($field_storage_definitions as $field_name => $field_storage) {
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
        $id_column = $table_mapping->getFieldColumnName($field_storage, 'component_id');
        $version_column = $table_mapping->getFieldColumnName($field_storage, 'component_version');

        foreach ($tables as $table) {
          $this->fixVersionColumnInTable($table, $id_column, $version_column, $prefix, $component_storage, $version_cache, $entity_type_id, $field_name);
        }
      }
    }
  }

  /**
   * Rewrites stale component_version values to the active version in one table.
   *
   * @param string $table
   *   The field data/revision table name.
   * @param string $id_column
   *   The component_id column name.
   * @param string $version_column
   *   The component_version column name.
   * @param string $prefix
   *   Optional `sdc.<theme>.` prefix limiting which rows are considered.
   * @param \Drupal\Core\Entity\EntityStorageInterface $component_storage
   *   The Canvas component entity storage.
   * @param array &$version_cache
   *   Cache of active/known versions keyed by component ID.
   * @param string $entity_type_id
   *   Entity type ID (for logging).
   * @param string $field_name
   *   Field name (for logging).
   */
  protected function fixVersionColumnInTable(
    string $table,
    string $id_column,
    string $version_column,
    string $prefix,
    $component_storage,
    array &$version_cache,
    string $entity_type_id,
    string $field_name,
  ): void {
    try {
      $schema = $this->database->schema();
      if (!$schema->tableExists($table)
        || !$schema->fieldExists($table, $id_column)
        || !$schema->fieldExists($table, $version_column)) {
        return;
      }

      $query = $this->database->select($table, 't')
        ->fields('t', [$id_column, $version_column])
        ->distinct();
      if ($prefix !== '') {
        $query->condition($id_column, $this->database->escapeLike($prefix) . '%', 'LIKE');
      }
      $pairs = $query->execute()->fetchAll();

      foreach ($pairs as $pair) {
        $component_id = $pair->{$id_column};
        $stored_version = $pair->{$version_column};
        if ($component_id === NULL || $stored_version === NULL) {
          continue;
        }

        if (!array_key_exists($component_id, $version_cache)) {
          $comp = $component_storage->load($component_id);
          $version_cache[$component_id] = $comp instanceof VersionedConfigEntityInterface
            ? ['active' => $comp->getActiveVersion(), 'versions' => $comp->getVersions()]
            : NULL;
        }

        $info = $version_cache[$component_id];
        if (empty($info)) {
          continue;
        }

        if ($stored_version === $info['active'] || in_array($stored_version, $info['versions'], TRUE)) {
          continue;
        }

        $updated = $this->database->update($table)
          ->fields([$version_column => $info['active']])
          ->condition($id_column, $component_id)
          ->condition($version_column, $stored_version)
          ->execute();

        if ($updated > 0) {
          $this->loggerFactory->get('varbase_components')->info(
            'Fixed stale component version for @component in @table (@count row(s); @entity_type.@field): @old → @new.',
            [
              '@component' => $component_id,
              '@table' => $table,
              '@count' => $updated,
              '@entity_type' => $entity_type_id,
              '@field' => $field_name,
              '@old' => $stored_version,
              '@new' => $info['active'],
            ]
          );
        }
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('varbase_components')->error(
        'Failed to fix component versions in @table.@column: @message',
        ['@table' => $table, '@column' => $version_column, '@message' => $e->getMessage()]
      );
    }
  }

  /**
   * Fixes component version hashes in all configs after a theme switch.
   *
   * When the theme changes, component IDs in config entities (content
   * templates, entity view displays, etc.) are updated from the old to the
   * new theme. However, the stored component_version hashes may reference
   * version snapshots that only exist in the old theme's component config.
   * This method finds any such stale version and replaces it with the active
   * version of the corresponding new-theme component.
   *
   * @param string $new_theme
   *   The new theme machine name.
   * @param bool $all_components
   *   When TRUE, validate every component reference, not only the new theme's.
   */
  protected function fixComponentVersionsInConfigs(string $new_theme, bool $all_components = FALSE): void {
    // When $all_components is TRUE we pass an empty prefix so every component
    // reference (SDC, block, js, etc.) is validated — used by the drush
    // fix-versions command. The theme-switch subscriber keeps the narrow
    // scope by leaving $all_components at its default.
    $new_prefix = $all_components ? '' : 'sdc.' . $new_theme . '.';
    $component_storage = $this->entityTypeManager->getStorage('component');

    // Cache active versions to avoid repeated entity loads.
    $active_versions = [];

    $all_configs = $this->configFactory->listAll();
    foreach ($all_configs as $config_name) {
      $config = $this->configFactory->getEditable($config_name);
      $data = $config->getRawData();
      if (empty($data)) {
        continue;
      }

      // Convert to YAML, look for component_version entries paired with
      // SDC component IDs for the new theme.
      $changed = $this->fixComponentVersionsInArray($data, $new_prefix, $component_storage, $active_versions);

      if ($changed) {
        try {
          $config->setData($data)->save();
          $this->loggerFactory->get('varbase_components')->info(
            'Fixed component version hashes in config: @config',
            ['@config' => $config_name]
          );
        }
        catch (\Exception $e) {
          $this->loggerFactory->get('varbase_components')->error(
            'Failed to fix component version hashes in config @config: @message',
            ['@config' => $config_name, '@message' => $e->getMessage()]
          );
        }
      }
    }
  }

  /**
   * Recursively fixes component version hashes in a config data array.
   *
   * Looks for arrays that have both 'component_id' and 'component_version'
   * keys where the component_id starts with the new theme's SDC prefix, then
   * verifies the version is valid and replaces it with the active version if
   * it is not.
   *
   * @param array &$data
   *   The config data array to scan and update (passed by reference).
   * @param string $new_prefix
   *   The new SDC component ID prefix, e.g. 'sdc.mytheme.'.
   * @param \Drupal\Core\Entity\EntityStorageInterface $component_storage
   *   The Canvas component entity storage.
   * @param array &$active_versions
   *   Cache of already-resolved active version hashes, keyed by component ID.
   *
   * @return bool
   *   TRUE if any version was changed.
   */
  protected function fixComponentVersionsInArray(array &$data, string $new_prefix, $component_storage, array &$active_versions): bool {
    $changed = FALSE;

    // If this array represents a component tree item with component_id and
    // component_version, validate and possibly fix the version.
    //
    // An empty $new_prefix means "check every component reference" (used by
    // the drush fix-versions command so stale hashes on non-SDC components
    // like `block.*` or `js.*` also get rewritten). A non-empty prefix limits
    // the scan to a single theme's SDC components (used by the theme-switch
    // subscriber to avoid touching unrelated trees).
    if (isset($data['component_id'], $data['component_version'])
      && is_string($data['component_id'])
      && ($new_prefix === '' || str_starts_with($data['component_id'], $new_prefix))
    ) {
      $comp_id = $data['component_id'];
      $stored_version = $data['component_version'];

      // Get or cache the active version and known version list for the
      // component. We resolve the full version list up-front so we can check
      // validity WITHOUT calling $comp->loadVersion() — that call logs a noisy
      // canvas warning AND does not throw when the version is missing, which
      // would otherwise defeat this whole method's purpose.
      if (!isset($active_versions[$comp_id])) {
        $comp = $component_storage->load($comp_id);
        if ($comp instanceof VersionedConfigEntityInterface) {
          $active_versions[$comp_id] = [
            'active' => $comp->getActiveVersion(),
            'versions' => $comp->getVersions(),
          ];
        }
        else {
          $active_versions[$comp_id] = NULL;
        }
      }

      if (!empty($active_versions[$comp_id])) {
        $active_version = $active_versions[$comp_id]['active'];
        $known_versions = $active_versions[$comp_id]['versions'];

        // A stored version is valid when it is either the active version or
        // one of the historically tracked versions. Anything else is stale
        // (typically left over from a theme switch or a component rebuild)
        // and must be rewritten to the current active version to avoid the
        // "Component version … not found … falling back to active version"
        // warning from Canvas at render time.
        if ($stored_version !== $active_version
          && !in_array($stored_version, $known_versions, TRUE)
        ) {
          $data['component_version'] = $active_version;
          $changed = TRUE;
        }
      }
    }

    // Recurse into nested arrays.
    foreach ($data as &$value) {
      if (is_array($value)) {
        if ($this->fixComponentVersionsInArray($value, $new_prefix, $component_storage, $active_versions)) {
          $changed = TRUE;
        }
      }
    }

    return $changed;
  }

  /**
   * Processes entity view display configurations.
   *
   * @param array $all_configs
   *   Array of all configuration names.
   * @param string $old_theme_escaped
   *   The escaped old theme name for regex.
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   */
  protected function processEntityViewDisplayConfigs(array $all_configs, string $old_theme_escaped, string $old_theme, string $new_theme): void {
    foreach ($all_configs as $config_name) {
      if (!str_starts_with($config_name, 'core.entity_view_display.')) {
        continue;
      }

      // Skip canvas component definition configs for the old theme.
      if (str_starts_with($config_name, 'canvas.component.sdc.' . $old_theme)) {
        continue;
      }

      if ($this->processConfigWithPatterns($config_name, $old_theme_escaped, $new_theme)) {
        $this->loggerFactory->get('varbase_components')->info('Changed entity view display config: @config_name', [
          '@config_name' => $config_name,
        ]);
      }
    }
  }

  /**
   * Processes all configurations.
   *
   * @param array $all_configs
   *   Array of all configuration names.
   * @param string $old_theme_escaped
   *   The escaped old theme name for regex.
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   */
  protected function processAllConfigs(array $all_configs, string $old_theme_escaped, string $old_theme, string $new_theme): void {
    foreach ($all_configs as $config_name) {
      $config_changed = FALSE;

      // Skip canvas component definition configs that belong to the old theme
      // (e.g. canvas.component.sdc.vartheme_bs5.*). These are component
      // definitions owned by the old theme; the new theme already has its own
      // equivalent canvas component configs and renaming these would cause UUID
      // conflicts on cache rebuild.
      if (str_starts_with($config_name, 'canvas.component.sdc.' . $old_theme)) {
        continue;
      }

      // Process with patterns for all configs.
      if ($this->processConfigWithPatterns($config_name, $old_theme_escaped, $new_theme)) {
        $config_changed = TRUE;
      }

      if ($config_changed) {
        $this->loggerFactory->get('varbase_components')->info('Changed config: @config_name', [
          '@config_name' => $config_name,
        ]);
      }
    }

    foreach ($all_configs as $config_name) {
      $config_changed = FALSE;

      // Special handling for none old theme configurations.
      if (!str_contains($config_name, $old_theme_escaped)) {
        if ($this->processDependenciesInConfig($config_name, $old_theme, $new_theme)) {
          $config_changed = TRUE;
        }
      }

      if ($config_changed) {
        $this->loggerFactory->get('varbase_components')->info('Changed config: @config_name', [
          '@config_name' => $config_name,
        ]);
      }
    }
  }

  /**
   * Processes a configuration with regex patterns.
   *
   * @param string $config_name
   *   The configuration name.
   * @param string $old_theme_escaped
   *   The escaped old theme name for regex.
   * @param string $new_theme
   *   The new theme machine name.
   *
   * @return bool
   *   TRUE if the configuration was changed, FALSE otherwise.
   */
  protected function processConfigWithPatterns(string $config_name, string $old_theme_escaped, string $new_theme): bool {
    $config = $this->configFactory->getEditable($config_name);
    $data = $config->getRawData();

    if (empty($data)) {
      return FALSE;
    }

    try {
      $yaml = Yaml::dump($data);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('varbase_components')->warning('Failed to dump YAML for config @config: @message', [
        '@config' => $config_name,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }

    if (empty($yaml)) {
      return FALSE;
    }

    // Determine the filesystem path prefix for the new theme (e.g.
    // 'themes/custom/mytheme') so we can replace file-path references
    // such as logo paths or template overrides that include the old theme's
    // directory.
    $new_theme_path = NULL;
    if ($this->themeHandler->themeExists($new_theme)) {
      $new_theme_info = $this->themeHandler->getTheme($new_theme);
      if ($new_theme_info) {
        $new_theme_path = $new_theme_info->getPath();
      }
    }

    $patterns = [
      // Filesystem paths: themes/(contrib|custom)/oldtheme → new theme path.
      // Only added when the new theme path is known.
      // Colon-separated plugin/component IDs (legacy format).
      '/(\b\w+:)' . $old_theme_escaped . '(:\w+)/',
      '/(^|\s|\'|")' . $old_theme_escaped . '(:\w+)/',
      // Dot-separated SDC component IDs: sdc.{theme}.{component}.
      // Replaces 'sdc.oldtheme.' with 'sdc.newtheme.' safely.
      '/\bsdc\.' . $old_theme_escaped . '\./',
    ];

    $new_yaml = $yaml;

    // Replace filesystem paths first (before the generic theme-name patterns).
    if ($new_theme_path !== NULL) {
      $new_yaml = preg_replace(
        '/themes\/(?:contrib|custom)\/' . $old_theme_escaped . '/',
        $new_theme_path,
        $new_yaml
      );
    }

    foreach ($patterns as $index => $pattern) {
      if ($index === 2) {
        // Simple replacement for the dot-separated SDC prefix.
        $new_yaml = preg_replace($pattern, 'sdc.' . $new_theme . '.', $new_yaml);
      }
      else {
        $new_yaml = preg_replace_callback($pattern, function ($matches) use ($new_theme) {
          return $matches[1] . $new_theme . $matches[2];
        }, $new_yaml);
      }
    }

    if ($new_yaml === $yaml || empty($new_yaml)) {
      return FALSE;
    }

    try {
      $new_data = Yaml::parse($new_yaml);
      if (!is_array($new_data)) {
        return FALSE;
      }

      $config->setData($new_data)->save();
      return TRUE;
    }
    catch (ParseException $e) {
      $this->loggerFactory->get('varbase_components')->error('Failed to parse YAML for config @config: @message', [
        '@config' => $config_name,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Processes dependencies in configurations with theme dependency replacement.
   *
   * @param string $config_name
   *   The configuration name.
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   *
   * @return bool
   *   TRUE if the configuration was changed, FALSE otherwise.
   */
  protected function processDependenciesInConfig(string $config_name, string $old_theme, string $new_theme): bool {
    $config = $this->configFactory->getEditable($config_name);
    $data = $config->getRawData();

    if (empty($data)) {
      return FALSE;
    }

    $config_changed = FALSE;

    // Handle dependencies section.
    if (isset($data['dependencies'])) {
      $config_changed = $this->changeThemeDependenciesInConfig($data['dependencies'], $old_theme, $new_theme) || $config_changed;
    }

    // Save the configuration if any changes were made.
    if ($config_changed) {
      try {
        $config->setData($data)->save();
        $this->loggerFactory->get('varbase_components')->info('Auto switched theme dependencies for: @config_name', [
          '@config_name' => $config_name,
        ]);
        return TRUE;
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('varbase_components')->error('Failed to save config @config: @message', [
          '@config' => $config_name,
          '@message' => $e->getMessage(),
        ]);
        return FALSE;
      }
    }

    return FALSE;
  }

  /**
   * Updates theme dependencies in configuration data.
   *
   * @param array &$dependencies
   *   The dependencies array to update (passed by reference).
   * @param string $old_theme
   *   The old theme machine name.
   * @param string $new_theme
   *   The new theme machine name.
   *
   * @return bool
   *   TRUE if any changes were made, FALSE otherwise.
   */
  protected function changeThemeDependenciesInConfig(array &$dependencies, string $old_theme, string $new_theme): bool {
    $changed = FALSE;

    if (isset($dependencies['theme']) && is_array($dependencies['theme'])) {
      $theme_index = array_search($old_theme, $dependencies['theme'], TRUE);
      if ($theme_index !== FALSE) {
        $dependencies['theme'][$theme_index] = $new_theme;
        $changed = TRUE;
      }
    }

    return $changed;
  }

  /**
   * Checks if a theme has a specific flag set to TRUE in its info.yml file.
   *
   * @param string $theme_name
   *   The machine name of the theme.
   * @param string $flag
   *   The flag to check (e.g., 'auto_switch_components').
   *
   * @return bool
   *   TRUE if the flag is defined and set to TRUE, FALSE otherwise.
   */
  protected function themeHasFlag(string $theme_name, string $flag): bool {
    if (!$this->themeHandler->themeExists($theme_name)) {
      return FALSE;
    }

    $theme = $this->themeHandler->getTheme($theme_name);
    if (!$theme) {
      return FALSE;
    }

    $theme_path = $theme->getPath();
    if (!$theme_path) {
      return FALSE;
    }

    $theme_info_file = $theme_path . '/' . $theme_name . '.info.yml';

    if (!file_exists($theme_info_file)) {
      return FALSE;
    }

    $file_contents = file_get_contents($theme_info_file);
    if ($file_contents === FALSE) {
      $this->loggerFactory->get('varbase_components')->warning('Could not read theme info file: @file', [
        '@file' => $theme_info_file,
      ]);
      return FALSE;
    }

    try {
      $info_file_data = Yaml::parse($file_contents);
    }
    catch (ParseException $e) {
      $this->loggerFactory->get('varbase_components')->error('Failed to parse theme info file @file: @message', [
        '@file' => $theme_info_file,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }

    return !empty($info_file_data[$flag]) && $info_file_data[$flag] === TRUE;
  }

}
