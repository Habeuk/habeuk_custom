<?php
declare(strict_types = 1);

namespace Drupal\habeuk_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Component\Utility\Random;
use Drupal\Component\Utility\Html;

/**
 * Provides a tabs order site block.
 *
 * @Block(
 *   id = "hbktabs_order_site",
 *   admin_label = @Translation("Hbk tabs order site"),
 *   category = @Translation("Custom"),
 * )
 */
final class HbktabsOrderSiteBlock extends BlockBase implements ContainerFactoryPluginInterface {
  
  /**
   * Constructs the plugin instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'tab1' => [
        'icone' => '',
        'titre' => '',
        'entity_type' => '',
        'bundle' => ''
      ],
      'tab2' => [
        'icone' => '',
        'titre' => '',
        'entity_type' => '',
        'bundle' => ''
      ],
      'tab3' => [
        'icone' => '',
        'titre' => '',
        'entity_type' => '',
        'bundle' => ''
      ],
      'number_tbas' => 3,
      'id_modal' => null
    ] + parent::defaultConfiguration();
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['number_tbas'] = [
      '#type' => 'number',
      '#title' => "nombre de tabs",
      '#required' => true,
      '#default_value' => $this->configuration['number_tbas']
    ];
    $form['id_modal'] = [
      '#type' => 'textfield',
      '#title' => "Id modal",
      '#default_value' => $this->configuration['id_modal']
    ];
    $index = (int) $this->configuration['number_tbas'];
    if ($index)
      for ($i = 0; $i < $index; $i++) {
        $form['tab' . $i] = [
          '#type' => 'details',
          '#title' => 'Tab ' . $i,
          '#tree' => true,
          '#open' => true,
          '#attributes' => [
            'id' => 'id_hbktabs_order_site' . $i
          ],
          '#preffix' => '<div id="hbktabs_order_site_container">',
          '#suffix' => '</div>'
        ];
        $form['tab' . $i]['icone'] = [
          '#type' => 'textarea',
          '#title' => 'Icone',
          '#default_value' => $this->configuration['tab' . $i]['icone']
        ];
        $form['tab' . $i]['titre'] = [
          '#type' => 'textfield',
          '#title' => 'Titre',
          '#default_value' => $this->configuration['tab' . $i]['titre']
        ];
        if ($form_state instanceof SubformState) {
          $settings = $form_state->getCompleteFormState()->getValue('settings');
          $entity_type = isset($settings['tab' . $i]['entity_type']) ? $settings['tab' . $i]['entity_type'] : $this->configuration['tab' . $i]['entity_type'];
        }
        else
          $entity_type = $this->configuration['tab' . $i]['entity_type'];
        //
        $form['tab' . $i]['entity_type'] = [
          '#type' => 'select',
          '#title' => 'entity_type',
          '#options' => $this->getEntityDefinitions(),
          '#default_value' => $entity_type,
          '#required' => true,
          '#ajax' => [
            'callback' => self::class . '::ProccessAfterSelect',
            'wrapper' => 'hbktabs_order_site_container',
            'effect' => 'fade'
          ]
        ];
        if ($entity_type) {
          $form['tab' . $i]['bundle'] = [
            '#type' => 'select',
            '#title' => 'bundle',
            '#options' => $this->getBundles($entity_type),
            '#default_value' => !empty($this->configuration['tab' . $i]['bundle']) ? $this->configuration['tab' . $i]['bundle'] : '',
            '#ajax' => [
              'callback' => self::class . '::ProccessAfterSelect',
              'wrapper' => 'hbktabs_order_site_container',
              'effect' => 'fade'
            ]
          ];
        }
      }
    return $form;
  }
  
  public static function ProccessAfterSelect($form, FormStateInterface $form_state) {
    return $form['settings'];
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['number_tbas'] = $form_state->getValue('number_tbas');
    $this->configuration['id_modal'] = $form_state->getValue('id_modal');
    $index = (int) $this->configuration['number_tbas'];
    if ($index)
      for ($i = 0; $i < $index; $i++) {
        $this->configuration['tab' . $i] = $form_state->getValue('tab' . $i);
      }
  }
  
  /**
   *
   * @param string $entity_type
   * @return []
   */
  protected function getBundles($entity_type) {
    /**
     *
     * @var \Drupal\Core\Entity\EntityTypeInterface $definition
     */
    $definition = $this->entityTypeManager->getDefinition($entity_type);
    if ($definition->getBaseTable()) {
      $entity_bundlde = $definition->getBundleOf();
    }
    else
      $entity_bundlde = $entity_type;
    if ($entity_bundlde) {
      $options = [];
      // dd($entity_bundlde,
      // $this->entityTypeManager->getStorage($entity_bundlde)->loadMultiple());
      foreach ($this->entityTypeManager->getStorage($entity_bundlde)->loadMultiple() as $entity) {
        $options[$entity->id()] = $entity->label();
      }
      return $options;
    }
    
    return [
      $entity_type => $entity_type
    ];
  }
  
  protected function getEntityDefinitions() {
    $definitions = $this->entityTypeManager->getDefinitions();
    $options = [];
    foreach ($definitions as $definition) {
      $options[$definition->id()] = $definition->getLabel();
    }
    return $options;
  }
  
  /**
   *
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];
    $tabs = [];
    $index = (int) $this->configuration['number_tbas'];
    $Random = new Random();
    $id_tabs = !empty($this->configuration['id_modal']) ? $this->configuration['id_modal'] : html::getId($Random->string(12));
    if ($index)
      for ($i = 0; $i < $index; $i++) {
        if (!empty($this->configuration['tab' . $i]['entity_type'])) {
          $tabs[$i]['entity_form'] = $this->configuration['tab' . $i]['bundle'];
          $tabs[$i]['titre'] = $this->configuration['tab' . $i]['titre'];
          $tabs[$i]['icone'] = $this->configuration['tab' . $i]['icone'];
          $tabs[$i]['id_tab'] = $id_tabs . '__' . $i;
          $tabs[$i]['status_tab'] = $i == 0 ? true : false;
          // Adapté si entity_type est une entité de configuration.
          $entity_type_id = $this->configuration['tab' . $i]['entity_type'];
          $bundle = $this->configuration['tab' . $i]['bundle'];
          if ($bundle && $entity_type_id != $bundle) {
            $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($bundle);
            $view_builder = $this->entityTypeManager->getViewBuilder($entity_type_id);
            $tabs[$i]['entity_form'] = $view_builder->view($entity);
          }
          elseif ($entity_type_id == $bundle) {
            //
          }
        }
      }
    $build['content'] = [
      '#theme' => 'hbktabs_order_site',
      '#tabs' => $tabs,
      '#id_tabs' => $id_tabs
    ];
    return $build;
  }
  
}
