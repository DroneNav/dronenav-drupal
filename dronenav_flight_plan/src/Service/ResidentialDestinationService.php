<?php

namespace Drupal\dronenav_flight_plan\Service;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;


class ResidentialDestinationService {

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerInterface $logger,
    protected string $apiBaseUrl
  ) {}

  /**
   * Retrieves all residential destinations for a DroneNav Site.
   */
  public function getDestinationsBySite(string $site_uuid): array {

    try {
      $response = $this->httpClient->get(
        rtrim($this->apiBaseUrl, '/') . '/api/residential-destinations',
        [
          'query' => [
            'site_id' => $site_uuid,
          ],
          'headers' => [
            'Accept' => 'application/json',
          ],
          'timeout' => 15,
        ]
      );

      $data = json_decode(
        (string) $response->getBody(),
        TRUE
      );

      return is_array($data['residential_destinations'] ?? NULL)
        ? $data['residential_destinations']
        : [];
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Residential destination lookup failed for Site @site: @message',
        [
          '@site' => $site_uuid,
          '@message' => $e->getMessage(),
        ]
      );

      return [];
    }
  }

  /**
   * Checks whether coordinates are inside or on a Site boundary.
   *
   * Returns NULL if the API lookup fails.
   */
  public function isPointWithinSite(
    string $site_uuid,
    float $latitude,
    float $longitude
  ): ?bool {

    try {
      $response = $this->httpClient->post(
        rtrim($this->apiBaseUrl, '/') .
          '/api/sites/' . rawurlencode($site_uuid) . '/point-containment',
        [
          'json' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
          ],
          'headers' => [
            'Accept' => 'application/json',
          ],
          'timeout' => 15,
        ]
      );

      $data = json_decode(
        (string) $response->getBody(),
        TRUE
      );

      if (
        !is_array($data) ||
        !is_bool($data['inside'] ?? NULL) ||
        !is_bool($data['on_boundary'] ?? NULL)
      ) {
        return NULL;
      }

      return $data['inside'] || $data['on_boundary'];
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Site containment lookup failed for Site @site: @message',
        [
          '@site' => $site_uuid,
          '@message' => $e->getMessage(),
        ]
      );

      return NULL;
    }
  }

}

