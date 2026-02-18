<?php

namespace Drupal\varbase_components\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
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
   */
  public function __construct(
    MessengerInterface $messenger,
    ConfigFactoryInterface $config_factory,
    ThemeHandlerInterface $theme_handler,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->themeHandler = $theme_handler;
    $this->loggerFactory = $logger_factory;
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

    $this->messenger->addStatus($this->t('Theme changed from %old to %new. Updating active configurations...', [
      '%old' => $old_theme,
      '%new' => $new_theme,
    ]));
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

    $patterns = [
      '/(\b\w+:)' . $old_theme_escaped . '(:\w+)/',
      '/(^|\s|\'|")' . $old_theme_escaped . '(:\w+)/',
    ];

    $new_yaml = $yaml;
    foreach ($patterns as $pattern) {
      $new_yaml = preg_replace_callback($pattern, function ($matches) use ($new_theme) {
        return $matches[1] . $new_theme . $matches[2];
      }, $new_yaml);
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
