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
 * Extends FormBase with the SaveMedia options.
 */
class SaveMediaForm extends FormBase {

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
    return 'save_entities_admin_form_2';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $mediaTypeList = $this->getMediaTypes();

    if (empty($mediaTypeList)) {
      // Melhorar form para acomodar esta parte.
      $form['text'] = [
        '#type' => 'item',
        '#description' => $this->t('Oops! Looks like you dont have the media module enabled'),
      ];
    }
    else {

      $form['field_set_1'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Media types'),
      ];

      $form['field_set_1']['media_types'] = [
        '#type' => 'checkboxes',
        '#options' => $mediaTypeList,
      ];

      $form['field_set_2'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Options'),
      ];

      $form['field_set_2']['update_time'] = [
        '#type' => 'checkbox',
        '#description' => $this->t('When this option is selected, the changed time of the media will be set to the current time.'),
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
        '#value' => $this->t('Save Medias'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('batch_size') <= 0) {
      $form_state->setErrorByName('batch_size', $this->t('The batch size should be higher than 0.'));
    }
    if (empty(array_filter($form_state->getValue('media_types')))) {
      $form_state->setErrorByName('content_types', $this->t('Please select 1 or more media types to start the process'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get values from form.
    $media_types_values = $form_state->getValue('media_types');
    $batch_size = (int) $form_state->getValue('batch_size');
    $save_date = $form_state->getValue('update_time');
    $only_published = $form_state->getValue('published_content');

    // Filter for only selected values.
    $selected_media_types = array_filter($media_types_values);

    // Get machine names from array.
    $media_types = array_keys($selected_media_types);

    // Call batch process.
    $this->runBatch($media_types, $batch_size, $save_date, $only_published);
  }

  /**
   * Run batch.
   *
   * @param array $media_types
   *   List of media types.
   * @param int $batch_size
   *   Size of the batch to run.
   * @param bool $save_date
   *   Describes if the change date should be updated.
   * @param bool $only_published
   *   Describes if only published content should be processed.
   */
  public function runBatch(array $media_types, $batch_size, $save_date, $only_published) {
    if (empty($media_types)) {
      return;
    }
    else {
      $ids_media = $this->getIds($media_types, $only_published);
    }
    // Check if there are media to process.
    if (!empty($ids_media)) {

      // Divides main array in smaller arrays to process.
      $media_chunks = array_chunk($ids_media, $batch_size);
      // Counts number of smaller arrays.
      $num_chunks_media = count($media_chunks);
      $operations = [];

      // For every smaller array.
      for ($i = 0; $i < $num_chunks_media; $i++) {
        $operations[] = [
          '\Drupal\save_entities\BatchProcess\SaveMediaBatchProcess::execute',
          [$media_chunks[$i], $save_date],
        ];

        $batch = [
          'title' => $this->t('Saving media'),
          'init_message' => $this->t('Saving media process is starting.'),
          'progress_message' => $this->t('Completed @current / @total batches. Estimated time: @estimate.'),
          'finished' => '\Drupal\save_entities\BatchProcess\SaveMediaBatchProcess::finishedCallback',
          'error_message' => $this->t('The batch process has encountered an error.'),
          'operations' => $operations,
        ];

        batch_set($batch);
        $batch = &batch_get();
        $batch['PROGRESSIVE'] = FALSE;

      }
    }
  }

  /**
   * Gets a list of ids from different media types.
   *
   * @param array $media_types
   *   List of media types.
   * @param bool $only_published
   *   Describes if only published content should be processed.
   *
   * @return array
   *   An array of ids purged from empty values
   */
  public function getIds(array $media_types, $only_published) {
    $media_ids = [];

    // Get array of media ids.
    foreach ($media_types as $mediaType) {
      if ($only_published) {
        $query = $this->entityTypeManager->getstorage('media')->getQuery()
          ->condition('bundle', $mediaType)->condition('status', '1');
      }
      else {
        $query = $this->entityTypeManager->getstorage('media')->getQuery()
          ->condition('bundle', $mediaType);
      }
      array_push($media_ids, $query->execute());
    }

    // Purger array of empty values and concat inside arrays.
    $purged_media_ids = array_merge(...$media_ids);
    return $purged_media_ids;
  }

  /**
   * Get all media machine names.
   *
   * @return array
   *   A list of all media types
   */
  public function getMediaTypes() {
    $mediaTypesList = [];
    $mediaTypes = $this->entityTypeBundleInfo->getBundleInfo('media');
    foreach ($mediaTypes as $node_machine_name => $mediaType) {
      $mediaTypesList[$node_machine_name] = $mediaType['label'];
    }
    return $mediaTypesList;
  }

}
