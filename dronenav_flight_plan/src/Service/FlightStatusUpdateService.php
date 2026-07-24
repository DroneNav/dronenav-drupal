<?php

namespace Drupal\dronenav_flight_plan\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies NAVProxy execution status reports to Drupal Flight Plans.
 */
final class FlightStatusUpdateService {

  /**
   * The Flight Plan content type machine name.
   */
  private const FLIGHT_PLAN_BUNDLE = 'working_flight_plan';

  /**
   * Field containing the Flight Execution Record UUID.
   */
  private const EXECUTION_ID_FIELD = 'field_flight_execution_id';

  /**
   * Field containing the Flight Plan status taxonomy reference.
   */
  private const STATUS_FIELD = 'field_flight_plan_status';

  /**
   * Allowed NAVProxy status values and Drupal taxonomy labels.
   */
  private const STATUS_MAP = [
    'active' => 'Active',
    'authorized' => 'Authorized',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
    'draft' => 'Draft',
    'rejected' => 'Rejected',
    'submitted' => 'Submitted',
  ];

  /**
   * Allowed Flight Plan status transitions.
   *
   * Current status labels are normalized to lowercase.
   */
  private const ALLOWED_TRANSITIONS = [
    'submitted' => ['active', 'authorized'],
    'authorized' => ['active'],
    'active' => ['submitted', 'completed'],
  ];

  /**
   * Constructs the status update service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Updates a Flight Plan status from a NAVProxy callback.
   */
  public function updateStatus(
    string $flight_execution_id,
    string $status,
    ?string $occurred_at = NULL,
  ): array {
    $flight_execution_id = trim($flight_execution_id);
    $status = strtolower(trim($status));

    if (!isset(self::STATUS_MAP[$status])) {
      return [
        'success' => FALSE,
        'message' => sprintf(
          'Unsupported Flight Plan status: %s.',
          $status
        ),
        'data' => NULL,
        'http_status' => 400,
      ];
    }

    $flight_plan = $this->loadFlightPlanByExecutionId(
      $flight_execution_id
    );

    if (!$flight_plan) {
      $this->logger->warning(
        'No Flight Plan was found for Flight Execution Record @execution_id.',
        [
          '@execution_id' => $flight_execution_id,
        ]
      );

      return [
        'success' => FALSE,
        'message' => 'No Flight Plan was found for the Flight Execution Record.',
        'data' => NULL,
        'http_status' => 404,
      ];
    }

    if (!$flight_plan->hasField(self::STATUS_FIELD)) {
      $this->logger->error(
        'Flight Plan @nid does not contain status field @field.',
        [
          '@nid' => $flight_plan->id(),
          '@field' => self::STATUS_FIELD,
        ]
      );

      return [
        'success' => FALSE,
        'message' => 'The Flight Plan status field is not configured.',
        'data' => NULL,
        'http_status' => 500,
      ];
    }

    $current_status = $this->getCurrentStatusLabel($flight_plan);
    $current_status_normalized = strtolower($current_status);
    $target_status_label = self::STATUS_MAP[$status];

    /*
     * Make callbacks idempotent. NAVProxy may retry a callback when the first
     * Drupal acknowledgment is lost.
     */
    if ($current_status_normalized === $status) {
      return [
        'success' => TRUE,
        'message' => sprintf(
          'The Flight Plan is already %s.',
          $target_status_label
        ),
        'data' => [
          'flight_plan_id' => (int) $flight_plan->id(),
          'flight_execution_id' => $flight_execution_id,
          'previous_status' => $current_status,
          'status' => $target_status_label,
          'changed' => FALSE,
          'occurred_at' => $occurred_at,
        ],
        'http_status' => 200,
      ];
    }

    if (!$this->isTransitionAllowed(
      $current_status_normalized,
      $status
    )) {
      $this->logger->warning(
        'Rejected Flight Plan status transition for node @nid from @current to @target.',
        [
          '@nid' => $flight_plan->id(),
          '@current' => $current_status,
          '@target' => $target_status_label,
        ]
      );

      return [
        'success' => FALSE,
        'message' => sprintf(
          'The Flight Plan cannot transition from %s to %s.',
          $current_status !== '' ? $current_status : 'an unknown status',
          $target_status_label
        ),
        'data' => [
          'flight_plan_id' => (int) $flight_plan->id(),
          'flight_execution_id' => $flight_execution_id,
          'current_status' => $current_status,
          'requested_status' => $target_status_label,
        ],
        'http_status' => 409,
      ];
    }

    $status_term_id = $this->loadStatusTermId($target_status_label);

    if ($status_term_id === NULL) {
      $this->logger->error(
        'The Flight Plan status taxonomy term @status could not be found.',
        [
          '@status' => $target_status_label,
        ]
      );

      return [
        'success' => FALSE,
        'message' => sprintf(
          'The Drupal status term %s is not configured.',
          $target_status_label
        ),
        'data' => NULL,
        'http_status' => 500,
      ];
    }

    $flight_plan->set(self::STATUS_FIELD, [
      'target_id' => $status_term_id,
    ]);

    try {
      $flight_plan->save();
    }
    catch (\Throwable $exception) {
      $this->logger->error(
        'Failed to update Flight Plan @nid to @status: @message',
        [
          '@nid' => $flight_plan->id(),
          '@status' => $target_status_label,
          '@message' => $exception->getMessage(),
        ]
      );

      return [
        'success' => FALSE,
        'message' => 'The Flight Plan status could not be saved.',
        'data' => NULL,
        'http_status' => 500,
      ];
    }

    $this->logger->notice(
      'Flight Plan @nid transitioned from @current to @target for Flight Execution Record @execution_id.',
      [
        '@nid' => $flight_plan->id(),
        '@current' => $current_status,
        '@target' => $target_status_label,
        '@execution_id' => $flight_execution_id,
      ]
    );

    return [
      'success' => TRUE,
      'message' => sprintf(
        'The Flight Plan status was updated to %s.',
        $target_status_label
      ),
      'data' => [
        'flight_plan_id' => (int) $flight_plan->id(),
        'flight_execution_id' => $flight_execution_id,
        'previous_status' => $current_status,
        'status' => $target_status_label,
        'changed' => TRUE,
        'occurred_at' => $occurred_at,
      ],
      'http_status' => 200,
    ];
  }

  /**
   * Loads a Flight Plan using its Flight Execution Record UUID.
   */
  private function loadFlightPlanByExecutionId(
    string $flight_execution_id,
  ): ?NodeInterface {
    $node_storage = $this->entityTypeManager->getStorage('node');

    $node_ids = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::FLIGHT_PLAN_BUNDLE)
      ->condition(self::EXECUTION_ID_FIELD, $flight_execution_id)
      ->range(0, 2)
      ->execute();

    if (count($node_ids) > 1) {
      $this->logger->critical(
        'Multiple Flight Plans reference Flight Execution Record @execution_id.',
        [
          '@execution_id' => $flight_execution_id,
        ]
      );

      return NULL;
    }

    if ($node_ids === []) {
      return NULL;
    }

    $flight_plan = $node_storage->load(reset($node_ids));

    return $flight_plan instanceof NodeInterface
      ? $flight_plan
      : NULL;
  }

  /**
   * Returns the Flight Plan's current status term label.
   */
  private function getCurrentStatusLabel(NodeInterface $flight_plan): string {
    $status_field = $flight_plan->get(self::STATUS_FIELD);

    if ($status_field->isEmpty()) {
      return '';
    }

    $status_term = $status_field->entity;

    if (!$status_term) {
      return '';
    }

    return trim((string) $status_term->label());
  }

  /**
   * Determines whether a status transition is permitted.
   */
  private function isTransitionAllowed(
    string $current_status,
    string $target_status,
  ): bool {
    return in_array(
      $target_status,
      self::ALLOWED_TRANSITIONS[$current_status] ?? [],
      TRUE
    );
  }

  /**
   * Finds the taxonomy term ID for a Flight Plan status label.
   */
  private function loadStatusTermId(string $status_label): ?int {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions(
      'node',
      self::FLIGHT_PLAN_BUNDLE
    );

    $status_field_definition = $field_definitions[self::STATUS_FIELD] ?? NULL;

    if (!$status_field_definition) {
      return NULL;
    }

    $handler_settings = $status_field_definition->getSetting(
      'handler_settings'
    );

    $target_bundles = $handler_settings['target_bundles'] ?? [];

    if ($target_bundles === []) {
      return NULL;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $term_ids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', array_values($target_bundles), 'IN')
      ->condition('name', $status_label)
      ->range(0, 1)
      ->execute();

    if ($term_ids === []) {
      return NULL;
    }

    return (int) reset($term_ids);
  }

}

