<?php

namespace Drupal\save_entities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Extends FormBase with the SaveNodes options.
 */
class SaveNodesForm extends FormBase {

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Provides an interface for an entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Provides an interface for entity type managers.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Provides an interface for an entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * ReportWorkerBase constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service the instance should use.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Provides an interface for an entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Provides an interface for entity type managers.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   Provides an interface for an entity type bundle info.
   */
  public function __construct(StateInterface $state, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->state = $state;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('state'),
          $container->get('entity_field.manager'),
          $container->get('entity_type.manager'),
          $container->get('entity_type.bundle.info')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'save_entities_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $contentTypeList = $this->getContentTypes();

    $form['field_set_1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content types'),
    ];

    $form['field_set_1']['content_types'] = [
      '#type' => 'checkboxes',
      '#options' => $contentTypeList,
    ];

    $form['field_set_2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Options'),
    ];

    $form['field_set_2']['update_time'] = [
      '#type' => 'checkbox',
      '#description' => $this->t('When this option is selected, the changed time of the nodes will be set to the current time.'),
      '#title' => $this->t('Change node updated time?'),
      '#return_Value' => TRUE,
    ];

    $form['field_set_2']['published_content'] = [
      '#type' => 'checkbox',
      '#description' => $this->t('When this option is selected only the published contents will be saved.'),
      '#title' => $this->t('Save only published content?'),
      '#return_Value' => TRUE,
    ];

    $form['field_set_2']['batch_size'] = [
      '#type' => 'number',
      '#default_value' => 1,
      '#title' => $this->t('Batch size'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Nodes'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('batch_size') <= 0) {
      $form_state->setErrorByName('batch_size', $this->t('The batch size should be higher than 0.'));
    }
    if (empty(array_filter($form_state->getValue('content_types')))) {
      $form_state->setErrorByName('content_types', $this->t('Please select 1 or more content types to start the process'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get values from form.
    $content_types_values = $form_state->getValue('content_types');
    $batch_size = (int) $form_state->getValue('batch_size');
    $save_date = $form_state->getValue('update_time');
    $only_published = $form_state->getValue('published_content');

    // Filter for only selected values.
    $selected_content_types = array_filter($content_types_values);

    // Get machine names from array.
    $content_types = array_keys($selected_content_types);

    // Call batch process.
    $this->runBatch($content_types, $batch_size, $save_date, $only_published);

  }

  /**
   * Run batch.
   *
   * @param array $content_types
   *   List of content types.
   * @param int $batch_size
   *   Size of the batch to run.
   * @param bool $save_date
   *   Describes if the change date should be update.
   * @param bool $only_published
   *   Describes if only published content should be processed.
   */
  public function runBatch(array $content_types, $batch_size, $save_date, $only_published) {
    if (empty($content_types)) {
      return;
    }
    else {
      $ids_node = $this->getIds($content_types, $only_published);
    }

    // Check if there are nodes to process.
    if (!empty($ids_node)) {
      // Divides main array in smaller arrays to process.
      $node_chunks = array_chunk($ids_node, $batch_size);
      // Counts number of smaller arrays.
      $num_chunks_node = count($node_chunks);

      $operations = [];

      // For every smaller array.
      for ($i = 0; $i < $num_chunks_node; $i++) {
        $operations[] = [
          '\Drupal\save_entities\BatchProcess\SaveNodesBatchProcess::execute',
          [$node_chunks[$i], $save_date],
        ];

        $batch1 = [
          'title' => $this->t('Saving nodes'),
          'init_message' => $this->t('Saving nodes process is starting.'),
          'progress_message' => $this->t('Completed @current / @total batches. Estimated time: @estimate.'),
          'finished' => '\Drupal\save_entities\BatchProcess\SaveNodesBatchProcess::finishedCallback',
          'error_message' => $this->t('The batch process has encountered an error.'),
          'operations' => $operations,
        ];

        batch_set($batch1);
        $batch1 = &batch_get();
        $batch1['PROGRESSIVE'] = FALSE;

      }
    }

  }

  /**
   * Gets a list of ids from different content types.
   *
   * @param array $content_types
   *   List of content types.
   * @param bool $only_published
   *   Describes if only published content should be processed.
   *
   * @return array
   *   A cleared array of node ids
   */
  public function getIds(array $content_types, $only_published) {
    $node_ids = [];

    // Get array of nodes ids.
    foreach ($content_types as $contentType) {
      if ($only_published) {
        $query = $this->entityTypeManager->getstorage('node')->getQuery()
          ->condition('type', $contentType)->condition('status', '1');
      }
      else {
        $query = $this->entityTypeManager->getstorage('node')->getQuery()
          ->condition('type', $contentType);
      }
      array_push($node_ids, $query->execute());
    }
    // Purger array of empty values and concat inside arrays.
    $purged_node_ids = array_merge(...$node_ids);
    return $purged_node_ids;
  }

  /**
   * Get all content type machine names.
   *
   * @return array
   *   A list of content types
   */
  public function getContentTypes() {
    $contentTypesList = [];
    $contentTypes = $this->entityTypeBundleInfo->getBundleInfo('node');
    foreach ($contentTypes as $node_machine_name => $contentType) {
      $contentTypesList[$node_machine_name] = $contentType['label'];
    }
    return $contentTypesList;
  }

}
