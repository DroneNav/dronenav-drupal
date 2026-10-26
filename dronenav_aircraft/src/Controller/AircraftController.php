<?php

namespace Drupal\dronenav_aircraft\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dronenav_aircraft\Service\AircraftApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for Aircraft administration pages.
 */
class AircraftController extends ControllerBase {

  protected AircraftApi $aircraftApi;

  protected RequestStack $requestStack;

  /**
   * Constructor.
   */
  public function __construct(
    AircraftApi $aircraft_api,
    RequestStack $request_stack
  ) {
    $this->aircraftApi = $aircraft_api;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_aircraft.api'),
      $container->get('request_stack')
    );
  }

  /**
   * Lists aircraft for the selected Aviator.
   */
  public function listing(): array {

    $aviators = [];

    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['type' => 'aviator']);

    foreach ($nodes as $node) {
      $aviator_id = $node->get('field_aviator_id')->value;
      $aviator_name = $node->get('field_aviator_name')->value;

      if ($aviator_id) {
        $aviators[$aviator_id] = $aviator_name ?: $node->label();
      }
    }

    asort($aviators);

    $selected_aviator = $this->requestStack
      ->getCurrentRequest()
      ->query
      ->get('aviator_id', '');

    if (!isset($aviators[$selected_aviator])) {
      $selected_aviator = '';
    }

    $build = [];

    $build['aviator_selector'] = $this->formBuilder()
      ->getForm('\Drupal\dronenav_aircraft\Form\AviatorSelectForm');

    if (!$selected_aviator) {
      return $build;
    }

    $aircraft = $this->aircraftApi
      ->getAircraftByAviator($selected_aviator);

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Aircraft'),
      '#url' => Url::fromRoute('dronenav_aircraft.add', [], [
        'query' => ['aviator_id' => $selected_aviator],
      ]),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $header = [
      $this->t('Aircraft Name'),
      $this->t('Manufacturer'),
      $this->t('Model'),
      $this->t('Aircraft Type'),
      $this->t('Maximum Speed (mph)'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $rows = [];

    foreach ($aircraft as $item) {
      $aircraft_id = $item['aircraft_id'] ?? '';

      $operations = [];

      if ($aircraft_id) {
        $operations[] = Link::fromTextAndUrl(
          $this->t('Edit'),
          Url::fromRoute('dronenav_aircraft.edit', [
            'aircraft_id' => $aircraft_id,
          ], [
            'query' => ['aviator_id' => $selected_aviator],
          ])
        )->toString();

      }

      $rows[] = [
        $item['aircraft_name'] ?? '',
        $item['manufacturer'] ?? '',
        $item['model'] ?? '',
        $item['aircraft_type'] ?? '',
        isset($item['maximum_speed_ft_s'])
          ? number_format(
              (float) $item['maximum_speed_ft_s'] * 15 / 22,
              2
            )
          : '',
        $item['operational_status'] ?? '',

        [
          'data' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'container-inline',
                'dronenav-aircraft-operations',
              ],
            ],
            'edit' => [
              '#markup' => implode(' | ', $operations),
            ],
            'separator' => [
              '#markup' => ' | ',
            ],
            'delete' => $this->formBuilder()->getForm(
              '\Drupal\dronenav_aircraft\Form\AircraftDeleteButtonForm',
              $aircraft_id,
              $selected_aviator
            ),
          ],
        ],

      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No aircraft found for this Aviator.'),
    ];

    return $build;
  }

}

