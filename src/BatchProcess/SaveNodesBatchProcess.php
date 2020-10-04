<?php

namespace Drupal\save_entities\BatchProcess;

/**
 * Resave All Nodes batch.
 */
class SaveNodesBatchProcess {

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

    $num_nodes = count($ids_array);
    if (is_numeric($num_nodes)) {

      // Initiate multistep processing.
      if (empty($context['sandbox'])) {
        $context['sandbox']['progress'] = 0;
        $context['sandbox']['max'] = $num_nodes;
        $context['sandbox']['curr_nid'] = $ids_array[0];
      }
      $max = $context['sandbox']['max'];

      // Start where we left off last time.
      $start = $context['sandbox']['progress'];
      for ($i = $start; $i < $max; $i++) {
        // Update our progress!
        $next = $ids_array[$i];
        $context['sandbox']['curr_nid'] = (int) $next;
        self::saveNode($context['sandbox']['curr_nid'], $save_date);

        // Add current node to results.
        $context['results'][] = $context['sandbox']['curr_nid'];

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
   * Save a node.
   *
   * @param int $nid
   *   The number of the node to save.
   * @param bool $save_date
   *   Describes if the change date should be updated.
   */
  public static function saveNode($nid, $save_date) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if ($save_date) {
      $node->set('changed', time());
    }
    $node->save();
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
                '@count nodes have been saved.', [
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
