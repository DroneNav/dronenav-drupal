<?php

namespace Drupal\dronenav_flight_plan\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Provides access to the DroneNav Flight Execution API.
 */
class FlightExecutionService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The module logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The Flight Execution API base URL.
   *
   * @var string
   */
  protected string $apiBaseUrl;

  /**
   * Constructs the Flight Execution service.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Drupal HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The module logger.
   * @param string $api_base_url
   *   The Flight Execution API base URL.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerInterface $logger,
    string $api_base_url,
  ) {
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->apiBaseUrl = rtrim($api_base_url, '/');
  }

  /**
   * Launches a Flight Execution Record.
   *
   * @param string $flight_execution_uuid
   *   The Flight Execution Record UUID.
   *
   * @return array
   *   A normalized result containing:
   *   - success: Whether the API accepted the request.
   *   - message: A user-facing result message.
   *   - data: The decoded API response, when available.
   */
  public function launch(
    string $flight_execution_uuid,
    string $aviator_id,
    string $aircraft_id
  ): array {

    $flight_execution_uuid = trim($flight_execution_uuid);

    if ($flight_execution_uuid === '') {
      return [
        'success' => FALSE,
        'message' => 'The Flight Execution Record UUID is missing.',
        'data' => NULL,
      ];
    }

    $url = sprintf(
      '%s/api/flight-executions/%s/launch',
      $this->apiBaseUrl,
      rawurlencode($flight_execution_uuid)
    );

    try {
      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'aviator_id' => $aviator_id,
          'aircraft_id' => $aircraft_id,
        ],
        'timeout' => 15,
        'http_errors' => FALSE,
      ]);

      $status_code = $response->getStatusCode();
      $body = (string) $response->getBody();

      $data = [];

      if ($body !== '') {
        $decoded = json_decode($body, TRUE);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
          $data = $decoded;
        }
      }

      if ($status_code >= 200 && $status_code < 300) {
        return [
          'success' => TRUE,
          'message' => $data['message'] ?? 'Flight launch initiated.',
          'data' => $data,
        ];
      }

      $message = $data['message']
        ?? $data['error']
        ?? 'The Flight Execution API rejected the launch request.';

      $this->logger->warning(
        'Flight Execution launch request for @uuid returned HTTP @status: @message',
        [
          '@uuid' => $flight_execution_uuid,
          '@status' => $status_code,
          '@message' => $message,
        ]
      );

      return [
        'success' => FALSE,
        'message' => $message,
        'data' => $data,
      ];
    }
    catch (GuzzleException $e) {
      $this->logger->error(
        'Flight Execution launch request for @uuid failed: @message',
        [
          '@uuid' => $flight_execution_uuid,
          '@message' => $e->getMessage(),
        ]
      );

      return [
        'success' => FALSE,
        'message' => 'The Flight Execution API could not be reached.',
        'data' => NULL,
      ];
    }
  }

}
