<?php

namespace Drupal\save_entities\BatchProcess;

use Drupal\media\Entity\Media;

/**
 * Resave All Media batch.
 */
class SaveMediaBatchProcess {

  /**
   * Execute batch process.
   *
   * @param array $ids_array
   *   A list of ids to process.
   * @param bool $save_date
   *   Describes if the change date should be update.
   * @param array $context
   *   The context of batch.
   */
  public static function execute(array $ids_array, $save_date, array &$context) {
    $num_media = count($ids_array);
    if (is_numeric($num_media)) {

      // Initiate multistep processing.
      if (empty($context['sandbox'])) {
        $context['sandbox']['progress'] = 0;
        $context['sandbox']['max'] = $num_media;
        $context['sandbox']['curr_media'] = $ids_array[0];
      }
      $max = $context['sandbox']['max'];

      // Start where we left off last time.
      $start = $context['sandbox']['progress'];
      for ($i = $start; $i < $max; $i++) {
        // Update our progress!
        $next = $ids_array[$i];
        $context['sandbox']['curr_media'] = (int) $next;
        self::saveMedia($context['sandbox']['curr_media'], $save_date);

        // Add current media to results.
        $context['results'][] = $context['sandbox']['curr_media'];

        // Update progress status.
        $context['sandbox']['progress'] = $i + 1;

        // Check progress status.
        if ($context['sandbox']['progress'] == $context['sandbox']['max'] - 1) {
          // Process finished.
          $context['finished'] = 1;
        }
        else {
          $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
        }

      }
    }
  }

  /**
   * Save a media.
   *
   * @param int $media_id
   *   The number of the media to save.
   * @param bool $save_date
   *   Describes if the change date should be updated.
   */
  public static function saveMedia($media_id, $save_date) {
    $media = Media::load($media_id);
    if ($save_date) {
      $media->set('changed', time());
    }
    $media->save();
  }

  /**
   * Callback of the batch process.
   *
   * @param bool $success
   *   Informs if the batch finish successfully or not.
   * @param array $results
   *   The results of the batch process.
   */
  public static function finishedCallback($success, array $results) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(
            t(
                '@count medias have been saved.', [
                  '@count' => count($results),
                ]
            )
        );
    }
    else {
      $messenger->addError(t('An error has occured in the process'));
    }
  }

}
