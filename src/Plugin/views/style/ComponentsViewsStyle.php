<?php

declare(strict_types=1);

namespace Drupal\varbase_components\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\views\Attribute\ViewsStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a view using a Single Directory Component (SDC).
 *
 * @ingroup views_style_plugins
 */
#[ViewsStyle(
  id: "components_views_style",
  title: new TranslatableMarkup("Component"),
  help: new TranslatableMarkup("Renders the view using a Single Directory Component."),
  theme: "views_view_unformatted",
  display_types: ["normal"],
)]
class ComponentsViewsStyle extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * Constructs a ComponentsViewsStyle object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $componentPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.sdc'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['component_id'] = ['default' => ''];
    $options['rows_slot'] = ['default' => ''];
    $options['prop_mappings'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['component_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Component'),
      '#description' => $this->t('Select an SDC component to render this view.'),
      '#options' => $this->getComponentOptions(),
      '#default_value' => $this->options['component_id'],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select a component -'),
      '#ajax' => [
        'callback' => [static::class, 'ajaxRefreshSlots'],
        'wrapper' => 'views-sdc-style-wrapper',
        'event' => 'change',
      ],
    ];

    $component_id = $form_state->getValue(['style_options', 'component_id'])
      ?? $this->options['component_id'];

    $form['rows_slot'] = [
      '#type' => 'select',
      '#title' => $this->t('Rows slot'),
      '#description' => $this->t('Select the component slot where view rows will be placed.'),
      '#options' => $this->getSlotOptions($component_id),
      '#default_value' => $this->options['rows_slot'],
      '#prefix' => '<div id="views-sdc-style-wrapper">',
      '#empty_option' => $this->t('- Select a slot -'),
    ];

    // Add prop mappings section.
    $props = $this->getComponentProps($component_id);
    if (!empty($props)) {
      $form['prop_mappings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Static Prop Values'),
        '#description' => $this->t('Set static values for component props.'),
        '#tree' => TRUE,
      ];

      foreach ($props as $prop_id => $prop_info) {
        $prop_title = $prop_info['title'] ?? $prop_id;
        $prop_type = $prop_info['type'] ?? 'string';

        if (isset($prop_info['enum']) && is_array($prop_info['enum'])) {
          $form_element = [
            '#type' => 'select',
            '#title' => $prop_title,
            '#default_value' => $this->options['prop_mappings'][$prop_id] ?? '',
            '#options' => $this->getEnumOptions($prop_info),
            '#empty_option' => $this->t('- None -'),
            '#description' => $prop_info['description'] ?? '',
          ];
        }
        else {
          $form_element = [
            '#type' => $this->getFormTypeForProp($prop_type),
            '#title' => $prop_title,
            '#default_value' => $this->options['prop_mappings'][$prop_id] ?? '',
            '#description' => $prop_info['description'] ?? '',
          ];

          $type_string = is_array($prop_type) ? implode('|', $prop_type) : $prop_type;
          if (in_array($type_string, ['array', 'object']) ||
              (is_array($prop_type) && (in_array('array', $prop_type) || in_array('object', $prop_type)))) {
            $form_element['#description'] .= ' ' . $this->t('Enter comma-separated values or JSON format.');
          }
        }

        $form['prop_mappings'][$prop_id] = $form_element;
      }
    }

    $form['rows_slot']['#suffix'] = '</div>';
  }

  /**
   * Ajax callback to refresh the slot/prop section when component changes.
   */
  public static function ajaxRefreshSlots(array $form, FormStateInterface $form_state): array {
    return $form['options']['style_options']['rows_slot'] ?? $form['style_options']['rows_slot'] ?? [];
  }

  /**
   * Returns the appropriate form type for a prop based on its type.
   */
  protected function getFormTypeForProp(string|array $prop_type): string {
    if (is_array($prop_type)) {
      if (in_array('boolean', $prop_type)) {
        return 'checkbox';
      }
      if (in_array('number', $prop_type) || in_array('integer', $prop_type)) {
        return 'number';
      }
      $prop_type = $prop_type[0] ?? 'string';
    }

    return match ($prop_type) {
      'boolean' => 'checkbox',
      'number', 'integer' => 'number',
      'array', 'object' => 'textarea',
      default => 'textfield',
    };
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $component_id = $this->options['component_id'];
    if (empty($component_id)) {
      return [];
    }

    // Render all rows using the row plugin.
    $rendered_rows = parent::render();

    // Extract the rows from the parent output (no grouping).
    $rows = [];
    foreach ($rendered_rows as $output_item) {
      if (!empty($output_item['#rows'])) {
        $rows = array_merge($rows, $output_item['#rows']);
      }
    }

    // Determine which slot to place rows in.
    $slot_name = $this->options['rows_slot'];
    if (empty($slot_name)) {
      $slot_name = $this->getFirstSlotName($component_id);
    }

    if (empty($slot_name)) {
      \Drupal::messenger()->addWarning($this->t('The selected component "@component" does not have slots. Consider using a component with a "rows" slot and "use_in_views: true" set.', [
        '@component' => $component_id,
      ]));
      return $rendered_rows;
    }

    $render = [
      '#type' => 'component',
      '#component' => $component_id,
      '#slots' => [
        $slot_name => $rows,
      ],
    ];

    // Add static prop values if any.
    $prop_mappings = $this->options['prop_mappings'] ?? [];
    if (!empty($prop_mappings)) {
      $props = array_filter($prop_mappings, fn($value) => $value !== '' && $value !== NULL);
      if (!empty($props)) {
        $render['#props'] = $this->convertPropTypes($component_id, $props);
      }
    }

    return $render;
  }

  /**
   * Converts prop values to their expected types based on component schema.
   */
  protected function convertPropTypes(string $component_id, array $props): array {
    $schema = $this->getComponentProps($component_id);
    $converted = [];

    foreach ($props as $prop_id => $value) {
      if (!isset($schema[$prop_id])) {
        $converted[$prop_id] = $value;
        continue;
      }

      $prop_info = $schema[$prop_id];

      if ($value === '' || $value === NULL) {
        if (isset($prop_info['default'])) {
          $converted[$prop_id] = $prop_info['default'];
        }
        continue;
      }

      $prop_type = is_array($prop_info['type'] ?? NULL)
        ? ($prop_info['type'][0] ?? 'string')
        : ($prop_info['type'] ?? 'string');

      $converted[$prop_id] = match ($prop_type) {
        'boolean' => (bool) $value,
        'integer' => (int) $value,
        'number' => is_numeric($value) ? (float) $value : $value,
        'array' => is_string($value) ? $this->stringToArray($value) : $value,
        'object' => is_string($value) ? $this->stringToObject($value) : $value,
        default => $value,
      };
    }

    return $converted;
  }

  /**
   * Converts a string to an array (JSON or comma-separated).
   */
  protected function stringToArray(string $value): array {
    if (str_starts_with(trim($value), '[') || str_starts_with(trim($value), '{')) {
      $decoded = json_decode($value, TRUE);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
      }
    }

    if (str_contains($value, ',')) {
      return array_map('trim', explode(',', $value));
    }

    return [$value];
  }

  /**
   * Converts a string to an object/associative array (JSON).
   */
  protected function stringToObject(string $value): array|object {
    if (str_starts_with(trim($value), '{')) {
      $decoded = json_decode($value);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
      }
    }

    return $this->stringToArray($value);
  }

  /**
   * Returns the name of the first slot in the component.
   */
  protected function getFirstSlotName(string $component_id): string {
    try {
      $component = $this->componentPluginManager->find($component_id);
      $slots = array_keys((array) ($component->metadata->slots ?? []));
      return $slots[0] ?? '';
    }
    catch (\Exception) {
      return '';
    }
  }

  /**
   * Returns a list of SDC components suitable for views (have a "rows" slot).
   */
  protected function getComponentOptions(): array {
    $options = [];

    foreach ($this->componentPluginManager->getDefinitions() as $id => $definition) {
      try {
        $component = $this->componentPluginManager->find($id);
        $slots = (array) ($component->metadata->slots ?? []);
        $use_in_views = $component->metadata->use_in_views ?? FALSE;

        if (isset($slots['rows']) && $use_in_views) {
          $options[$id] = $definition['name'] ?? $id;
        }
      }
      catch (\Exception) {
        // Skip components that cannot be loaded.
      }
    }

    asort($options);
    return $options;
  }

  /**
   * Returns the slot options for the given component ID.
   */
  protected function getSlotOptions(string $component_id): array {
    if (empty($component_id)) {
      return [];
    }
    try {
      $component = $this->componentPluginManager->find($component_id);
      $slots = (array) ($component->metadata->slots ?? []);
      $options = [];
      foreach ($slots as $slot_id => $slot) {
        $options[$slot_id] = $slot['title'] ?? $slot_id;
      }
      return $options;
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * Returns the props schema for the given component ID.
   */
  protected function getComponentProps(string $component_id): array {
    if (empty($component_id)) {
      return [];
    }
    try {
      $component = $this->componentPluginManager->find($component_id);
      return $component->metadata->schema['properties'] ?? [];
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * Builds select options from a prop's enum definition.
   */
  protected function getEnumOptions(array $definition): array {
    if (empty($definition['enum']) || !is_array($definition['enum'])) {
      return [];
    }

    $values = array_combine(
      $definition['enum'],
      array_map(static function ($value) {
        return is_string($value) ? ucwords(str_replace(['_', '-'], ' ', $value)) : $value;
      }, $definition['enum'])
    );

    if (isset($definition['meta:enum']) && is_array($definition['meta:enum'])) {
      $meta = array_intersect_key($definition['meta:enum'], $values);
      foreach ($meta as $value => $label) {
        $values[$value] = $label;
      }
    }

    return $values;
  }

}
