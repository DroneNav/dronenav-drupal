<?php

namespace Drupal\dronenav_survey_workbench\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;

class OverlayReviewService {

  private const API_BASE = 'https://api.dronenav.org/api';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountProxyInterface $currentUser;
  protected ClientInterface $httpClient;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    ClientInterface $http_client
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->httpClient = $http_client;
  }

  public function acceptReview(Node $review): void {
    if ($review->bundle() !== 'working_overlay_review') {
      throw new \InvalidArgumentException('Only working overlay reviews can be accepted.');
    }

    if ($this->isReviewApproved($review)) {
      throw new \RuntimeException('This review has already been accepted.');
    }

    if (!$review->hasField('field_survey') || $review->get('field_survey')->isEmpty()) {
      throw new \RuntimeException('Review is missing its survey reference.');
    }

    $survey = $review->get('field_survey')->entity;

    if (!$survey instanceof Node || $survey->bundle() !== 'working_overlay_survey') {
      throw new \RuntimeException('Review does not reference a valid working overlay survey.');
    }

    if (!$survey->hasField('field_overlay') || $survey->get('field_overlay')->isEmpty()) {
      throw new \RuntimeException('Survey is missing its overlay reference.');
    }

    $overlay = $survey->get('field_overlay')->entity;

    if (!$overlay instanceof Node) {
      throw new \RuntimeException('Survey overlay could not be loaded.');
    }

    if (!$overlay->hasField('field_overlay_uuid') || $overlay->get('field_overlay_uuid')->isEmpty()) {
      throw new \RuntimeException('Overlay is missing its DroneNav UUID.');
    }

    $this->approveOverlay(
      $overlay->bundle(),
      $overlay->get('field_overlay_uuid')->value
    );

    if ($review->hasField('field_submitted_by')) {
      $review->set('field_submitted_by', [
        'target_id' => $this->currentUser->id(),
      ]);
    }

    if ($review->hasField('field_submission_date')) {
      $review->set('field_submission_date', date('Y-m-d'));
    }

    if ($review->hasField('field_review_status')) {
      if ($tid = $this->getReviewStatusTid('Approved')) {
        $review->set('field_review_status', ['target_id' => $tid]);
      }
    }

    $review->setPublished(TRUE);
    $review->save();
  }

  /**
   * Determines whether the review has already been approved.
   */
  protected function isReviewApproved(Node $review): bool {
    if (
      !$review->hasField('field_review_status') ||
      $review->get('field_review_status')->isEmpty()
    ) {
      return FALSE;
    }

    $status_entity = $review->get('field_review_status')->entity;

    return $status_entity
      && strcasecmp($status_entity->label(), 'Approved') === 0;
  }

  public function rejectReview(Node $review): void {
    if ($review->bundle() !== 'working_overlay_review') {
      throw new \InvalidArgumentException('Only working overlay reviews can be rejected.');
    }

    if (!$review->hasField('field_survey') || $review->get('field_survey')->isEmpty()) {
      throw new \RuntimeException('Review is missing its survey reference.');
    }

    $survey = $review->get('field_survey')->entity;

    if (!$survey instanceof Node || $survey->bundle() !== 'working_overlay_survey') {
      throw new \RuntimeException('Review does not reference a valid working overlay survey.');
    }

    if (!$survey->hasField('field_overlay') || $survey->get('field_overlay')->isEmpty()) {
      throw new \RuntimeException('Survey is missing its overlay reference.');
    }

    $overlay = $survey->get('field_overlay')->entity;

    if (!$overlay instanceof Node) {
      throw new \RuntimeException('Survey overlay could not be loaded.');
    }

    if (!$overlay->hasField('field_overlay_uuid') || $overlay->get('field_overlay_uuid')->isEmpty()) {
      throw new \RuntimeException('Overlay is missing its DroneNav UUID.');
    }

    if (!$review->hasField('field_reviewer_comments') || $review->get('field_reviewer_comments')->isEmpty()) {
      throw new \RuntimeException('Reviewer comments are required when rejecting a survey.');
    }

    $comments = trim((string) $review->get('field_reviewer_comments')->value);

    if ($comments === '') {
      throw new \RuntimeException('Reviewer comments are required when rejecting a survey.');
    }

    if ($survey->hasField('field_reviewer_comments')) {
      $existing_comments = trim((string) $survey->get('field_reviewer_comments')->value);

      $entry = sprintf(
        "[%s] Rejected by %s:\n%s",
        date('Y-m-d'),
        $this->currentUser->getAccountName(),
        $comments
      );

      $survey->set(
        'field_reviewer_comments',
        $existing_comments === ''
          ? $entry
          : $existing_comments . "\n\n" . $entry
      );

      $survey->setPublished(FALSE);
      $survey->save();
    }

    $this->rejectOverlay(
      $overlay->bundle(),
      $overlay->get('field_overlay_uuid')->value,
      $comments
    );

    if ($review->hasField('field_submitted_by')) {
      $review->set('field_submitted_by', [
      'target_id' => $this->currentUser->id(),
      ]);
    }

    if ($review->hasField('field_submission_date')) {
      $review->set('field_submission_date', date('Y-m-d'));
    }

    if ($review->hasField('field_review_status')) {
      if ($tid = $this->getReviewStatusTid('Rejected')) {
        $review->set('field_review_status', ['target_id' => $tid]);
      }
    }

    $review->setPublished(FALSE);
    $review->save();
  }

  public function requestChangesReview(Node $review): void {
    if ($review->bundle() !== 'working_overlay_review') {
      throw new \InvalidArgumentException('Only working overlay reviews can request changes.');
    }

    if (!$review->hasField('field_survey') || $review->get('field_survey')->isEmpty()) {
      throw new \RuntimeException('Review is missing its survey reference.');
    }

    $survey = $review->get('field_survey')->entity;

    if (!$survey instanceof Node || $survey->bundle() !== 'working_overlay_survey') {
      throw new \RuntimeException('Review does not reference a valid working overlay survey.');
    }

    if (!$survey->hasField('field_overlay') || $survey->get('field_overlay')->isEmpty()) {
      throw new \RuntimeException('Survey is missing its overlay reference.');
    }

    $overlay = $survey->get('field_overlay')->entity;

    if (!$overlay instanceof Node) {
      throw new \RuntimeException('Survey overlay could not be loaded.');
    }

    if (!$overlay->hasField('field_overlay_uuid') || $overlay->get('field_overlay_uuid')->isEmpty()) {
      throw new \RuntimeException('Overlay is missing its DroneNav UUID.');
    }

    if (!$review->hasField('field_reviewer_comments') || $review->get('field_reviewer_comments')->isEmpty()) {
      throw new \RuntimeException('Reviewer comments are required when requesting changes.');
    }

    $comments = trim((string) $review->get('field_reviewer_comments')->value);

    if ($comments === '') {
      throw new \RuntimeException('Reviewer comments are required when requesting changes.');
    }

    if ($survey->hasField('field_reviewer_comments')) {
      $existing_comments = trim((string) $survey->get('field_reviewer_comments')->value);

      $entry = sprintf(
        "[%s] Changes requested by %s:\n%s",
        date('Y-m-d'),
        $this->currentUser->getAccountName(),
        $comments
      );

      $survey->set(
        'field_reviewer_comments',
        $existing_comments === ''
          ? $entry
          : $existing_comments . "\n\n" . $entry
      );

      $survey->setPublished(FALSE);
      $survey->save();
    }

    $this->requestChangesOverlay(
      $overlay->bundle(),
      $overlay->get('field_overlay_uuid')->value,
      $comments
    );

    if ($review->hasField('field_submitted_by')) {
      $review->set('field_submitted_by', [
        'target_id' => $this->currentUser->id(),
      ]);
    }

    if ($review->hasField('field_submission_date')) {
      $review->set('field_submission_date', date('Y-m-d'));
    }

    if ($review->hasField('field_review_status')) {
      if ($tid = $this->getReviewStatusTid('Revisions Requested')) {
        $review->set('field_review_status', ['target_id' => $tid]);
      }
    }

    $review->setPublished(FALSE);
    $review->save();
  }


  protected function approveOverlay(string $overlay_type, string $overlay_uuid): void {

    $this->httpClient->post(
      self::API_BASE . '/governance/overlays/' . $overlay_type . '/' . $overlay_uuid . '/approve',
      [
        'json' => [
            'approved_by' => $this->currentUser->getAccountName(),
        ],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );
  }

  protected function rejectOverlay(string $overlay_type, string $overlay_uuid, string $comments): void {

    $this->httpClient->post(
      self::API_BASE . '/governance/overlays/' . $overlay_type . '/' . $overlay_uuid . '/reject',
      [
        'json' => [
            'reviewed_by' => $this->currentUser->getAccountName(),
            'review_comments' => $comments
        ],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );
  }

  protected function requestChangesOverlay(string $overlay_type, string $overlay_uuid, string $comments): void {
    $this->httpClient->post(
      self::API_BASE . '/governance/overlays/' . $overlay_type . '/' . $overlay_uuid . '/request-changes',
      [
        'json' => [
          'reviewed_by' => $this->currentUser->getAccountName(),
          'review_comments' => $comments,
        ],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );
  }

  protected function getReviewStatusTid(string $name): ?int {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $ids = $storage->getQuery()
      ->condition('vid', 'review_status')
      ->condition('name', $name)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    return empty($ids) ? NULL : (int) reset($ids);
  }

}

