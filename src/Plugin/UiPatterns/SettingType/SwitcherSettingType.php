<?php

namespace Drupal\varbase_components\Plugin\UIPatterns\SettingType;

use Drupal\ui_patterns_settings\Definition\PatternDefinitionSetting;
use Drupal\ui_patterns_settings\Plugin\PatternSettingTypeBase;

/**
 * Switcher setting type.
 *
 * @UiPatternsSettingType(
 *   id = "switcher",
 *   label = @Translation("true/false")
 * )
 */
class SwitcherSettingType extends PatternSettingTypeBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, $value, PatternDefinitionSetting $def, $form_type) {
    $value = $this->getValue($value);
    $form[$def->getName()] = [
      '#type' => 'checkbox',
      '#title' => $def->getLabel(),
      '#description' => $def->getDescription(),
      '#default_value' => (boolean) $value,
    ];
    $this->handleInput($form[$def->getName()], $def, $form_type);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsPreprocess($value, array $context, PatternDefinitionSetting $def) {
    return (boolean) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldStorageExposableTypes() {
    return ['boolean'];
  }

}
