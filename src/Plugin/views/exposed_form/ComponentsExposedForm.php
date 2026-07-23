<?php

declare(strict_types=1);

namespace Drupal\varbase_components\Plugin\views\exposed_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\views\Attribute\ViewsExposedForm;
use Drupal\views\Plugin\views\exposed_form\ExposedFormPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a view's exposed filters through a Single Directory Component.
 *
 * The counterpart of the components views style: where that style hands the
 * view's rows to a component, this hands the exposed filter form to one. The
 * filters then look like whatever the active theme's component says they look
 * like — a site template with its own theme maps its own component and gets its
 * own filter row, instead of every theme chasing the markup Views happens to
 * emit with CSS keyed to a form ID.
 *
 * The component receives the whole exposed form in its `filters` slot.
 *
 * @ingroup views_exposed_form_plugins
 */
#[ViewsExposedForm(
  id: 'components_exposed_form',
  title: new TranslatableMarkup('Component'),
  help: new TranslatableMarkup('Renders the exposed filters using a Single Directory Component.'),
)]
class ComponentsExposedForm extends ExposedFormPluginBase {

  /**
   * The slot the exposed form is placed in.
   */
  public const FILTERS_SLOT = 'filters';

  /**
   * Constructs a ComponentsExposedForm object.
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
    $options['reset_button_always_show'] = ['default' => FALSE];
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
      '#description' => $this->t('The Single Directory Component that renders the exposed filters.'),
      '#options' => $this->getComponentOptions(),
      '#default_value' => $this->options['component_id'],
      '#empty_option' => $this->t('- Select a component -'),
      '#required' => TRUE,
    ];

    $form['reset_button_always_show'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always show reset button'),
      '#description' => $this->t('Keep the reset button visible even without user input.'),
      '#default_value' => $this->options['reset_button_always_show'],
      '#states' => [
        'invisible' => [
          'input[name="exposed_form_options[reset_button]"]' => ['checked' => FALSE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    // Core hides the reset action when there is no exposed input; reveal it
    // again when the view opts to always show it (BEF parity).
    if (!empty($this->options['reset_button_always_show']) && isset($form['actions']['reset'])) {
      $form['actions']['reset']['#access'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function renderExposedForm($block = FALSE): array {
    $form = parent::renderExposedForm($block);

    // No component, or nothing to render: leave the exposed form as it is, so a
    // view keeps working while a theme is still being wired up.
    $component_id = $this->options['component_id'] ?? '';
    if ($component_id === '' || empty($form)) {
      return $form;
    }
    if (!$this->componentPluginManager->hasDefinition($component_id)) {
      return $form;
    }

    return [
      '#type' => 'component',
      '#component' => $component_id,
      '#slots' => [
        static::FILTERS_SLOT => $form,
      ],
    ];
  }

  /**
   * Returns the components that declare themselves usable in views.
   *
   * @return array
   *   Component labels, keyed by component ID, grouped by provider.
   */
  protected function getComponentOptions(): array {
    $options = [];
    foreach ($this->componentPluginManager->getAllComponents() as $component) {
      $definition = $component->getPluginDefinition();
      if (empty($definition['use_in_views'])) {
        continue;
      }
      $provider = $definition['provider'] ?? '';
      $options[$provider][$component->getPluginId()] = $definition['name'] ?? $component->getPluginId();
    }
    return $options;
  }

}
