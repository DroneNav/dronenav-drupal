<?php

namespace Drupal\dronenav_flight_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns Flight Plan statuses for the current aviator.
 */
class FlightPlanStatusController extends ControllerBase {

  /**
   * Returns the current aviator's Flight Plan statuses.
   */
  public function statuses(): JsonResponse {
    $current_user = $this->currentUser();

    if (!$current_user->hasRole('aviator')) {
      return new JsonResponse([
        'error' => 'Access denied.',
      ], 403);
    }

    $uid = (int) $current_user->id();

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    $nids = $node_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'working_flight_plan')
      ->condition('uid', $uid)
      ->sort('changed', 'DESC')
      ->execute();

    $data = [];

    if (!empty($nids)) {
      $flight_plans = $node_storage->loadMultiple($nids);

      foreach ($flight_plans as $flight_plan) {
        $status = '';

        if (
          $flight_plan->hasField('field_flight_plan_status') &&
          !$flight_plan->get('field_flight_plan_status')->isEmpty()
        ) {
          $status_term = $flight_plan
            ->get('field_flight_plan_status')
            ->entity;

          if ($status_term) {
            $status = $status_term->label();
          }
        }

        $data[(string) $flight_plan->id()] = [
          'status' => $status,
          'changed' => (int) $flight_plan->getChangedTime(),
        ];
      }
    }

    return new JsonResponse($data);
  }

}

