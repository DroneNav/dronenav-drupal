<?php

namespace Drupal\dronenav_aircraft\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service wrapper for the DroneNav Aircraft API.
 */
class AircraftApi {

  /**
   * Base URL of the DroneNav API.
   */
  private const API_BASE_URL = 'https://api.dronenav.org/api';

  protected ClientInterface $httpClient;

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
   * Returns aircraft belonging to an Aviator.
   */
  public function getAircraftByAviator(string $aviator_id): array {

    try {
      $response = $this->httpClient->request('GET', self::API_BASE_URL . '/aircraft', [
        'query' => [
          'aviator_id' => $aviator_id,
        ],
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      return $data['aircraft'] ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to retrieve aircraft for Aviator @id: @message', [
        '@id' => $aviator_id,
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }

  /**
   * Returns a single aircraft.
   */
  public function getAircraft(string $aircraft_id): array {

    try {
      $response = $this->httpClient->request('GET', self::API_BASE_URL . '/aircraft/' . $aircraft_id);

      return json_decode($response->getBody()->getContents(), TRUE) ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to retrieve aircraft @id: @message', [
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }

  /**
   * Creates an aircraft.
   */
  public function createAircraft(array $data): array {

    try {
      $response = $this->httpClient->request('POST', self::API_BASE_URL . '/aircraft', [
        'json' => $data,
      ]);

      return json_decode($response->getBody()->getContents(), TRUE) ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to create aircraft: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }

  /**
   * Updates an aircraft.
   */
  public function updateAircraft(string $aircraft_id, array $data): array {

    try {
      $response = $this->httpClient->request('PATCH', self::API_BASE_URL . '/aircraft/' . $aircraft_id, [
        'json' => $data,
      ]);

      return json_decode($response->getBody()->getContents(), TRUE) ?? [];
    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      $response = $e->getResponse();

      $response_body = $response
        ? (string) $response->getBody()
        : $e->getMessage();

      $this->logger->error('Aircraft update failed @id: @response', [
        '@id' => $aircraft_id,
        '@response' => $response_body,
      ]);

      return [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to update aircraft @id: @message', [
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Deletes an aircraft.
   *
   * The API/database is responsible for constraint validation.
   */
  public function deleteAircraft(string $aircraft_id): bool {

    try {
      $this->httpClient->request('DELETE', self::API_BASE_URL . '/aircraft/' . $aircraft_id);
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to delete aircraft @id: @message', [
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return FALSE;
    }

  }

}

