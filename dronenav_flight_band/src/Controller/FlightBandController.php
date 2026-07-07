<?php

namespace Drupal\dronenav_flight_band\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\dronenav_flight_band\Service\FlightBandApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Flight Band administration pages.
 */
class FlightBandController extends ControllerBase {

  /**
   * Flight Band API service.
   *
   * @var \Drupal\dronenav_flight_band\Service\FlightBandApi
   */
  protected FlightBandApi $flightBandApi;

  /**
   * Constructor.
   */
  public function __construct(FlightBandApi $flight_band_api) {
    $this->flightBandApi = $flight_band_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_flight_band.api')
    );
  }

  /**
   * Lists Flight Bands.
   *
   * @return array
   *   Render array.
   */
  public function list(): array {
    $rows = [];

    $header = [
      $this->t('Flight Class'),
      $this->t('Band Name'),
      $this->t('Min AGL'),
      $this->t('Max AGL'),
      $this->t('Days'),
      $this->t('Start'),
      $this->t('End'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $bands = $this->flightBandApi->getFlightBands();

    // Sort Flight Bands by operational behavior rather than by descriptive name.
    //
    // Flight execution planning is driven primarily by the flight class,
    // followed by the days of operation and finally by the assigned altitude
    // range. This grouping allows administrators to visualize how airspace
    // is partitioned for different traffic patterns (e.g., commercial,
    // recreational, emergency) when configuring network congestion policies.   
 
    usort($bands, function (array $a, array $b): int {
      return [
        $a['flight_class'] ?? '',
        implode(',', $a['days'] ?? []),
        (int) ($a['min_agl_ft'] ?? 0),
      ] <=> [
        $b['flight_class'] ?? '',
        implode(',', $b['days'] ?? []),
        (int) ($b['min_agl_ft'] ?? 0),
      ];
    });

    foreach ($bands as $band) {
      $uuid = $band['flight_band_id'] ?? '';

      $operations = [];

      if ($uuid) {
        $operations[] = Link::fromTextAndUrl(
          $this->t('Edit'),
          Url::fromRoute('dronenav_flight_band.edit', ['uuid' => $uuid])
        )->toString();

        $operations[] = Link::fromTextAndUrl(
          $this->t('Delete'),
          Url::fromRoute('dronenav_flight_band.delete', ['uuid' => $uuid])
        )->toString();
      }

      $rows[] = [
        $band['flight_class'] ?? '',
        $band['band_name'] ?? '',
        $band['min_agl_ft'] ?? '',
        $band['max_agl_ft'] ?? '',
        implode(',', $band['days'] ?? []),
        $band['start_time'] ?? '',
        $band['end_time'] ?? '',
        $band['operational_status'] ?? '',
        [
          'data' => [
            '#markup' => implode(' | ', $operations),
          ],
        ],
      ];




    }

    return [
      'add_link' => [
        '#type' => 'link',
        '#title' => $this->t('Add Flight Band'),
        '#url' => Url::fromRoute('dronenav_flight_band.add'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No Flight Bands found.'),
      ],
    ];
  }

}

