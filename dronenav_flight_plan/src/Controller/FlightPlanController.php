<?php

namespace Drupal\dronenav_flight_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Controller for DroneNav Flight Plans.
 */
class FlightPlanController extends ControllerBase {

  /**
   * Displays the current user's working Flight Plans.
   */
  public function list(): array {

    $header = [
      $this->t('Flight Plan'),
      $this->t('Status'),
      $this->t('Flight Class'),
      $this->t('Departure'),
      $this->t('Origin Site'),
      $this->t('Destination Site'),
      $this->t('Operations'),
    ];

    $rows = [];

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'working_flight_plan')
      ->condition('uid', $this->currentUser()->id())
      ->sort('created', 'DESC')
      ->execute();

    if (!empty($nids)) {
      $nodes = Node::loadMultiple($nids);

      foreach ($nodes as $node) {
        $operations = [];

        $operations[] = Link::fromTextAndUrl(
          $this->t('Edit'),
          Url::fromRoute(
            'entity.node.edit_form',
            ['node' => $node->id()],
            [
              'query' => [
                'destination' => Url::fromRoute('dronenav_flight_plan.list')->toString(),
              ],
            ]
          )
        )->toString();

        $operations[] = Link::fromTextAndUrl(
          $this->t('Delete'),
          Url::fromRoute(
            'entity.node.delete_form',
            ['node' => $node->id()],
            [
              'query' => [
                'destination' => Url::fromRoute('dronenav_flight_plan.list')->toString(),
              ],
            ]
          )
        )->toString();

        $rows[] = [
          $node->label(),
          $this->getEntityReferenceLabel($node, 'field_flight_plan_status'),
          $this->getEntityReferenceLabel($node, 'field_flight_class'),
          $node->get('field_departure_datetime')->value ?? '',
          $this->getEntityReferenceLabel($node, 'field_origin_site'),
          $this->getEntityReferenceLabel($node, 'field_destination_site'),
          [ 
            'data' => [
              '#markup' => implode(' | ', $operations),
            ],
            'style' => 'white-space: nowrap;',
          ],
        ];
      }
    }

    return [
      '#cache' => [
        'max-age' => 0,
      ],
      'add_button' => [
        '#type' => 'link',
        '#title' => $this->t('File Flight Plan'),
        '#url' => Url::fromRoute('dronenav_flight_plan.add'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No Flight Plans found.'),
        '#attributes' => [
             'style' => 'border-spacing: 10px 0px; border-collapse: separate;',
        ],
      ],
    ];

  }

  /**
   * Returns the current user's Aviator node.
   */
  protected function getCurrentAviator(): ?Node {

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'aviator')
      ->condition('field_aviator_account', $this->currentUser()->id())
      ->range(0, 1)
      ->execute();

    if (empty($nids)) {
      return NULL;
    }

    return Node::load(reset($nids));

  }


  /**
   * Returns the label for a referenced entity field.
   */
  protected function getEntityReferenceLabel(Node $node, string $field_name): string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }

    $entity = $node->get($field_name)->entity;

    return $entity ? $entity->label() : '';
  }

  public function add() {

    $aviator = $this->getCurrentAviator();

    if (!$aviator) {
      $this->messenger()->addError($this->t('No Aviator profile was found.'));
      return $this->redirect('<front>');
    }

    // Read the defaults...
    $authority = $aviator->get('field_authority')->target_id;

    $home_site = $aviator->get('field_home_site')->target_id;

    $default_aircraft = $aviator->get('field_default_aircraft')->target_id;

    $flight_class_terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'flight_class',
        'name' => 'Recreational',
      ]);

    $flight_class = reset($flight_class_terms);

    $status_terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'vid' => 'flight_plan_status',
        'name' => 'Draft',
      ]);

    $flight_plan_status = reset($status_terms);

    $flight_plan = Node::create([
      'type' => 'working_flight_plan',

      'title' => 'New Flight Plan',

      'uid' => $this->currentUser()->id(),

      'field_aviator' => [
        'target_id' => $aviator->id(),
      ],

      'field_authority' => [
        'target_id' => $authority,
      ],

      'field_aircraft' => [
        'target_id' => $default_aircraft,
      ],

      'field_origin_site' => [
        'target_id' => $home_site,
      ],

      'field_destination_site' => [
        'target_id' => $home_site,
      ],

      'field_flight_class' => [
        'target_id' => $flight_class ? $flight_class->id() : NULL,
      ],

      'field_flight_plan_status' => [
        'target_id' => $flight_plan_status ? $flight_plan_status->id() : NULL,
      ],

    ]);

    $flight_plan->save();

    return $this->redirect(
      'entity.node.edit_form',
      ['node' => $flight_plan->id()],
      [
        'query' => [
          'destination' => Url::fromRoute('dronenav_flight_plan.list')->toString(),
        ],
      ]
    );

  }


}

