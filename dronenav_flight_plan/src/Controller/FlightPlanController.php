<?php

namespace Drupal\dronenav_flight_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

use Drupal\dronenav_flight_plan\Service\FlightPlanValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\dronenav_flight_plan\Service\FlightPlanSubmissionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dronenav_flight_plan\Service\FlightPathOrderingService;
use Drupal\dronenav_flight_plan\Service\FlightExecutionService;


/**
 * Controller for DroneNav Flight Plans.
 */
class FlightPlanController extends ControllerBase implements ContainerInjectionInterface {

  protected FlightPlanSubmissionService $submissionService;
  protected FlightPlanValidator $flightPlanValidator;
  protected FlightPathOrderingService $flightPathOrderingService;
  protected FlightExecutionService $flightExecutionService;

  public function __construct(
    FlightPlanSubmissionService $submission_service,
    FlightPlanValidator $flight_plan_validator,
    FlightPathOrderingService $flight_path_ordering_service,
    FlightExecutionService $flight_execution_service
  ) {
    $this->submissionService = $submission_service;
    $this->flightPlanValidator = $flight_plan_validator;
    $this->flightPathOrderingService = $flight_path_ordering_service;
    $this->flightExecutionService = $flight_execution_service;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('dronenav_flight_plan.submission_service'),
      $container->get('dronenav_flight_plan.validator'),
      $container->get('dronenav_flight_plan.flight_path_ordering'),
      $container->get('dronenav_flight_plan.flight_execution')
    );
  }

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

        if ($node->isPublished()) {
          // Submitted/accepted: View.
          $operations[] = Link::fromTextAndUrl(
            $this->t('View'),
            Url::fromRoute(
              'entity.node.canonical',
              ['node' => $node->id()]
            )
          )->toString();

          /*
           * Flight Plans with no requested departure datetime
           * may be launched manually.
           */
          if ($node->get('field_departure_datetime')->isEmpty()) {
            $operations[] = Link::fromTextAndUrl(
              $this->t('Launch'),
              Url::fromRoute(
                'dronenav_flight_plan.launch',
                ['node' => $node->id()]
              )
            )->toString();
          }
        }
        else {
            // Draft/rejected/unpublished: Edit | Delete | Submit.
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

            $operations[] = Link::fromTextAndUrl(
              $this->t('Submit'),
              Url::fromRoute(
                'dronenav_flight_plan.submit',
                ['node' => $node->id()]
              )
            )->toString();

        }

        $operations[] = Link::fromTextAndUrl(
          $this->t('Map'),
          Url::fromRoute(
            'dronenav_flight_plan.map',
            ['nid' => $node->id()]
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

  public function submit(Node $node) {

    if ($node->bundle() !== 'working_flight_plan') {
      $this->messenger()->addError($this->t('Invalid Flight Plan.'));
      return $this->redirect('dronenav_flight_plan.list');
    }

    if ((int) $node->getOwnerId() !== (int) $this->currentUser()->id()) {
      $this->messenger()->addError(
        $this->t('You may only submit your own Flight Plans.')
      );

      return $this->redirect('dronenav_flight_plan.list');
    }

    /*
     * Validate Drupal-governed Flight Plan requirements.
     */
    $validation = $this->flightPlanValidator
      ->validateForSubmission($node);

    if (!$validation['valid']) {
      foreach ($validation['errors'] as $error) {
        $this->messenger()->addError($this->t($error));
      }

      return $this->redirect('dronenav_flight_plan.list');
    }

    /*
     * Derive and validate the direction-aware Route order.
     *
     * A Flight Plan with no selected Routes returns a valid empty path.
     */
    $ordering = $this->flightPathOrderingService
      ->orderFlightPath($node);

    if (!$ordering['valid']) {
      foreach ($ordering['errors'] as $error) {
        $this->messenger()->addError($this->t($error));
      }

      return $this->redirect('dronenav_flight_plan.list');
    }

    /*
     * Rewrite the entity-reference field using the derived traversal order.
     * Drupal preserves this item order through field deltas.
     */
    if (!empty($ordering['ordered_routes'])) {
      $ordered_route_references = [];

      foreach ($ordering['ordered_routes'] as $route) {
        $ordered_route_references[] = [
          'target_id' => $route->id(),
        ];
      }

      $node->set(
        'field_flight_path',
        $ordered_route_references
      );

      /*
       * Save the ordered draft before sending it to the API.
       */
      $node->save();
    }

    /*
     * Build and submit the Flight Execution payload from the ordered plan.
     */
    $response = $this->submissionService->submit($node);

    if (($response['status'] ?? '') === 'accepted') {
      $node->set(
        'field_flight_execution_id',
        $response['flight_execution_record_id'] ?? ''
      );

      $status_terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'vid' => 'flight_plan_status',
          'name' => 'Submitted',
        ]);

      $submitted_status = reset($status_terms);

      if ($submitted_status) {
        $node->set('field_flight_plan_status', [
          'target_id' => $submitted_status->id(),
        ]);
      }

      $node->setPublished(TRUE);
      $node->save();

      /*
       * Notification failure must not undo a successful submission.
       */
       \Drupal::logger('dronenav_flight_plan')->notice('Before mail()');
      $this->sendFlightPlanAcceptedEmail($node);
       \Drupal::logger('dronenav_flight_plan')->notice('After mail()');

      $this->messenger()->addStatus(
        $this->t('Flight Plan submitted successfully.')
      );
    }
    else {
      $this->messenger()->addError(
        $response['message']
          ?? $this->t('Flight Plan submission failed.')
      );
    }

    return $this->redirect('dronenav_flight_plan.list');
  }

  /**
   * Displays the overlays referenced by a Flight Plan.
   */
  public function map(int $nid): array {

    $flight_plan = $this->entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (
      !$flight_plan ||
      $flight_plan->bundle() !== 'working_flight_plan'
    ) {
      throw $this->createNotFoundException();
    }

    $site_uuids = [];
    $droneport_uuids = [];
    $route_uuids = [];

    /*
     * Origin and destination Sites.
     */
    foreach ([
      'field_origin_site',
      'field_destination_site',
    ] as $field_name) {

      if (
        !$flight_plan->hasField($field_name) ||
        $flight_plan->get($field_name)->isEmpty()
      ) {
        continue;
      }

      $site = $flight_plan->get($field_name)->entity;

      if (
        !$site ||
        !$site->hasField('field_overlay_uuid') ||
        $site->get('field_overlay_uuid')->isEmpty()
      ) {
        continue;
      }

      $site_uuids[] =
        $site->get('field_overlay_uuid')->value;
    }

    /*
     * Departure and arrival DronePorts.
     */
    foreach ([
      'field_departure_droneport',
      'field_arrival_droneport',
    ] as $field_name) {

      if (
        !$flight_plan->hasField($field_name) ||
        $flight_plan->get($field_name)->isEmpty()
      ) {
        continue;
      }

      $droneport = $flight_plan->get($field_name)->entity;

      if (
        !$droneport ||
        !$droneport->hasField('field_overlay_uuid') ||
        $droneport->get('field_overlay_uuid')->isEmpty()
      ) {
        continue;
      }

      $droneport_uuids[] =
        $droneport->get('field_overlay_uuid')->value;
    }

    /*
     * Flight Path Routes.
     */
    if (
      $flight_plan->hasField('field_flight_path') &&
      !$flight_plan->get('field_flight_path')->isEmpty()
    ) {

      foreach (
        $flight_plan->get('field_flight_path')->referencedEntities()
        as $route
      ) {

        if (
          !$route->hasField('field_overlay_uuid') ||
          $route->get('field_overlay_uuid')->isEmpty()
        ) {
          continue;
        }

        $route_uuids[] =
          $route->get('field_overlay_uuid')->value;
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'root',
        'data-mode' => 'map-context',
        'data-context-sites' => implode(',', $site_uuids),
        'data-context-zones' => '',
        'data-context-droneports' => implode(',', $droneport_uuids),
        'data-context-routes' => implode(',', $route_uuids),
        'style' => 'height: 700px; width: 100%;',
      ],
      '#attached' => [
        'library' => [
          'dronenav_survey_workbench/react_map',
        ],
      ],
    ];

  }

  /**
   * Sends a plain-text Flight Plan acceptance notification.
   */
  protected function sendFlightPlanAcceptedEmail(Node $flight_plan): void {
    try {
      $aviator = $flight_plan->get('field_aviator')->entity;

      if (
        !$aviator instanceof Node ||
        !$aviator->hasField('field_aviator_account') ||
        $aviator->get('field_aviator_account')->isEmpty()
      ) {
        \Drupal::logger('dronenav_flight_plan')->warning(
          'Acceptance email was not sent for Flight Plan @id because no Aviator account was found.',
          ['@id' => $flight_plan->id()]
        );
        return;
      }

      $account = $aviator->get('field_aviator_account')->entity;

      if (!$account || !$account->getEmail()) {
        \Drupal::logger('dronenav_flight_plan')->warning(
          'Acceptance email was not sent for Flight Plan @id because the Aviator account has no email address.',
          ['@id' => $flight_plan->id()]
        );
        return;
      }

      $reference_label = static function (
        Node $node,
        string $field_name,
        string $empty_value = 'None'
      ): string {
        if (
          !$node->hasField($field_name) ||
          $node->get($field_name)->isEmpty()
        ) {
          return $empty_value;
        }

        $entity = $node->get($field_name)->entity;

        return $entity ? $entity->label() : $empty_value;
      };

      $route_labels = [];

      if (
        $flight_plan->hasField('field_flight_path') &&
        !$flight_plan->get('field_flight_path')->isEmpty()
      ) {
        foreach ($flight_plan->get('field_flight_path')->referencedEntities() as $route) {
          $route_labels[] = $route->label();
        }
      }

      $requested_departure = 'Any Time';

      if (
        $flight_plan->hasField('field_departure_datetime') &&
        !$flight_plan->get('field_departure_datetime')->isEmpty()
      ) {
        $requested_departure =
          $flight_plan->get('field_departure_datetime')->value;
      }

      $submitted = \Drupal::service('date.formatter')->format(
        $flight_plan->getChangedTime(),
        'custom',
        'Y-m-d H:i T',
        'UTC'
      );

      $body = implode("\n", [
        'Dear ' . $aviator->label() . ',',
        '',
        'Your Flight Plan has been successfully accepted by DroneNav.',
        '',
        'The information below summarizes the accepted Flight Plan.',
        '',
        'FLIGHT PLAN SUMMARY',
        '-------------------',
        '',
        'Flight Plan: ' . $flight_plan->label(),
        'Submitted: ' . $submitted,
        'Requested Departure: ' . $requested_departure,
        'Flight Class: ' . $reference_label(
          $flight_plan,
          'field_flight_class'
        ),
        'Authority: ' . $reference_label(
          $flight_plan,
          'field_authority'
        ),
        'Aircraft: ' . $reference_label(
          $flight_plan,
          'field_aircraft'
        ),
        'Origin Site: ' . $reference_label(
          $flight_plan,
          'field_origin_site'
        ),
        'Departure DronePort: ' . $reference_label(
          $flight_plan,
          'field_departure_droneport'
        ),
        'Destination Site: ' . $reference_label(
          $flight_plan,
          'field_destination_site'
        ),
        'Arrival DronePort: ' . $reference_label(
          $flight_plan,
          'field_arrival_droneport'
        ),
        'Flight Path: ' . (
          $route_labels
            ? implode(' -> ', $route_labels)
            : 'None'
        ),
        '',
        'This Flight Plan is now immutable. If your operational requirements change, create and submit a new Flight Plan.',
        '',
        'Actual flight operations remain subject to applicable operational policies and conditions at the time of launch.',
        '',
        'Thank you for using DroneNav.',
        '',
        'DroneNav',
        'Safe Autonomous Airspace',
      ]);

      $params = [
        'body' => $body,
      ];

      $result = \Drupal::service('plugin.manager.mail')->mail(
        'dronenav_flight_plan',
        'flight_plan_accepted',
        $account->getEmail(),
        $account->getPreferredLangcode(),
        $params
      );

      if (empty($result['result'])) {
        \Drupal::logger('dronenav_flight_plan')->error(
          'Acceptance email failed for Flight Plan @id.',
          ['@id' => $flight_plan->id()]
        );
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('dronenav_flight_plan')->error(
        'Acceptance email failed for Flight Plan @id: @message',
        [
          '@id' => $flight_plan->id(),
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Launches the Flight Execution Record for a Flight Plan.
   */
  public function launch(Node $node) {

    $flight_execution_uuid = (string) (
      $node->get('field_flight_execution_id')->value ?? ''
    );

    $aviator = $node->get('field_aviator')->entity;
    $aircraft = $node->get('field_aircraft')->entity;

    $aviator_id = $aviator
      ? (string) $aviator->uuid()
      : '';

    $aircraft_id = $aircraft
      ? (string) $aircraft->uuid()
      : '';

    $result = $this->flightExecutionService->launch(
      $flight_execution_uuid,
      $aviator_id,
      $aircraft_id
    );

    if ($result['success']) {
      $this->messenger()->addStatus(
        $this->t('@message', [
          '@message' => $result['message'],
        ])
      );
    }
    else {
      $this->messenger()->addError(
        $this->t('@message', [
          '@message' => $result['message'],
        ])
      );
    }

    return $this->redirect('dronenav_flight_plan.list');
  }

}

