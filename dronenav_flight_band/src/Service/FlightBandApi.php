<?php

namespace Drupal\dronenav_flight_band\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service wrapper for the DroneNav Flight Band API.
 */
class FlightBandApi {

  /**
   * Base URL of the DroneNav API.
   */
  private const API_BASE_URL = 'https://api.dronenav.org/api';

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
   * Constructor.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerInterface $logger
  ) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * Returns all Flight Bands.
   *
   * @return array
   *   Flight Band records.
   */
  public function getFlightBands(): array {

    try {
      $response = $this->httpClient->request('GET', self::API_BASE_URL . '/flight-bands');

      $data = json_decode($response->getBody()->getContents(), TRUE);

      return $data['flight_bands'] ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to retrieve Flight Bands: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }


  /**
   * Returns a single Flight Band.
   */
  public function getFlightBand(string $uuid): array {

    try {
      $response = $this->httpClient->request('GET', self::API_BASE_URL . '/flight-bands/' . $uuid);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      return $data['flight_band'] ?? $data ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to retrieve Flight Band @uuid: @message', [
        '@uuid' => $uuid,
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }

  /**
   * Creates a Flight Band.
   */
  public function createFlightBand(array $data): array {

    try {
      $response = $this->httpClient->request('POST', self::API_BASE_URL . '/flight-bands', [
        'json' => $data,
      ]);

      $response_data = json_decode($response->getBody()->getContents(), TRUE);

      return $response_data['flight_band'] ?? $response_data ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to create Flight Band: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }

  /**
   * Updates a Flight Band.
   */
  public function updateFlightBand(string $uuid, array $data): array {

    try {
      $response = $this->httpClient->request('PATCH', self::API_BASE_URL . '/flight-bands/' . $uuid, [
        'json' => $data,
      ]);

      $response_data = json_decode($response->getBody()->getContents(), TRUE);

      return $response_data['flight_band'] ?? $response_data ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to update Flight Band @uuid: @message', [
        '@uuid' => $uuid,
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }

  /**
   * Deletes a Flight Band.
   */
  public function deleteFlightBand(string $uuid): bool {

    try {
      $this->httpClient->request('DELETE', self::API_BASE_URL . '/flight-bands/' . $uuid);
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to delete Flight Band @uuid: @message', [
        '@uuid' => $uuid,
        '@message' => $e->getMessage(),
      ]);

      return FALSE;
    }

  }

}

