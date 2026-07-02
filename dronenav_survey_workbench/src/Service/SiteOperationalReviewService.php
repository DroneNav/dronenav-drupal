<?php

namespace Drupal\dronenav_survey_workbench\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;

class SiteOperationalReviewService {

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

  public function approvePackage(Node $review): void {
    $summary = $this->loadSummaryFromReview($review);
    $site = $this->loadSiteFromSummary($summary);
    $site_uuid = $this->getOverlayUuid($site);

    $this->approveSitePackage($site_uuid);

    $this->setReviewStatus($review, 'Approved');
    $this->setDecisionFields($review);

    $review->setPublished(TRUE);
    $review->save();
  }

  public function rejectPackage(Node $review): void {
    $summary = $this->loadSummaryFromReview($review);
    $site = $this->loadSiteFromSummary($summary);
    $site_uuid = $this->getOverlayUuid($site);

    if (!$review->hasField('field_reviewer_comments') || $review->get('field_reviewer_comments')->isEmpty()) {
      throw new \RuntimeException('Reviewer comments are required when rejecting a site package.');
    }

    $comments = trim((string) $review->get('field_reviewer_comments')->value);

    if ($comments === '') {
      throw new \RuntimeException('Reviewer comments are required when rejecting a site package.');
    }

    $this->rejectSitePackage($site_uuid, $comments);

    if ($summary->hasField('field_reviewer_comments')) {
      $existing_comments = trim((string) $summary->get('field_reviewer_comments')->value);

      $entry = sprintf(
        "[%s] Site package rejected by %s:\n%s",
        date('Y-m-d'),
        $this->currentUser->getAccountName(),
        $comments
      );

      $summary->set(
        'field_reviewer_comments',
        $existing_comments === ''
          ? $entry
          : $existing_comments . "\n\n" . $entry
      );
    }

    $summary->setPublished(FALSE);
    $summary->save();

    $this->setReviewStatus($review, 'Rejected');
    $this->setDecisionFields($review);

    $review->setPublished(FALSE);
    $review->save();
  }

  protected function loadSummaryFromReview(Node $review): Node {
    if ($review->bundle() !== 'working_site_operational_review') {
      throw new \InvalidArgumentException('Only working site operational reviews can be processed.');
    }

    if (!$review->hasField('field_site_survey_summary') || $review->get('field_site_survey_summary')->isEmpty()) {
      throw new \RuntimeException('Site operational review is missing its site survey summary reference.');
    }

    $summary = $review->get('field_site_survey_summary')->entity;

    if (!$summary instanceof Node || $summary->bundle() !== 'working_site_survey_summary') {
      throw new \RuntimeException('Site operational review does not reference a valid site survey summary.');
    }

    return $summary;
  }

  protected function loadSiteFromSummary(Node $summary): Node {
    if (!$summary->hasField('field_site') || $summary->get('field_site')->isEmpty()) {
      throw new \RuntimeException('Site survey summary is missing its site reference.');
    }

    $site = $summary->get('field_site')->entity;

    if (!$site instanceof Node || $site->bundle() !== 'site') {
      throw new \RuntimeException('Site survey summary does not reference a valid site overlay.');
    }

    return $site;
  }

  protected function getOverlayUuid(Node $site): string {
    if (!$site->hasField('field_overlay_uuid') || $site->get('field_overlay_uuid')->isEmpty()) {
      throw new \RuntimeException('Site overlay is missing its DroneNav UUID.');
    }

    return (string) $site->get('field_overlay_uuid')->value;
  }

  protected function approveSitePackage(string $site_uuid): void {
    $this->httpClient->post(
      self::API_BASE . '/governance/sites/' . $site_uuid . '/approve-package',
      [
        'json' => [
          'reviewed_by' => $this->currentUser->getAccountName(),
        ],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );
  }

  protected function rejectSitePackage(string $site_uuid, string $comments): void {
    $this->httpClient->post(
      self::API_BASE . '/governance/sites/' . $site_uuid . '/reject-package',
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

  protected function setDecisionFields(Node $review): void {
    if ($review->hasField('field_submitted_by')) {
      $review->set('field_submitted_by', [
        'target_id' => $this->currentUser->id(),
      ]);
    }

    if ($review->hasField('field_submission_date')) {
      $review->set('field_submission_date', date('Y-m-d'));
    }
  }

  protected function setReviewStatus(Node $review, string $name): void {
    if (!$review->hasField('field_review_status')) {
      return;
    }

    $tid = $this->getReviewStatusTid($name);

    if ($tid) {
      $review->set('field_review_status', ['target_id' => $tid]);
    }
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

