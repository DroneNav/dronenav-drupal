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

    try {
      $authority = $this->getRequiredReferencedEntity(
        $flight_plan,
        'field_authority'
      );

      $aviator = $this->getRequiredReferencedEntity(
        $flight_plan,
        'field_aviator'
      );

      $aircraft = $this->getRequiredReferencedEntity(
        $flight_plan,
        'field_aircraft'
      );

      $origin_site = $this->getRequiredReferencedEntity(
        $flight_plan,
        'field_origin_site'
      );

      $destination_site = $this->getRequiredReferencedEntity(
        $flight_plan,
        'field_destination_site'
      );

      $departure_droneport = $this->getOptionalReferencedEntity(
        $flight_plan,
        'field_departure_droneport'
      );

      $arrival_droneport = $this->getOptionalReferencedEntity(
        $flight_plan,
        'field_arrival_droneport'
      );

      $flight_path_ids = [];

      if (
        $flight_plan->hasField('field_flight_path') &&
        !$flight_plan->get('field_flight_path')->isEmpty()
      ) {
        foreach ($flight_plan->get('field_flight_path')->referencedEntities() as $route) {
          $flight_path_ids[] = $this->getRequiredFieldValue(
            $route,
            'field_overlay_uuid'
          );
        }
      }

      $flight_class = $this->getRequiredReferencedEntity(
        $flight_plan,
        'field_flight_class'
      );

      $requested_departure_datetime = $this->buildRequestedDepartureDatetime(
        $flight_plan,
        $origin_site,
        $departure_droneport
      );

      $payload = [
        'flight_plan_id' => (int) $flight_plan->id(),
        'flight_plan_title' => $flight_plan->label(),
        'submitted_by' => \Drupal::currentUser()->getAccountName(),

        'authority_id' => $this->getRequiredFieldValue(
          $authority,
          'field_authority_id'
        ),

        'aviator_id' => $this->getRequiredFieldValue(
          $aviator,
          'field_aviator_id'
        ),

        'aircraft_id' => $this->getRequiredFieldValue(
          $aircraft,
          'field_aircraft_id'
        ),

        'flight_class' => $flight_class->label(),

        'origin_site_id' => $this->getRequiredFieldValue(
          $origin_site,
          'field_overlay_uuid'
        ),

        'destination_site_id' => $this->getRequiredFieldValue(
          $destination_site,
          'field_overlay_uuid'
        ),

        'departure_droneport_id' => $departure_droneport
          ? $this->getRequiredFieldValue(
            $departure_droneport,
            'field_overlay_uuid'
          )
          : NULL,

        'arrival_droneport_id' => $arrival_droneport
          ? $this->getRequiredFieldValue(
            $arrival_droneport,
            'field_overlay_uuid'
          )
          : NULL,

        'flight_path_ids' => $flight_path_ids,

        'requested_departure_datetime' => $requested_departure_datetime,
      ];

      $response = $this->httpClient->post(
        self::API_BASE . '/flight-executions',
        [
          'json' => $payload,
          'timeout' => 15,
        ]
      );

      return json_decode(
        $response->getBody()->getContents(),
        TRUE
      ) ?? [
        'status' => 'error',
        'message' => 'The Flight Execution API returned an invalid response.',
      ];
    }
    catch (\Exception $e) {
      \Drupal::logger('dronenav_flight_plan')->error(
        'Flight Plan submission failed: @message',
        ['@message' => $e->getMessage()]
      );

      return [
        'status' => 'error',
        'message' => $e->getMessage(),
      ];
    }

  }

  /**
   * Returns a required referenced entity.
   */
  protected function getRequiredReferencedEntity(
    Node $flight_plan,
    string $field_name
  ): object {

    if (
      !$flight_plan->hasField($field_name) ||
      $flight_plan->get($field_name)->isEmpty()
    ) {
      throw new \RuntimeException(
        sprintf('Missing required Flight Plan field: %s', $field_name)
      );
    }

    $entity = $flight_plan->get($field_name)->entity;

    if (!$entity) {
      throw new \RuntimeException(
        sprintf('Unable to load referenced entity for: %s', $field_name)
      );
    }

    return $entity;
  }

  /**
   * Returns an optional referenced entity or NULL.
   */
  protected function getOptionalReferencedEntity(
    Node $flight_plan,
    string $field_name
  ): ?object {

    if (
      !$flight_plan->hasField($field_name) ||
      $flight_plan->get($field_name)->isEmpty()
    ) {
      return NULL;
    }

    return $flight_plan->get($field_name)->entity;
  }

  /**
   * Returns a required scalar field value from an entity.
   */
  protected function getRequiredFieldValue(
    object $entity,
    string $field_name
  ): string {

    if (
      !$entity->hasField($field_name) ||
      $entity->get($field_name)->isEmpty()
    ) {
      throw new \RuntimeException(
        sprintf(
          'Referenced %s entity is missing required field: %s',
          $entity->getEntityTypeId(),
          $field_name
        )
      );
    }

    return (string) $entity->get($field_name)->value;
  }

  /**
   * Builds an ISO-8601 departure datetime using the origin timezone.
   */
  protected function buildRequestedDepartureDatetime(
    Node $flight_plan,
    object $origin_site,
    ?object $departure_droneport
  ): ?string {

    if (
      !$flight_plan->hasField('field_departure_datetime') ||
      $flight_plan->get('field_departure_datetime')->isEmpty()
    ) {
      return NULL;
    }

    $raw_value = trim(
      (string) $flight_plan->get('field_departure_datetime')->value
    );

    if ($raw_value === '') {
      return NULL;
    }

    if ($departure_droneport) {
      $droneport_id = $this->getRequiredFieldValue(
        $departure_droneport,
        'field_overlay_uuid'
      );

      $timezone_name = $this->loadOperationalTimezone(
        '/droneports/' . rawurlencode($droneport_id),
        'droneport'
      );
    }
    else {
      $origin_site_id = $this->getRequiredFieldValue(
        $origin_site,
        'field_overlay_uuid'
      );

      $timezone_name = $this->loadOperationalTimezone(
        '/sites/' . rawurlencode($origin_site_id),
        'site'
      );
    }

    try {
      $storage_timezone = new \DateTimeZone('UTC');
      $display_timezone = new \DateTimeZone(date_default_timezone_get());
      $origin_timezone = new \DateTimeZone($timezone_name);

      /*
       * Drupal stores datetime fields in UTC. Convert the stored instant back
       * into the timezone used by the Drupal form so we recover the wall-clock
       * date and time entered by the Aviator.
       */
      $stored_departure = \DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i:s',
        $raw_value,
        $storage_timezone
      );

      if (!$stored_departure) {
        throw new \RuntimeException(
          sprintf(
            'Unable to read stored departure datetime: %s',
            $raw_value
          )
        );
      }

      $entered_wall_time = $stored_departure
        ->setTimezone($display_timezone)
        ->format('Y-m-d\TH:i:s');

      /*
       * Reinterpret that wall-clock date and time in the operational timezone
       * of the Departure DronePort or Origin Site.
       */
      $operational_departure = \DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i:s',
        $entered_wall_time,
        $origin_timezone
      );

      if (!$operational_departure) {
        throw new \RuntimeException(
          sprintf(
            'Unable to interpret departure datetime in timezone %s.',
            $timezone_name
          )
        );
      }

      return $operational_departure->format(\DateTimeInterface::ATOM);
    }
    catch (\Exception $e) {
      throw new \RuntimeException(
        sprintf(
          'Unable to build the requested departure datetime: %s',
          $e->getMessage()
        )
      );
    }
  }

  /**
   * Retrieves the operational timezone from a Site or DronePort detail endpoint.
   */
  protected function loadOperationalTimezone(
    string $path,
    string $response_key
  ): string {

    try {
      $response = $this->httpClient->get(
        self::API_BASE . $path,
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
          'The API returned an invalid timezone response.'
        );
      }

      $entity_data = $data[$response_key] ?? $data;

      $timezone_name = trim(
        (string) ($entity_data['timezone'] ?? '')
      );

      if ($timezone_name === '') {
        throw new \RuntimeException(
          sprintf(
            'The API did not return a timezone for the %s.',
            ucfirst($response_key)
          )
        );
      }

      new \DateTimeZone($timezone_name);

      return $timezone_name;
    }
    catch (\RuntimeException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      throw new \RuntimeException(
        sprintf(
          'Unable to retrieve the operational timezone: %s',
          $e->getMessage()
        )
      );
    }
  }

}

