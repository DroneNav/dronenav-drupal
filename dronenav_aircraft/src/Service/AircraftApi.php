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
   * Returns calibration profiles for an aircraft.
   */
  public function getDecelerationProfiles(string $aircraft_id): array {

    try {
      $response = $this->httpClient->request(
        'GET',
        self::API_BASE_URL . '/aircraft/' . $aircraft_id . '/deceleration-profiles'
      );

      $data = json_decode($response->getBody()->getContents(), TRUE);

      return $data['profiles'] ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to retrieve calibration profiles for aircraft @id: @message', [
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return [];
    }

  }

  /**
   * Creates a deceleration calibration profile.
   */
  public function createDecelerationProfile(string $aircraft_id, array $data): array {

    try {
      $response = $this->httpClient->request(
        'POST',
        self::API_BASE_URL . '/aircraft/' . $aircraft_id . '/deceleration-profiles',
        [
          'json' => $data,
        ]
      );

      return json_decode($response->getBody()->getContents(), TRUE) ?? [];
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      $response = $e->getResponse();

      if ($response && $response->getStatusCode() === 409) {
        return [
          'error' => 'A calibration profile already exists for this cruise speed.',
        ];
      }

      $this->logger->error('Unable to create calibration profile for aircraft @id: @message', [
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return ['error' => 'Unable to create calibration profile.'];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to create calibration profile for aircraft @id: @message', [
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return ['error' => 'Unable to create calibration profile.'];
    }

  }

  /**
   * Updates a deceleration calibration profile.
   */
  public function updateDecelerationProfile(
    string $aircraft_id,
    string $profile_id,
    array $data
  ): array {

    try {
      $response = $this->httpClient->request(
        'PATCH',
        self::API_BASE_URL . '/aircraft/' . $aircraft_id
          . '/deceleration-profiles/' . $profile_id,
        [
          'json' => $data,
        ]
      );

      return json_decode($response->getBody()->getContents(), TRUE) ?? [];
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      $response = $e->getResponse();

      if ($response && $response->getStatusCode() === 409) {
        return [
          'error' => 'A calibration profile already exists for this cruise speed.',
        ];
      }

      $this->logger->error('Unable to update calibration profile @profile for aircraft @id: @message', [
        '@profile' => $profile_id,
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return ['error' => 'Unable to update calibration profile.'];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to update calibration profile @profile for aircraft @id: @message', [
        '@profile' => $profile_id,
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return ['error' => 'Unable to update calibration profile.'];
    }

  }

  /**
   * Deletes a deceleration calibration profile.
   */
  public function deleteDecelerationProfile(
    string $aircraft_id,
    string $profile_id
  ): array {

    try {
      $response = $this->httpClient->request(
        'DELETE',
        self::API_BASE_URL . '/aircraft/' . $aircraft_id
          . '/deceleration-profiles/' . $profile_id
      );

      return json_decode($response->getBody()->getContents(), TRUE) ?? [];
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to delete calibration profile @profile for aircraft @id: @message', [
        '@profile' => $profile_id,
        '@id' => $aircraft_id,
        '@message' => $e->getMessage(),
      ]);

      return ['error' => 'Unable to delete calibration profile.'];
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

