<?php

namespace Drupal\dronenav_api\Service;

use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Provides authenticated server-to-server access to the DroneNav Flask API.
 *
 * Public methods should be added for individual supported API operations.
 * Controllers must not accept an arbitrary Flask path from the browser.
 */
final class DroneNavApiGatewayService {

  private const ALLOWED_OVERLAY_TYPES = [
    'site',
    'zone',
    'droneport',
    'route',
  ];

  /**
   * Validates and normalizes an overlay type.
   */
  private function validateOverlayType(
    string $overlay_type
  ): ?string {
    $overlay_type = strtolower(trim($overlay_type));

    return in_array(
      $overlay_type,
      self::ALLOWED_OVERLAY_TYPES,
      TRUE
    )
      ? $overlay_type
      : NULL;
  }

  /**
   * Constructs the DroneNav API gateway service.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Sends a controlled request to the DroneNav Flask API.
   *
   * This method is intentionally private. Add one public method for each
   * explicitly supported API operation.
   */
  private function request(
    string $method,
    string $path,
    array $options = [],
  ): array {
    $base_url = rtrim(
      trim((string) Settings::get('dronenav_api_base_url', '')),
      '/'
    );

    $gateway_token = trim((string) Settings::get(
      'dronenav_api_gateway_token',
      ''
    ));

    if ($base_url === '' || $gateway_token === '') {
      $this->logger->critical(
        'The DroneNav API gateway is missing its base URL or authentication token.'
      );

      return [
        'success' => FALSE,
        'status' => 503,
        'data' => NULL,
        'message' => 'The DroneNav API gateway is not configured.',
      ];
    }

    if (
      $path === ''
      || $path[0] !== '/'
      || str_contains($path, '..')
      || str_contains($path, '://')
    ) {
      $this->logger->error(
        'The DroneNav API gateway rejected an invalid internal path: @path',
        [
          '@path' => $path,
        ]
      );

      return [
        'success' => FALSE,
        'status' => 500,
        'data' => NULL,
        'message' => 'The gateway operation is incorrectly configured.',
      ];
    }

    $url = $base_url . $path;

    $request_options = array_replace_recursive([
      'connect_timeout' => 5,
      'timeout' => 15,
      'http_errors' => FALSE,
    ], $options);

    $request_options['headers'] = array_merge(
      $request_options['headers'] ?? [],
      [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $gateway_token,
      ]
    );

    try {
      $response = $this->httpClient->request(
        strtoupper($method),
        $url,
        $request_options
      );

      $status_code = $response->getStatusCode();
      $body = trim((string) $response->getBody());
      $data = NULL;

      if ($body !== '') {
        $decoded = json_decode($body, TRUE);

        if (
          json_last_error() === JSON_ERROR_NONE
          && is_array($decoded)
        ) {
          $data = $decoded;
        }
      }

      if ($status_code >= 200 && $status_code < 300) {
        return [
          'success' => TRUE,
          'status' => $status_code,
          'data' => $data,
          'message' => NULL,
        ];
      }

      $message = is_array($data)
        ? (
          $data['message']
          ?? $data['error']
          ?? 'The DroneNav API rejected the request.'
        )
        : 'The DroneNav API rejected the request.';

      $this->logger->warning(
        'DroneNav API request @method @path returned HTTP @status.',
        [
          '@method' => strtoupper($method),
          '@path' => $path,
          '@status' => $status_code,
        ]
      );

      return [
        'success' => FALSE,
        'status' => $status_code,
        'data' => $data,
        'message' => $message,
      ];
    }
    catch (GuzzleException $exception) {
      $this->logger->error(
        'DroneNav API request @method @path failed: @message',
        [
          '@method' => strtoupper($method),
          '@path' => $path,
          '@message' => $exception->getMessage(),
        ]
      );

      return [
        'success' => FALSE,
        'status' => 502,
        'data' => NULL,
        'message' => 'The DroneNav API could not be reached.',
      ];
    }
  }

  /**
   * Loads all DroneNav Sites.
   */
  public function getSites(): array {
    return $this->request(
      'GET',
      '/api/sites'
    );
  }

  /**
   * Loads one DroneNav Site.
   */
  public function getSite(string $site_id): array {
    $site_id = trim($site_id);

    if ($site_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Site ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/sites/' . $site_id
    );
  }

  /**
   * Loads DroneNav Site Package.
   */
  public function getSitePackage(string $site_id): array {
    $site_id = trim($site_id);

    if ($site_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Site ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/sites/' . $site_id . '/package'
    );
  }

  /**
   * Creates a new DroneNav Site.
   */
  public function createSite(array $site): array {

    return $this->request(
      'POST',
      '/api/sites',
      [
        'json' => $site,
      ]
    );
  }

  /**
   * Updates a DroneNav Site.
   */
  public function updateSite(string $site_id, array $site): array {

    return $this->request(
      'PATCH',
      '/api/sites/' . $site_id,
      [
        'json' => $site,
      ]
    );
  }

  /**
   * Deletes one DroneNav Site.
   */
  public function deleteSite(string $site_id): array {
    $site_id = trim($site_id);

    if ($site_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Site ID is required.',
      ];
    }

    return $this->request(
      'DELETE',
      '/api/sites/' . $site_id
    );
  }

  /**
   * Loads all DroneNav Zones.
   */
  public function getZones(): array {
    return $this->request(
      'GET',
      '/api/zones'
    );
  }

  /**
   * Loads one DroneNav Zone.
   */
  public function getZone(string $zone_id): array {
    $zone_id = trim($zone_id);

    if ($zone_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Zone ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/zones/' . $zone_id
    );
  }

  /**
   * Creates a new DroneNav Zone.
   */
  public function createZone(array $zone): array {

    return $this->request(
      'POST',
      '/api/zones',
      [
        'json' => $zone,
      ]
    );
  }

  /**
   * Updates a DroneNav Zone.
   */
  public function updateZone(string $zone_id, array $zone): array {

    return $this->request(
      'PATCH',
      '/api/zones/' . $zone_id,
      [
        'json' => $zone,
      ]
    );
  }

  /**
   * Deletes one DroneNav Zone.
   */
  public function deleteZone(string $zone_id): array {
    $zone_id = trim($zone_id);

    if ($zone_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Zone ID is required.',
      ];
    }

    return $this->request(
      'DELETE',
      '/api/zones/' . $zone_id
    );
  }

  /**
   * Loads all DroneNav Droneports.
   */
  public function getDroneports(): array {
    return $this->request(
      'GET',
      '/api/droneports'
    );
  }

  /**
   * Loads one DroneNav Droneport.
   */
  public function getDroneport(string $droneport_id): array {
    $droneport_id = trim($droneport_id);

    if ($droneport_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Droneport ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/droneports/' . $droneport_id
    );
  }

  /**
   * Creates a new DroneNav Droneport.
   */
  public function createDroneport(array $droneport): array {

    return $this->request(
      'POST',
      '/api/droneports',
      [
        'json' => $droneport,
      ]
    );
  }

  /**
   * Updates a DroneNav Droneport.
   */
  public function updateDroneport(string $droneport_id, array $droneport): array {

    return $this->request(
      'PATCH',
      '/api/droneports/' . $droneport_id,
      [
        'json' => $droneport,
      ]
    );
  }

  /**
   * Deletes one DroneNav Droneport.
   */
  public function deleteDroneport(string $droneport_id): array {
    $droneport_id = trim($droneport_id);

    if ($droneport_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Droneport ID is required.',
      ];
    }

    return $this->request(
      'DELETE',
      '/api/droneports/' . $droneport_id
    );
  }

  /**
   * Loads all DroneNav Routes.
   */
  public function getRoutes(): array {
    return $this->request(
      'GET',
      '/api/routes'
    );
  }

  /**
   * Loads one DroneNav Route.
   */
  public function getRoute(string $route_id): array {
    $route_id = trim($route_id);

    if ($route_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Route ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/routes/' . $route_id
    );
  }

  /**
   * Loads DroneNav Route Context Package.
   */
  public function getRouteContextPackage(string $route_id): array {
    $route_id = trim($route_id);

    if ($route_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Route ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/routes/' . $route_id . '/context-package'
    );
  }

  /**
   * Creates a new DroneNav Route.
   */
  public function createRoute(array $route): array {

    return $this->request(
      'POST',
      '/api/routes',
      [
        'json' => $route,
      ]
    );
  }

  /**
   * Updates a DroneNav Route.
   */
  public function updateRoute(string $route_id, array $route): array {

    return $this->request(
      'PATCH',
      '/api/routes/' . $route_id,
      [
        'json' => $route,
      ]
    );
  }

  /**
   * Deletes one DroneNav Route.
   */
  public function deleteRoute(string $route_id): array {
    $route_id = trim($route_id);

    if ($route_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Route ID is required.',
      ];
    }

    return $this->request(
      'DELETE',
      '/api/routes/' . $route_id
    );
  }

  /**
   * Loads all DroneNav Reference Data.
   */
  public function getReferenceData(): array {
    return $this->request(
      'GET',
      '/api/reference-data'
    );
  }

  /**
   * Get the flight context.
   */
  public function getFlightContext(array $body): array {

    return $this->request(
      'POST',
      '/api/flight-context',
      [
        'json' => $body,
      ]
    );
  }

  /**
   * Loads the actual flight path.
   */
  public function getActualPath(string $flight_execution_id): array {
    $flight_execution_id = trim($flight_execution_id);

    if ($flight_execution_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Flight Execution ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/actual-paths/' . $flight_execution_id
    );
  }

  /**
   * Loads one DroneNav Overlay Site Package.
   */
  public function getOverlayPackage(string $overlay_id): array {
    $overlay_id = trim($overlay_id);

    if ($overlay_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'The Overlay Site ID is required.',
      ];
    }

    return $this->request(
      'GET',
      '/api/governance/overlays/' . $overlay_id . '/package'
    );
  }

  /**
   * Submit Survey of DroneNav Overlay Site Package.
   */
  public function surveyOverlayPackage(string $overlay_id, array $package): array {

    return $this->request(
      'POST',
      '/api/governance/overlays/' . $overlay_id . '/survey-package',
      [
        'json' => $package,
      ]
    );
  }

  /**
   * Expire Survey of DroneNav Overlay Site Package.
   */
  public function expireSurveyOverlayPackage(string $overlay_id, array $package): array {

    return $this->request(
      'POST',
      '/api/governance/overlays/' . $overlay_id . '/expire-survey-package',
      [
        'json' => $package,
      ]
    );
  }

  /**
   * Activate DroneNav Site Package.
   */
  public function activateSitePackage(string $overlay_id, array $package): array {

    return $this->request(
      'POST',
      '/api/governance/overlays/sites/' . $overlay_id . '/activate-package',
      [
        'json' => $package,
      ]
    );
  }

  /**
   * Deactivate DroneNav Site Package.
   */
  public function deactivateSitePackage(string $overlay_id, array $package): array {

    return $this->request(
      'POST',
      '/api/governance/overlays/sites/' . $overlay_id . '/deactivate-package',
      [
        'json' => $package,
      ]
    );
  }

  /**
   * Marks an overlay as surveyed.
   */
  public function surveyOverlay(
    string $overlay_type,
    string $overlay_id,
    array $payload
  ): array {

    $overlay_type = $this->validateOverlayType($overlay_type);
    $overlay_id = trim($overlay_id);

    if ($overlay_type === NULL || $overlay_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'A valid overlay type and ID are required.',
      ];
    }

    return $this->request(
      'POST',
      '/api/governance/overlays/'
        . $overlay_type
        . 's/'
        . $overlay_id
        . '/survey',
      [
        'json' => $payload,
      ]
    );
  }

  /**
   * Marks an overlay as surveyed expired.
   */
  public function expireSurveyOverlay(
    string $overlay_type,
    string $overlay_id,
    array $payload
  ): array {

    $overlay_type = $this->validateOverlayType($overlay_type);
    $overlay_id = trim($overlay_id);

    if ($overlay_type === NULL || $overlay_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'A valid overlay type and ID are required.',
      ];
    }

    return $this->request(
      'POST',
      '/api/governance/overlays/'
        . $overlay_type
        . 's/'
        . $overlay_id
        . '/expire-survey',
      [
        'json' => $payload,
      ]
    );
  }

  /**
   * Marks an overlay as activated.
   */
  public function activateOverlay(
    string $overlay_type,
    string $overlay_id,
    array $payload
  ): array {

    $overlay_type = $this->validateOverlayType($overlay_type);
    $overlay_id = trim($overlay_id);

    if ($overlay_type === NULL || $overlay_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'A valid overlay type and ID are required.',
      ];
    }

    return $this->request(
      'POST',
      '/api/governance/overlays/'
        . $overlay_type
        . 's/'
        . $overlay_id
        . '/activate',
      [
        'json' => $payload,
      ]
    );
  }

  /**
   * Marks an overlay as deactivated.
   */
  public function deactivateOverlay(
    string $overlay_type,
    string $overlay_id,
    array $payload
  ): array {

    $overlay_type = $this->validateOverlayType($overlay_type);
    $overlay_id = trim($overlay_id);

    if ($overlay_type === NULL || $overlay_id === '') {
      return [
        'success' => FALSE,
        'status' => 400,
        'data' => NULL,
        'message' => 'A valid overlay type and ID are required.',
      ];
    }

    return $this->request(
      'POST',
      '/api/governance/overlays/'
        . $overlay_type
        . 's/'
        . $overlay_id
        . '/deactivate',
      [
        'json' => $payload,
      ]
    );
  }


  /**
   * Checks a scheduled Flight Plan for applicable FAA TFR conflicts.
   */
  public function checkFlightPlanTfrConflicts(
    array $flight_plan
  ): array {

    return $this->request(
      'POST',
      '/api/tfrs/flight-plan-conflicts',
      [
        'json' => $flight_plan,
      ]
    );
  }





}

