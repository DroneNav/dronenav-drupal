<?php

namespace Drupal\dronenav_flight_plan\Service;

use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;

class FlightPathOrderingService {

  private const API_BASE = 'https://api.dronenav.org/api';

  private const DIRECTION_FORWARD = 0;
  private const DIRECTION_REVERSE = 1;
  private const DIRECTION_BIDIRECTIONAL = 2;

  protected ClientInterface $httpClient;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Orders the selected Routes from departure to arrival DronePort.
   *
   * @return array
   *   [
   *     'valid' => bool,
   *     'errors' => string[],
   *     'ordered_routes' => NodeInterface[],
   *     'ordered_route_ids' => string[],
   *   ]
   */
  public function orderFlightPath(NodeInterface $flight_plan): array {
    $errors = [];
    $ordered_routes = [];
    $ordered_route_ids = [];

    if (
      !$flight_plan->hasField('field_flight_path') ||
      $flight_plan->get('field_flight_path')->isEmpty()
    ) {
      return $this->success([], []);
    }

    $selected_routes =
      $flight_plan->get('field_flight_path')->referencedEntities();

    $departure_droneport = $this->getReferencedEntity(
      $flight_plan,
      'field_departure_droneport'
    );

    $arrival_droneport = $this->getReferencedEntity(
      $flight_plan,
      'field_arrival_droneport'
    );

    if (!$departure_droneport) {
      $errors[] =
        'A Departure DronePort is required when a Flight Path is selected.';
    }

    if (!$arrival_droneport) {
      $errors[] =
        'An Arrival DronePort is required when a Flight Path is selected.';
    }

    if (!empty($errors)) {
      return $this->failure($errors);
    }

    $departure_droneport_id = $this->getOverlayUuid(
      $departure_droneport,
      'Departure DronePort'
    );

    $arrival_droneport_id = $this->getOverlayUuid(
      $arrival_droneport,
      'Arrival DronePort'
    );

    if (!$departure_droneport_id) {
      $errors[] = 'The Departure DronePort does not have an API identifier.';
    }

    if (!$arrival_droneport_id) {
      $errors[] = 'The Arrival DronePort does not have an API identifier.';
    }

    if (!empty($errors)) {
      return $this->failure($errors);
    }

    /*
     * Build an internal collection containing:
     *
     * - The Drupal Route proxy node.
     * - The current API Route details.
     * - The stable API Route UUID.
     */
    $routes = [];

    foreach ($selected_routes as $route_node) {
      $route_id = $this->getOverlayUuid($route_node, 'Route');

      if (!$route_id) {
        $errors[] = sprintf(
          'Route "%s" does not have an API identifier.',
          $route_node->label()
        );

        continue;
      }

      try {
        $route_details = $this->loadRouteDetails($route_id);
      }
      catch (\RuntimeException $e) {
        $errors[] = $e->getMessage();
        continue;
      }

      $routes[$route_id] = [
        'node' => $route_node,
        'route_id' => $route_id,
        'origin_droneport_id' =>
          $route_details['origin_droneport_id'] ?? NULL,
        'destination_droneport_id' =>
          $route_details['destination_droneport_id'] ?? NULL,
        'direction' =>
          isset($route_details['direction'])
            ? (int) $route_details['direction']
            : NULL,
      ];
    }

    if (!empty($errors)) {
      return $this->failure($errors);
    }

    foreach ($routes as $route) {
      if (
        empty($route['origin_droneport_id']) ||
        empty($route['destination_droneport_id'])
      ) {
        $errors[] = sprintf(
          'Route "%s" is missing an origin or destination DronePort.',
          $route['node']->label()
        );
      }

      if (
        !in_array(
          $route['direction'],
          [
            self::DIRECTION_FORWARD,
            self::DIRECTION_REVERSE,
            self::DIRECTION_BIDIRECTIONAL,
          ],
          TRUE
        )
      ) {
        $errors[] = sprintf(
          'Route "%s" has an unsupported direction value.',
          $route['node']->label()
        );
      }
    }

    if (!empty($errors)) {
      return $this->failure($errors);
    }

    $unused_routes = $routes;
    $current_droneport_id = $departure_droneport_id;

    /*
     * At each DronePort, exactly one unused selected Route must provide a
     * legal traversal away from the current DronePort.
     */
    while (!empty($unused_routes)) {
      $candidates = [];

      foreach ($unused_routes as $route_id => $route) {
        $next_droneport_id = $this->getNextDronePortId(
          $route,
          $current_droneport_id
        );

        if ($next_droneport_id !== NULL) {
          $candidates[$route_id] = [
            'route' => $route,
            'next_droneport_id' => $next_droneport_id,
          ];
        }
      }

      if (empty($candidates)) {
        $errors[] = sprintf(
          'The selected Flight Path cannot be ordered from Departure DronePort "%s" to Arrival DronePort "%s".',
          $departure_droneport->label(),
          $arrival_droneport->label()
        );

        return $this->failure($errors);
      }

      if (count($candidates) > 1) {
        $route_names = array_map(
          static fn(array $candidate): string =>
            $candidate['route']['node']->label(),
          array_values($candidates)
        );

        $errors[] = sprintf(
          'The Flight Path is ambiguous at DronePort %s. More than one Route may be traversed next: %s.',
          $current_droneport_id,
          implode(', ', $route_names)
        );

        return $this->failure($errors);
      }

      $route_id = array_key_first($candidates);
      $candidate = $candidates[$route_id];

      $ordered_routes[] = $candidate['route']['node'];
      $ordered_route_ids[] = $route_id;

      $current_droneport_id = $candidate['next_droneport_id'];

      unset($unused_routes[$route_id]);

      /*
       * Reaching the selected Arrival DronePort before consuming all selected
       * Routes means the Aviator selected Routes outside the intended chain.
       */
      if (
        $current_droneport_id === $arrival_droneport_id &&
        !empty($unused_routes)
      ) {
        $errors[] =
          'The Flight Path reaches the Arrival DronePort before all selected Routes have been used.';

        return $this->failure($errors);
      }
    }

    if ($current_droneport_id !== $arrival_droneport_id) {
      $errors[] = sprintf(
          'The selected Flight Path does not terminate at Arrival DronePort "%s".',
          $arrival_droneport->label() );

      return $this->failure($errors);
    }

    return $this->success(
      $ordered_routes,
      $ordered_route_ids
    );
  }

  /**
   * Returns the next DronePort when the Route is legally traversable.
   */
  protected function getNextDronePortId(
    array $route,
    string $current_droneport_id
  ): ?string {
    $origin_id = $route['origin_droneport_id'];
    $destination_id = $route['destination_droneport_id'];
    $direction = $route['direction'];

    if (
      $direction === self::DIRECTION_FORWARD &&
      $current_droneport_id === $origin_id
    ) {
      return $destination_id;
    }

    if (
      $direction === self::DIRECTION_REVERSE &&
      $current_droneport_id === $destination_id
    ) {
      return $origin_id;
    }

    if ($direction === self::DIRECTION_BIDIRECTIONAL) {
      if ($current_droneport_id === $origin_id) {
        return $destination_id;
      }

      if ($current_droneport_id === $destination_id) {
        return $origin_id;
      }
    }

    return NULL;
  }

  /**
   * Retrieves current Route details from the operational API.
   */
  protected function loadRouteDetails(string $route_id): array {
    try {
      $response = $this->httpClient->get(
        self::API_BASE . '/routes/' . rawurlencode($route_id),
        [
          'timeout' => 15,
        ]
      );

      $data = json_decode(
        $response->getBody()->getContents(),
        TRUE
      );

      if (!is_array($data)) {
        throw new \RuntimeException(
          sprintf(
            'The API returned an invalid response for Route %s.',
            $route_id
          )
        );
      }

      /*
       * Supports either:
       *
       * { "route": { ... } }
       *
       * or:
       *
       * { "route_id": "...", ... }
       */
      $route = $data['route'] ?? $data;

      if (
        empty($route['route_id']) ||
        $route['route_id'] !== $route_id
      ) {
        throw new \RuntimeException(
          sprintf(
            'The API did not return the requested Route %s.',
            $route_id
          )
        );
      }

      return $route;
    }
    catch (\RuntimeException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      throw new \RuntimeException(
        sprintf(
          'Unable to retrieve Route %s: %s',
          $route_id,
          $e->getMessage()
        )
      );
    }
  }

  /**
   * Returns a referenced entity or NULL.
   */
  protected function getReferencedEntity(
    NodeInterface $node,
    string $field_name
  ): ?object {
    if (
      !$node->hasField($field_name) ||
      $node->get($field_name)->isEmpty()
    ) {
      return NULL;
    }

    return $node->get($field_name)->entity;
  }

  /**
   * Returns an overlay proxy's stable API UUID.
   */
  protected function getOverlayUuid(
    object $entity,
    string $label
  ): ?string {
    if (
      !$entity->hasField('field_overlay_uuid') ||
      $entity->get('field_overlay_uuid')->isEmpty()
    ) {
      return NULL;
    }

    $uuid = trim(
      (string) $entity->get('field_overlay_uuid')->value
    );

    return $uuid !== '' ? $uuid : NULL;
  }

  protected function success(
    array $ordered_routes,
    array $ordered_route_ids
  ): array {
    return [
      'valid' => TRUE,
      'errors' => [],
      'ordered_routes' => $ordered_routes,
      'ordered_route_ids' => $ordered_route_ids,
    ];
  }

  protected function failure(array $errors): array {
    return [
      'valid' => FALSE,
      'errors' => $errors,
      'ordered_routes' => [],
      'ordered_route_ids' => [],
    ];
  }

}

