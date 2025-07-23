<?php

namespace Drupal\varbase_components\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Messenger\MessengerInterface;

class ActiveThemeChangeSubscriber implements EventSubscriberInterface {

  protected $messenger;
  protected $configFactory;

  public function __construct(MessengerInterface $messenger, ConfigFactoryInterface $configFactory) {
    $this->messenger = $messenger;
    $this->configFactory = $configFactory;
  }

  public static function getSubscribedEvents() {
    return [
      ConfigEvents::SAVE => 'onActiveThemeChange',
    ];
  }

  public function onActiveThemeChange(ConfigCrudEvent $event) {
    $config = $event->getConfig();

    if ($config->getName() === 'system.theme') {
      $old_theme = $config->getOriginal()['default'];
      $new_theme = $config->get('default');

      if ($old_theme !== $new_theme) {
        $this->messenger->addStatus(t('Theme changed from %old to %new. Updating active configurations...', [
          '%old' => $old_theme,
          '%new' => $new_theme,
        ]));

        $this->replaceAndSaveThemeInActiveConfigs($old_theme, $new_theme);
      }
    }
  }

  /**
   * Replace theme name in active config and save back to database.
   */
  protected function replaceAndSaveThemeInActiveConfigs(string $old_theme, string $new_theme): void {
    $old_theme_esc = preg_quote($old_theme, '/');
    $all_configs = $this->configFactory->listAll();

    foreach ($all_configs as $config_name) {
      $config = $this->configFactory->getEditable($config_name);
      $data = $config->getRawData();
      $yaml = Yaml::dump($data, 10, 2);

      $patterns = [
        '/(\b\w+:)' . $old_theme_esc . '(:\w+)/',
        '/(^|\s|\'|")' . $old_theme_esc . '(:\w+)/'
      ];

      $new_yaml = $yaml;
      foreach ($patterns as $pattern) {
        $new_yaml = preg_replace_callback($pattern, function ($matches) use ($new_theme) {
          return $matches[1] . $new_theme . $matches[2];
        }, $new_yaml);
      }

      if ($new_yaml !== $yaml) {
        $new_data = Yaml::parse($new_yaml);

        if (is_array($new_data)) {
          $config->setData($new_data)->save();
          \Drupal::logger('varbase_components')->info("Updated theme reference in active config: $config_name");
        }
      }
    }
  }
}
