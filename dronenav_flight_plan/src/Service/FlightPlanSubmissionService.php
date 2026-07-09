<?php

namespace Drupal\dronenav_flight_plan\Service;

use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;

class FlightPlanSubmissionService {

  private const API_BASE = 'https://api.dronenav.org/api';

  protected ClientInterface $httpClient;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }



  public function submit(Node $flight_plan): array {

    $payload = [
      'flight_plan_id' => $flight_plan->id(),
      'title' => $flight_plan->label(),
    ];

    try {

      $response = $this->httpClient->post(
        self::API_BASE . '/flight-executions',
        [
          'json' => $payload,
          'verify' => FALSE,
        ]
      );

      return json_decode(
        $response->getBody()->getContents(),
        TRUE
      );

    }
    catch (\Exception $e) {

      return [
        'status' => 'error',
        'message' => $e->getMessage(),
      ];

    }

  }


}

