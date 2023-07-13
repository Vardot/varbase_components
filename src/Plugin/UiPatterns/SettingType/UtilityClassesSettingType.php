<?php

namespace Drupal\varbase_components\Plugin\UIPatterns\SettingType;

use Drupal\ui_patterns_settings\Definition\PatternDefinitionSetting;
use Drupal\ui_patterns_settings\Plugin\PatternSettingTypeBase;

/**
 * Utility Classes setting type.
 *
 * @UiPatternsSettingType(
 *   id = "utility_classes",
 *   label = @Translation("Utility Classes")
 * )
 */
class UtilityClassesSettingType extends PatternSettingTypeBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, $value, PatternDefinitionSetting $def, $form_type) {
    $form[$def->getName()] = [
      '#type' => 'textfield',
      '#title' => $def->getLabel(),
      '#description' => $def->getDescription() != NULL ? $def->getDescription() : $this->t('E.g. w-75 mb-3'),
      '#default_value' => $this->getValue($value),
    ];

    $this->handleInput($form[$def->getName()], $def, $form_type);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsPreprocess($value, array $context, PatternDefinitionSetting $def) {

    if (!empty($value) && $value !== '') {
      $utilityClassesArray = explode(" ", $value);

      return $utilityClassesArray;
    }

    return [];
  }

}
