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

    $status = strtolower(
      trim((string) $entity->get($field_name)->value)
    );

    if ($status !== 'active') {
      $errors[] = sprintf(
        '%s "%s" is inactive and cannot be used for this Flight Plan.',
        $label,
        $entity->label()
      );
    }
  }

}

