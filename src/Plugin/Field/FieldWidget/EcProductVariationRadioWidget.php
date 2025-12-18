<?php

namespace Drupal\ec_radio_widget\Plugin\Field\FieldWidget;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationWidgetBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'ec_product_variation_radio' widget.
 *
 * @FieldWidget(
 *   id = "ec_product_variation_radio",
 *   label = @Translation("EC Product variation radio"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EcProductVariationRadioWidget extends ProductVariationWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type.
   *
   * @var string
   */
  protected string $entityType = 'commerce_product_variation';

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var EntityDisplayRepositoryInterface
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The renderer service.
   *
   * @var RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The logger channel.
   *
   * @var LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    EntityDisplayRepositoryInterface $entity_display_repository,
    RendererInterface $renderer,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings,
      $entity_type_manager,
      $entity_repository
    );

    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->renderer = $renderer;
    $this->logger = $logger_factory->get('ec_radio_widget');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('entity_display.repository'),
      $container->get('renderer'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
        'label_display' => TRUE,
        'label_text' => '',
        'hide_single' => TRUE,
        'hide_radio' => FALSE,
        'label_display_mode' => 'default',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);

    $element['label_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display label'),
      '#default_value' => $this->getSetting('label_display'),
    ];

    $element['label_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label text'),
      '#default_value' => $this->getSetting('label_text'),
      '#description' => $this->t('The label will be available to screen readers even if it is not displayed.'),
      '#required' => TRUE,
    ];

    $element['hide_single'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide widget for single variation'),
      '#default_value' => $this->getSetting('hide_single'),
    ];

    $element['hide_radio'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide radio buttons visually'),
      '#default_value' => $this->getSetting('hide_radio'),
    ];

    if (empty($form['#entity_type'])) {
      return $element;
    }

    $options = ['default' => $this->t('Default')];
    $options += array_map(
      fn($mode) => $mode['label'],
      $this->entityDisplayRepository->getViewModes($form['#entity_type'])
    );

    $element['label_display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display mode'),
      '#default_value' => $this->getSetting('label_display_mode'),
      '#options' => $options,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $summary[] = $this->t('Label: @text (@visible)', [
      '@text' => $this->getSetting('label_text'),
      '@visible' => $this->getSetting('label_display') ? $this->t('visible') : $this->t('hidden'),
    ]);

    if ($this->getSetting('hide_single')) {
      $summary[] = $this->t('Single variation: hidden');
    }

    if ($this->getSetting('hide_radio')) {
      $summary[] = $this->t('Radio buttons: hidden');
    }

    if ($this->getSetting('label_display_mode')) {
      $summary[] = $this->t('Label display mode: @mode', [
        '@mode' => $this->getSetting('label_display_mode'),
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ): array {
    $element['#attached']['library'][] = 'ec_radio_widget/styles';

    /** @var ProductInterface $product */
    $product = $form_state->get('product');
    $variations = $this->loadEnabledVariations($product);

    if (count($variations) === 0) {
      $this->logger->warning('No variations available for product @id', [
        '@id' => $product->id(),
      ]);

      $form_state->set('hide_form', TRUE);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => 0,
      ];

      return $element;
    }

    if (count($variations) === 1 && $this->getSetting('hide_single')) {
      /** @var ProductVariationInterface $selected_variation */
      $selected_variation = reset($variations);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => $selected_variation->id(),
      ];
      $form_state->set('selected_variation', $selected_variation->id());

      return $element;
    }

    $wrapper_id = Html::getUniqueId('commerce-product-add-to-cart-form');
    $form += [
      '#wrapper_id' => $wrapper_id,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    $parents = array_merge(
      $element['#field_parents'],
      [$items->getName(), $delta]
    );
    $user_input = (array) NestedArray::getValue($form_state->getUserInput(), $parents);

    if (!empty($user_input)) {
      $selected_variation = $this->selectVariationFromUserInput($variations, $user_input);
    }
    else {
      $selected_variation = $this->getDefaultVariation($product, $variations);
    }
    $form_state->set('selected_variation', $selected_variation->id());

    $element['variation'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'variation-radios-wrapper',
        ],
      ],
    ];

    if ($this->getSetting('label_display')) {
      $element['variation']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->getSetting('label_text'),
        '#attributes' => [
          'class' => [
            'variation-title',
          ],
        ],
      ];
    }

    // Important.
    // By default, the core create a fieldset for radiobuttons group.
    // For fix, needs to create buttons one by one.
    foreach ($this->buildVariationOptions($variations) as $key => $option) {
      $title_render = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'variation-option-wrapper',
          ],
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $option['label'],
          '#attributes' => [
            'class' => [
              'variation-label',
            ],
          ],
        ],
        'content' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'variation-content',
            ],
          ],
          '#markup' => $option['content'],
        ],
      ];

      try {
        $element['variation'][$key] = [
          '#type' => 'radio',
          '#return_value' => $key,
          '#default_value' => $selected_variation->id() == $key ? $key : NULL,
          '#parents' => array_merge($parents, ['variation']),
          '#title' => $this->renderer->render($title_render),
          '#title_display' => 'after',
          '#ajax' => [
            'callback' => [get_class($this), 'ajaxRefresh'],
            'wrapper' => $form['#wrapper_id'],
          ],
          '#context' => [
            'widget' => 'ec_product_variation_radio',
          ],
          '#attributes' => [
            'class' => [
              $this->getSetting('hide_radio') ? 'variation-radio-hidden' : '',
            ],
          ],
        ];
      }
      catch (\Exception $e) {
        $this->logger->error('Error rendering variation @id: @message', [
          '@id' => $option->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $element;
  }

  /**
   * Builds variation options for the radios element.
   *
   * @param ProductVariationInterface[] $variations
   *   Array of product variations.
   *
   * @return array
   *   Rendered variation options.
   */
  protected function buildVariationOptions(array $variations): array {
    $options = [];
    $view_builder = $this->entityTypeManager->getViewBuilder($this->entityType);
    $display_mode = $this->getSetting('label_display_mode');

    foreach ($variations as $variation) {
      try {
        $render_array = $view_builder->view($variation, $display_mode);
        $options[$variation->id()] = [
          'label' => $variation->label(),
          'content' => $this->renderer->render($render_array),
        ];
      }
      catch (\Exception $e) {
        $this->logger->error('Error rendering variation @id: @message', [
          '@id' => $variation->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $options;
  }

  /**
   * Selects a product variation from user input.
   *
   * @param ProductVariationInterface[] $variations
   *   An array of product variations.
   * @param array $user_input
   *   The user input.
   *
   * @return ProductVariationInterface|null
   *   The selected variation or NULL if there's no user input.
   */
  protected function selectVariationFromUserInput(array $variations, array $user_input): ?ProductVariationInterface {
    if (!empty($user_input['variation']) && isset($variations[$user_input['variation']])) {
      return $variations[$user_input['variation']];
    }

    $this->logger->notice('No valid variation selected from user input');
    return NULL;
  }

}
