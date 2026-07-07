<?php

namespace Drupal\dronenav_api\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Provides access to DroneNav API reference data.
 */
class ReferenceDataService {

  /**
   * DroneNav API base URL.
   */
  protected const API_BASE_URL = 'https://api.dronenav.org/api';

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs the reference data service.
   */
  public function __construct(ClientInterface $http_client, LoggerInterface $logger) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * Returns the full reference data dictionary from the DroneNav API.
   */
  public function getReferenceData(): array {
    try {
      $response = $this->httpClient->request('GET', self::API_BASE_URL . '/reference-data', [
        'timeout' => 10,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      $body = (string) $response->getBody();
      $data = json_decode($body, TRUE);

      if (!is_array($data)) {
        $this->logger->error('DroneNav reference data response was not valid JSON.');
        return [];
      }

      return $data;
    }
    catch (GuzzleException $e) {
      $this->logger->error('DroneNav reference data API request failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Returns flight class options keyed for Drupal form select fields.
   */
  public function getFlightClasses(): array {
    $reference_data = $this->getReferenceData();

    if (empty($reference_data['flight_class']) || !is_array($reference_data['flight_class'])) {
      return [];
    }

    $options = [];

    foreach ($reference_data['flight_class'] as $flight_class) {
      $options[$flight_class] = $flight_class;
    }

    return $options;
  }

}

