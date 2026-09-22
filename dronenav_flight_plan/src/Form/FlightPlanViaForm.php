<?php

namespace Drupal\dronenav_flight_plan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;


class FlightPlanViaForm extends FormBase {

  public function getFormId() {
    return 'dronenav_flight_plan_via_form';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state
  ) {

    $flight_plan = \Drupal::routeMatch()->getParameter('node');

    if (
      $flight_plan !== NULL &&
      (
        !$flight_plan instanceof Node ||
        $flight_plan->bundle() !== 'working_flight_plan'
      )
    ) {
      throw new \InvalidArgumentException(
        'A working Flight Plan is required.'
      );
    }

    if (!$flight_plan) {
      $aviator = $this->getCurrentAviator();

      if (!$aviator) {
        throw new \RuntimeException(
          'No Aviator profile was found.'
        );
      }

      $authority = $aviator->get('field_authority')->target_id;
      $home_site = $aviator->get('field_home_site')->target_id;
      $default_aircraft = $aviator
        ->get('field_default_aircraft')
        ->target_id;

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
          'target_id' => $flight_class
            ? $flight_class->id()
            : NULL,
        ],

        'field_flight_plan_status' => [
          'target_id' => $flight_plan_status
            ? $flight_plan_status->id()
            : NULL,
        ],
      ]);

    }

    $form_state->set(
      'flight_plan',
      $flight_plan
    );

    $saved_flights = $flight_plan
      ->get('field_flights')
      ->referencedEntities();

    $saved_flight_1 = $saved_flights[0] ?? NULL;
    $saved_flight_2 = $saved_flights[1] ?? NULL;

    if ($form_state->get('via_flight_count') === NULL) {
      $form_state->set(
        'via_flight_count',
        max(2, count($saved_flights))
      );
    }

    $via_flight_count = $form_state->get('via_flight_count');

    $form['flight_plan'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Flight Plan'),
    ];

    $form['flight_plan']['flight_plan_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Flight Plan Name'),
      '#default_value' => $flight_plan->label() === 'New Flight Plan'
        ? ''
        : $flight_plan->label(),
      '#required' => TRUE,
    ];

    $form['flight_plan']['aviator'] = [
      '#type' => 'select',
      '#title' => $this->t('Aviator'),
      '#options' => $this->getReferenceOptions(
        $flight_plan,
        'field_aviator'
      ),
      '#default_value' => $flight_plan
        ->get('field_aviator')
        ->target_id,
      '#required' => TRUE,
    ];

    $form['flight_plan']['aircraft'] = [
      '#type' => 'select',
      '#title' => $this->t('Aircraft'),
      '#options' => $this->getReferenceOptions(
        $flight_plan,
        'field_aircraft'
      ),
      '#default_value' => $flight_plan
        ->get('field_aircraft')
        ->target_id,
      '#required' => TRUE,
    ];

    $form['flight_plan']['authority'] = [
      '#type' => 'select',
      '#title' => $this->t('Authority'),
      '#options' => $this->getReferenceOptions(
        $flight_plan,
        'field_authority'
      ),
      '#default_value' => $flight_plan
        ->get('field_authority')
        ->target_id,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::authorityChanged',
        'wrapper' => 'flights-wrapper',
      ],
    ];

    $form['flight_plan']['flight_class'] = [
      '#type' => 'select',
      '#title' => $this->t('Flight Class'),
      '#options' => $this->getReferenceOptions(
        $flight_plan,
        'field_flight_class'
      ),
      '#default_value' => $flight_plan
        ->get('field_flight_class')
        ->target_id,
      '#required' => TRUE,
    ];

    $form['flight_plan']['departure_datetime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Departure Date/Time'),
      '#default_value' => $flight_plan
        ->get('field_departure_datetime')->date,
      '#required' => TRUE,
    ];

    $authority_nid = $form_state->getValue('authority')
      ?: $flight_plan->get('field_authority')->target_id;

    $form['flights'] = [
      '#type' => 'container',
      '#prefix' => '<div id="flights-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['flights']['flight_1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Flight 1'),
    ];

    $form['flights']['flight_1']['origin_site'] = [
      '#type' => 'select',
      '#title' => $this->t('Origin Site'),
      '#options' => \Drupal::service(
        'dronenav_flight_plan.option_service'
      )->getSiteOptions($authority_nid),
      '#required' => TRUE,
      '#default_value' => $saved_flight_1
        ? $saved_flight_1->get('field_origin_site')->target_id
        : NULL,
      '#ajax' => [
        'callback' => '::originSiteChanged',
        'wrapper' => 'flights-wrapper',
      ],
    ];

    $origin_site_nid = $form_state->getValue('origin_site')
      ?: (
        $saved_flight_1
          ? $saved_flight_1->get('field_origin_site')->target_id
          : NULL
      );

    $departure_droneport_options = \Drupal::service(
      'dronenav_flight_plan.option_service'
    )->getDronePortOptions($origin_site_nid);

    unset($departure_droneport_options['_none']);

    $form['flights']['flight_1']['departure_droneport'] = [
      '#type' => 'select',
      '#title' => $this->t('Departure DronePort'),
      '#options' => $departure_droneport_options,
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('departure_droneport')
        ?: (
          $saved_flight_1
            ? $saved_flight_1
              ->get('field_departure_droneport')
              ->target_id
            : NULL
        ),
    ];

    $form['flights']['flight_1']['destination_site'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination Site'),
      '#options' => \Drupal::service(
        'dronenav_flight_plan.option_service'
      )->getSiteOptions($authority_nid),
      '#required' => TRUE,
      '#default_value' => $saved_flight_1
        ? $saved_flight_1->get('field_destination_site')->target_id
        : NULL,
      '#ajax' => [
        'callback' => '::destinationSiteChanged',
        'wrapper' => 'flights-wrapper',
      ],
    ];

    $destination_site_nid = $form_state->getValue(
      'destination_site'
    ) ?: (
      $saved_flight_1
        ? $saved_flight_1->get('field_destination_site')->target_id
        : NULL
    );

    $arrival_droneport_options = \Drupal::service(
      'dronenav_flight_plan.option_service'
    )->getDronePortOptions($destination_site_nid);

    unset($arrival_droneport_options['_none']);

    $form['flights']['flight_1']['arrival_droneport'] = [
      '#type' => 'select',
      '#title' => $this->t('Arrival DronePort'),
      '#options' => $arrival_droneport_options,
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('arrival_droneport')
        ?: (
          $saved_flight_1
            ? $saved_flight_1
              ->get('field_arrival_droneport')
              ->target_id
            : NULL
        ),
      '#ajax' => [
        'callback' => '::arrivalDronePortChanged',
        'wrapper' => 'flights-wrapper',
      ],
    ];

    $flight_2_departure_droneport_nid = $form_state->getValue(
      'arrival_droneport'
    ) ?: (
      $saved_flight_1
        ? $saved_flight_1
          ->get('field_arrival_droneport')
          ->target_id
        : NULL
    );

    $form['flights']['flight_1']['flight_path'] = [
      '#type' => 'select',
      '#title' => $this->t('Flight Path'),
      '#options' => \Drupal::service(
        'dronenav_flight_plan.option_service'
      )->getRouteOptions(
        $origin_site_nid,
        $destination_site_nid
      ),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('flight_path')
        ?: (
          $saved_flight_1
            ? array_column(
              $saved_flight_1->get('field_flight_path')->getValue(),
              'target_id'
            )
            : []
        ),
    ];


    $flight_2_origin_site_nid = $destination_site_nid;

    $form['flights']['flight_2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Flight 2'),
      '#prefix' => '<div id="flight-2-wrapper">',
      '#suffix' => '</div>',
    ];

    $flight_2_origin_site = $flight_2_origin_site_nid
      ? Node::load($flight_2_origin_site_nid)
      : NULL;

    $form['flights']['flight_2']['origin_site'] = [
      '#type' => 'item',
      '#title' => $this->t('Origin Site'),
      '#markup' => $flight_2_origin_site
        ? $flight_2_origin_site->label()
        : '',
    ];

    $flight_2_departure_droneport =
      $flight_2_departure_droneport_nid
        ? Node::load($flight_2_departure_droneport_nid)
        : NULL;

    $form['flights']['flight_2']['departure_droneport'] = [
      '#type' => 'item',
      '#title' => $this->t('Departure DronePort'),
      '#markup' => $flight_2_departure_droneport
        ? $flight_2_departure_droneport->label()
        : '',
    ];

    $form['flights']['flight_2']['destination_site_2'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination Site'),
      '#options' => \Drupal::service(
        'dronenav_flight_plan.option_service'
      )->getSiteOptions($authority_nid),
      '#required' => TRUE,
      '#default_value' => $saved_flight_2
        ? $saved_flight_2->get('field_destination_site')->target_id
        : NULL,
      '#ajax' => [
        'callback' => '::flight2DestinationSiteChanged',
        'wrapper' => 'flights-wrapper',
      ],
    ];

    $flight_2_destination_site_nid = $form_state->getValue(
      'destination_site_2'
    ) ?: (
      $saved_flight_2
        ? $saved_flight_2->get('field_destination_site')->target_id
        : NULL
    );

    $flight_2_arrival_droneport_options = \Drupal::service(
      'dronenav_flight_plan.option_service'
    )->getDronePortOptions($flight_2_destination_site_nid);

    unset($flight_2_arrival_droneport_options['_none']);

    $form['flights']['flight_2']['arrival_droneport_2'] = [
      '#type' => 'select',
      '#title' => $this->t('Arrival DronePort'),
      '#options' => $flight_2_arrival_droneport_options,
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('arrival_droneport_2')
        ?: (
          $saved_flight_2
            ? $saved_flight_2
              ->get('field_arrival_droneport')
              ->target_id
            : NULL
        ),
      '#ajax' => [
        'callback' => '::flight2Changed',
        'wrapper' => 'flights-wrapper',
      ],
    ];

    $form['flights']['flight_2']['flight_path_2'] = [
      '#type' => 'select',
      '#title' => $this->t('Flight Path'),
      '#options' => \Drupal::service(
        'dronenav_flight_plan.option_service'
      )->getRouteOptions(
        $flight_2_origin_site_nid,
        $flight_2_destination_site_nid
      ),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('flight_path_2')
        ?: (
          $saved_flight_2
            ? array_column(
              $saved_flight_2->get('field_flight_path')->getValue(),
              'target_id'
            )
            : []
        ),
      '#ajax' => [
        'callback' => '::flight2Changed',
        'wrapper' => 'flights-wrapper',
      ],
    ];

    for ($flight_number = 3; $flight_number <= $via_flight_count; $flight_number++) {
      $previous_number = $flight_number - 1;

      $destination_key = 'destination_site_' . $flight_number;
      $arrival_key = 'arrival_droneport_' . $flight_number;
      $flight_path_key = 'flight_path_' . $flight_number;

      $previous_destination_key =
        'destination_site_' . $previous_number;
      $previous_arrival_key =
        'arrival_droneport_' . $previous_number;

      $previous_saved_flight =
        $saved_flights[$previous_number - 1] ?? NULL;

      $origin_site_nid = $form_state->getValue(
        $previous_destination_key
      ) ?: (
        $previous_saved_flight
          ? $previous_saved_flight
            ->get('field_destination_site')
            ->target_id
          : NULL
      );

      $departure_droneport_nid = $form_state->getValue(
        $previous_arrival_key
      ) ?: (
        $previous_saved_flight
          ? $previous_saved_flight
            ->get('field_arrival_droneport')
            ->target_id
          : NULL
      );

      $saved_flight = $saved_flights[$flight_number - 1] ?? NULL;

      $form['flights']['flight_' . $flight_number] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Flight @number', [
          '@number' => $flight_number,
        ]),
      ];

      $origin_site = $origin_site_nid
        ? Node::load($origin_site_nid)
        : NULL;

      $form['flights']['flight_' . $flight_number]['origin_site'] = [
        '#type' => 'item',
        '#title' => $this->t('Origin Site'),
        '#markup' => $origin_site
          ? $origin_site->label()
          : '',
      ];

      $departure_droneport = $departure_droneport_nid
        ? Node::load($departure_droneport_nid)
        : NULL;

      $form['flights']['flight_' . $flight_number]['departure_droneport'] = [
        '#type' => 'item',
        '#title' => $this->t('Departure DronePort'),
        '#markup' => $departure_droneport
          ? $departure_droneport->label()
          : '',
      ];

      $form['flights']['flight_' . $flight_number][$destination_key] = [
        '#type' => 'select',
        '#title' => $this->t('Destination Site'),
        '#options' => \Drupal::service(
          'dronenav_flight_plan.option_service'
        )->getSiteOptions($authority_nid),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::dynamicFlightChanged',
          'wrapper' => 'flights-wrapper',
        ],
        '#default_value' => $saved_flight
          ? $saved_flight->get('field_destination_site')->target_id
          : NULL,
      ];

      $destination_site_nid = $form_state->getValue(
        $destination_key
      ) ?: (
        $saved_flight
          ? $saved_flight->get('field_destination_site')->target_id
          : NULL
      );

      $arrival_droneport_options = \Drupal::service(
        'dronenav_flight_plan.option_service'
      )->getDronePortOptions($destination_site_nid);

      unset($arrival_droneport_options['_none']);

      $form['flights']['flight_' . $flight_number][$arrival_key] = [
        '#type' => 'select',
        '#title' => $this->t('Arrival DronePort'),
        '#options' => $arrival_droneport_options,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::dynamicFlightChanged',
          'wrapper' => 'flights-wrapper',
        ],
        '#default_value' => $saved_flight
          ? $saved_flight->get('field_arrival_droneport')->target_id
          : NULL,
      ];

      $form['flights']['flight_' . $flight_number][$flight_path_key] = [
        '#type' => 'select',
        '#title' => $this->t('Flight Path'),
        '#options' => \Drupal::service(
          'dronenav_flight_plan.option_service'
        )->getRouteOptions(
          $origin_site_nid,
          $destination_site_nid
        ),
        '#multiple' => TRUE,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::dynamicFlightChanged',
          'wrapper' => 'flights-wrapper',
        ],
        '#default_value' => $saved_flight
          ? array_column(
            $saved_flight->get('field_flight_path')->getValue(),
            'target_id'
          )
          : [],
      ];
    }

    $last_flight_number = $via_flight_count;
    $last_saved_flight =
      $saved_flights[$last_flight_number - 1] ?? NULL;

    $last_destination = $form_state->getValue(
      'destination_site_' . $last_flight_number
    ) ?: (
      $last_saved_flight
        ? $last_saved_flight
          ->get('field_destination_site')
          ->target_id
        : NULL
    );

    $last_arrival = $form_state->getValue(
      'arrival_droneport_' . $last_flight_number
    ) ?: (
      $last_saved_flight
        ? $last_saved_flight
          ->get('field_arrival_droneport')
          ->target_id
        : NULL
    );

    $last_flight_path = $form_state->getValue(
      'flight_path_' . $last_flight_number
    ) ?: (
      $last_saved_flight
        ? array_column(
          $last_saved_flight
            ->get('field_flight_path')
            ->getValue(),
          'target_id'
        )
        : []
    );

    $can_add_via = (
      !empty($last_destination) &&
      !empty($last_arrival) &&
      !empty($last_flight_path)
    );

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Via Flight Plan'),
      '#button_type' => 'primary',
    ];

    $form['actions']['add_via'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Via'),
      '#submit' => ['::addVia'],
      '#ajax' => [
        'callback' => '::addViaCallback',
        'wrapper' => 'flights-wrapper',
      ],
      '#disabled' => !$can_add_via,
      '#prefix' => '<span id="add-via-wrapper">',
      '#suffix' => '</span>',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancelForm'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  protected function getReferenceOptions(
    Node $flight_plan,
    string $field_name
  ): array {

    $form = \Drupal::service('entity.form_builder')
      ->getForm($flight_plan, 'edit');

    return $form[$field_name]['widget']['#options'] ?? [];
  }

  public function authorityChanged(
    array &$form,
    FormStateInterface $form_state
  ) {
    return $form['flights'];
  }

  public function originSiteChanged(
    array &$form,
    FormStateInterface $form_state
  ) {
    return $form['flights'];
  }

  public function destinationSiteChanged(
    array &$form,
    FormStateInterface $form_state
  ) {
    return $form['flights'];
  }

  public function arrivalDronePortChanged(
    array &$form,
    FormStateInterface $form_state
  ) {
    return $form['flights'];
  }

  public function flight2DestinationSiteChanged(
    array &$form,
    FormStateInterface $form_state
  ) {
    return $form['flights'];
  }

  public function addVia(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $via_flight_count = (int) $form_state->get(
      'via_flight_count'
    );

    $form_state->set(
      'via_flight_count',
      $via_flight_count + 1
    );

    $form_state->setRebuild();
  }

  public function addViaCallback(
    array &$form,
    FormStateInterface $form_state
  ): AjaxResponse {

    $response = new AjaxResponse();

    $response->addCommand(
      new ReplaceCommand(
        '#flights-wrapper',
        $form['flights']
      )
    );

    $response->addCommand(
      new ReplaceCommand(
        '#add-via-wrapper',
        $form['actions']['add_via']
      )
    );

    return $response;
  }

  public function flight2Changed(
    array &$form,
    FormStateInterface $form_state
  ): AjaxResponse {

    $response = new AjaxResponse();

    $response->addCommand(
      new ReplaceCommand(
        '#flights-wrapper',
        $form['flights']
      )
    );

    $response->addCommand(
      new ReplaceCommand(
        '#add-via-wrapper',
        $form['actions']['add_via']
      )
    );

    return $response;
  }

  public function dynamicFlightChanged(
    array &$form,
    FormStateInterface $form_state
  ): AjaxResponse {

    $response = new AjaxResponse();

    $response->addCommand(
      new ReplaceCommand(
        '#flights-wrapper',
        $form['flights']
      )
    );

    $response->addCommand(
      new ReplaceCommand(
        '#add-via-wrapper',
        $form['actions']['add_via']
      )
    );

    return $response;
  }

  public function submitForm(
    array &$form,
    FormStateInterface $form_state
  ) {

    $flight_plan = $form_state->get('flight_plan');

    if (
      !$flight_plan instanceof Node ||
      $flight_plan->bundle() !== 'working_flight_plan'
    ) {
      throw new \InvalidArgumentException(
        'A working Flight Plan is required.'
      );
    }

    $flight_plan->setTitle(
      $form_state->getValue('flight_plan_name')
    );

    $flight_plan->set(
      'field_aviator',
      [
        'target_id' => $form_state->getValue('aviator'),
      ]
    );

    $flight_plan->set(
      'field_aircraft',
      [
        'target_id' => $form_state->getValue('aircraft'),
      ]
    );

    $flight_plan->set(
      'field_authority',
      [
        'target_id' => $form_state->getValue('authority'),
      ]
    );

    $flight_plan->set(
      'field_flight_class',
      [
        'target_id' => $form_state->getValue('flight_class'),
      ]
    );

    $departure_datetime = $form_state->getValue(
      'departure_datetime'
    );

    $departure_datetime->setTimezone(
      new \DateTimeZone('UTC')
    );

    $flight_plan->set(
      'field_departure_datetime',
      $departure_datetime->format('Y-m-d\TH:i:s')
    );

    $via_flight_count = (int) $form_state->get(
      'via_flight_count'
    );

    $flights = [];

    $flight_1 = Paragraph::create([
      'type' => 'flight_plan_flight',

      'field_origin_site' => [
        'target_id' => $form_state->getValue('origin_site'),
      ],

      'field_departure_droneport' => [
        'target_id' => $form_state->getValue('departure_droneport'),
      ],

      'field_destination_site' => [
        'target_id' => $form_state->getValue('destination_site'),
      ],

      'field_arrival_droneport' => [
        'target_id' => $form_state->getValue('arrival_droneport'),
      ],

      'field_flight_path' => array_map(
        static fn($route_nid) => ['target_id' => $route_nid],
        $form_state->getValue('flight_path') ?: []
      ),
    ]);

    $flights[] = ['entity' => $flight_1];

    $flight_2 = Paragraph::create([
      'type' => 'flight_plan_flight',

      'field_origin_site' => [
        'target_id' => $form_state->getValue('destination_site'),
      ],

      'field_departure_droneport' => [
        'target_id' => $form_state->getValue('arrival_droneport'),
      ],

      'field_destination_site' => [
        'target_id' => $form_state->getValue('destination_site_2'),
      ],

      'field_arrival_droneport' => [
        'target_id' => $form_state->getValue('arrival_droneport_2'),
      ],

      'field_flight_path' => array_map(
        static fn($route_nid) => ['target_id' => $route_nid],
        $form_state->getValue('flight_path_2') ?: []
      ),
    ]);

    $flights[] = ['entity' => $flight_2];

    for ($flight_number = 3; $flight_number <= $via_flight_count; $flight_number++) {
      $previous_number = $flight_number - 1;

      $destination_key = 'destination_site_' . $flight_number;
      $arrival_key = 'arrival_droneport_' . $flight_number;
      $flight_path_key = 'flight_path_' . $flight_number;

      $previous_destination_key =
        'destination_site_' . $previous_number;
      $previous_arrival_key =
        'arrival_droneport_' . $previous_number;

      $flight = Paragraph::create([
        'type' => 'flight_plan_flight',
        'field_origin_site' => [
          'target_id' => $form_state->getValue($previous_destination_key),
        ],
        'field_departure_droneport' => [
          'target_id' => $form_state->getValue($previous_arrival_key),
        ],
        'field_destination_site' => [
          'target_id' => $form_state->getValue($destination_key),
        ],
        'field_arrival_droneport' => [
          'target_id' => $form_state->getValue($arrival_key),
        ],
        'field_flight_path' => array_map(
          static fn($route_nid) => ['target_id' => $route_nid],
          $form_state->getValue($flight_path_key) ?: []
        ),
      ]);

      $flights[] = ['entity' => $flight];
    }


    $flight_plan->set(
      'field_origin_site',
      [
        'target_id' => $form_state->getValue('origin_site'),
      ]
    );

    $last_destination_key =
      'destination_site_' . $via_flight_count;

    $flight_plan->set(
      'field_destination_site',
      ['target_id' => $form_state->getValue($last_destination_key)]
    );

    $flight_plan->set(
      'field_flights',
      $flights
    );

    $flight_plan->save();

    $this->messenger()->addStatus(
      $this->t('Via Flight Plan saved.')
    );

    $form_state->setRedirect(
      'dronenav_flight_plan.list'
    );

  }

  public function cancelForm(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $form_state->setRedirect(
      'dronenav_flight_plan.list'
    );

  }

  protected function getCurrentAviator(): ?Node {

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'aviator')
      ->condition(
        'field_aviator_account',
        $this->currentUser()->id()
      )
      ->range(0, 1)
      ->execute();

    if (empty($nids)) {
      return NULL;
    }

    return Node::load(reset($nids));

  }

}

