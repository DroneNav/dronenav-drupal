<?php

namespace Drupal\dronenav_flight_plan\Service;

use Drupal\node\Entity\Node;

class FlightPlanValidator {

  /**
   * Validates Drupal-governed requirements before API submission.
   */
  public function validateForSubmission(Node $flight_plan): array {
    $errors = [];

    $aviator = $this->getReferencedEntity(
      $flight_plan,
      'field_aviator',
      'Aviator',
      $errors
    );

    $aircraft = $this->getReferencedEntity(
      $flight_plan,
      'field_aircraft',
      'Aircraft',
      $errors
    );

    if ($aviator) {
      $this->validateActiveStatus(
        $aviator,
        'field_aviator_status',
        'Aviator',
        $errors
      );
    }

    if ($aircraft) {
      $this->validateActiveStatus(
        $aircraft,
        'field_status',
        'Aircraft',
        $errors
      );
    }

    $this->validateFlightStructure(
      $flight_plan,
      $errors
    );

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Loads a required referenced entity.
   */
  protected function getReferencedEntity(
    Node $flight_plan,
    string $field_name,
    string $label,
    array &$errors
  ): ?object {

    if (
      !$flight_plan->hasField($field_name) ||
      $flight_plan->get($field_name)->isEmpty()
    ) {
      $errors[] = sprintf(
        '%s is required before the Flight Plan can be submitted.',
        $label
      );

      return NULL;
    }

    $entity = $flight_plan->get($field_name)->entity;

    if (!$entity) {
      $errors[] = sprintf(
        'The referenced %s could not be loaded.',
        $label
      );

      return NULL;
    }

    return $entity;
  }

  /**
   * Validates that an entity has an active governance status.
   */
  protected function validateActiveStatus(
    object $entity,
    string $field_name,
    string $label,
    array &$errors
  ): void {

    if (
      !$entity->hasField($field_name) ||
      $entity->get($field_name)->isEmpty()
    ) {
      $errors[] = sprintf(
        '%s "%s" does not have a governance status.',
        $label,
        $entity->label()
      );

      return;
    }

    $field = $entity->get($field_name);

    if ($field->getFieldDefinition()->getType() === 'entity_reference') {
      $status_entity = $field->entity;

      $status = $status_entity
        ? strtolower(trim((string) $status_entity->label()))
        : '';
    }
    else {
      $status = strtolower(
        trim((string) $field->value)
      );
    }

    if ($status !== 'active') {
      $errors[] = sprintf(
        '%s "%s" is inactive and cannot be used for this Flight Plan.',
        $label,
        $entity->label()
      );
    }

  }

  /**
   * Validates the required structure of a Flight Plan.
   */
  protected function validateFlightStructure(
    Node $flight_plan,
    array &$errors
  ): void {

    $origin_site = $this->getReferencedEntity(
      $flight_plan,
      'field_origin_site',
      'Origin Site',
      $errors
    );

    $destination_site = $this->getReferencedEntity(
      $flight_plan,
      'field_destination_site',
      'Destination Site',
      $errors
    );

    /*
     * The required Site references have already produced validation errors.
     * Do not perform relationship validation until both can be loaded.
     */
    if (!$origin_site || !$destination_site) {
      return;
    }

    $departure_droneport = NULL;
    $arrival_droneport = NULL;

    if (
      $flight_plan->hasField('field_departure_droneport') &&
      !$flight_plan->get('field_departure_droneport')->isEmpty()
    ) {
      $departure_droneport =
        $flight_plan->get('field_departure_droneport')->entity;
    }

    if (
      $flight_plan->hasField('field_arrival_droneport') &&
      !$flight_plan->get('field_arrival_droneport')->isEmpty()
    ) {
      $arrival_droneport =
        $flight_plan->get('field_arrival_droneport')->entity;
    }

    $has_departure_datetime = (
      $flight_plan->hasField('field_departure_datetime') &&
      !$flight_plan->get('field_departure_datetime')->isEmpty()
    );

    $has_flight_path = (
      $flight_plan->hasField('field_flight_path') &&
      !$flight_plan->get('field_flight_path')->isEmpty()
    );

    /*
     * A Flight Plan must be one of two valid structures:
     *
     * Reusable:
     * - no departure datetime
     * - no departure DronePort
     * - no arrival DronePort
     * - no Routes
     *
     * Scheduled:
     * - departure datetime
     * - departure DronePort
     * - arrival DronePort
     * - at least one Route
     */
    if ($has_departure_datetime) {
      if (!$departure_droneport) {
        $errors[] =
          'A Flight Plan with a Departure Date and Time requires a Departure DronePort.';
      }

      if (!$arrival_droneport) {
        $errors[] =
          'A Flight Plan with a Departure Date and Time requires an Arrival DronePort.';
      }

      if (!$has_flight_path) {
        $errors[] =
          'A Flight Plan with a Departure Date and Time requires at least one Route in the Flight Path.';
      }
    }
    else {
      if ($departure_droneport) {
        $errors[] =
          'A Flight Plan without a Departure Date and Time cannot include a Departure DronePort.';
      }

      if ($arrival_droneport) {
        $errors[] =
          'A Flight Plan without a Departure Date and Time cannot include an Arrival DronePort.';
      }

      if ($has_flight_path) {
        $errors[] =
          'A Flight Plan without a Departure Date and Time cannot include Routes in the Flight Path.';
      }
    }

    /*
     * When a DronePort is selected, it must belong to the corresponding Site.
     */
    $origin_site_uuid = (
      $origin_site->hasField('field_overlay_uuid') &&
      !$origin_site->get('field_overlay_uuid')->isEmpty()
    )
      ? (string) $origin_site->get('field_overlay_uuid')->value
      : '';

    $destination_site_uuid = (
      $destination_site->hasField('field_overlay_uuid') &&
      !$destination_site->get('field_overlay_uuid')->isEmpty()
    )
      ? (string) $destination_site->get('field_overlay_uuid')->value
      : '';

    if ($departure_droneport) {
      $departure_parent_site_uuid = (
        $departure_droneport->hasField('field_parent_site_id') &&
        !$departure_droneport->get('field_parent_site_id')->isEmpty()
      )
        ? (string) $departure_droneport
          ->get('field_parent_site_id')
          ->value
        : '';

      if (
        $origin_site_uuid === '' ||
        $departure_parent_site_uuid === '' ||
        $departure_parent_site_uuid !== $origin_site_uuid
      ) {
        $errors[] = sprintf(
          'Departure DronePort "%s" does not belong to Origin Site "%s".',
          $departure_droneport->label(),
          $origin_site->label()
        );
      }
    }

    if ($arrival_droneport) {
      $arrival_parent_site_uuid = (
        $arrival_droneport->hasField('field_parent_site_id') &&
        !$arrival_droneport->get('field_parent_site_id')->isEmpty()
      )
        ? (string) $arrival_droneport
          ->get('field_parent_site_id')
          ->value
        : '';

      if (
        $destination_site_uuid === '' ||
        $arrival_parent_site_uuid === '' ||
        $arrival_parent_site_uuid !== $destination_site_uuid
      ) {
        $errors[] = sprintf(
          'Arrival DronePort "%s" does not belong to Destination Site "%s".',
          $arrival_droneport->label(),
          $destination_site->label()
        );
      }
    }

  }



}

