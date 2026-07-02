<?php

namespace Drupal\dronenav_survey_workbench\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Session\AccountProxyInterface;

class OverlayActivationService {

  private const API_BASE = 'https://api.dronenav.org/api';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ClientInterface $httpClient;
  protected AccountProxyInterface $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ClientInterface $http_client,
    AccountProxyInterface $current_user
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
    $this->currentUser = $current_user;
  }

  public function deactivate(Node $review): void {
    [$overlay_type, $overlay_uuid] = $this->getOverlayInfoFromReview($review);

    $this->httpClient->post(
      self::API_BASE . '/governance/overlays/' . $overlay_type . '/' . $overlay_uuid . '/deactivate',
      [
        'json' => new \stdClass(),
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );

    if ($review->hasField('field_operational_status')) {
      $review->set('field_operational_status', 0);
    }

    $review->save();
  }

  public function activate(Node $review): void {
    [$overlay_type, $overlay_uuid] = $this->getOverlayInfoFromReview($review);

    $this->httpClient->post(
      self::API_BASE . '/governance/overlays/' . $overlay_type . '/' . $overlay_uuid . '/activate',
      [
        'json' => [
          'activated_by' => $this->currentUser->getAccountName(),
          ],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );

    if ($review->hasField('field_operational_status')) {
      $review->set('field_operational_status', 1);
    }

    $review->save();
  }

  protected function getOverlayInfoFromReview(Node $review): array {
    if ($review->bundle() !== 'working_overlay_review') {
      throw new \InvalidArgumentException('Only working overlay reviews can be activated or deactivated.');
    }

    if (!$review->hasField('field_survey') || $review->get('field_survey')->isEmpty()) {
      throw new \RuntimeException('Review is missing its survey reference.');
    }

    $survey = $review->get('field_survey')->entity;

    if (!$survey instanceof Node) {
      throw new \RuntimeException('Review survey could not be loaded.');
    }

    if (!$survey->hasField('field_overlay') || $survey->get('field_overlay')->isEmpty()) {
      throw new \RuntimeException('Survey is missing its overlay reference.');
    }

    $overlay = $survey->get('field_overlay')->entity;

    if (!$overlay instanceof Node) {
      throw new \RuntimeException('Overlay could not be loaded.');
    }

    if (!$overlay->hasField('field_overlay_uuid') || $overlay->get('field_overlay_uuid')->isEmpty()) {
      throw new \RuntimeException('Overlay is missing its DroneNav UUID.');
    }

    return [
      $overlay->bundle(),
      $overlay->get('field_overlay_uuid')->value,
    ];
  }

  public function activateSite(Node $review): void {
    $site_uuid = $this->getSiteUuidFromOperationalReview($review);

    $this->httpClient->post(
      self::API_BASE . '/governance/sites/' . $site_uuid . '/activate-package',
      [
        'json' => [
          'activated_by' => $this->currentUser->getAccountName(),
        ],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );

    if ($review->hasField('field_operational_status')) {
      $review->set('field_operational_status', 1);
    }

    $review->save();
  }

  public function deactivateSite(Node $review): void {
    $site_uuid = $this->getSiteUuidFromOperationalReview($review);

    $this->httpClient->post(
      self::API_BASE . '/governance/sites/' . $site_uuid . '/deactivate-package',
      [
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );

    if ($review->hasField('field_operational_status')) {
      $review->set('field_operational_status', 0);
    }

    $review->save();
  }

  protected function getSiteUuidFromOperationalReview(Node $review): string {
    if ($review->bundle() !== 'working_site_operational_review') {
      throw new \InvalidArgumentException('Only working site operational reviews can deactivate a site.');
    }

    if (!$review->hasField('field_site_survey_summary') || $review->get('field_site_survey_summary')->isEmpty()) {
      throw new \RuntimeException('Site operational review is missing its site survey summary reference.');
    }

    $summary = $review->get('field_site_survey_summary')->entity;

    if (!$summary instanceof Node) {
      throw new \RuntimeException('Site survey summary could not be loaded.');
    }

    if (!$summary->hasField('field_site') || $summary->get('field_site')->isEmpty()) {
      throw new \RuntimeException('Site survey summary is missing its site reference.');
    }

    $site = $summary->get('field_site')->entity;

    if (!$site instanceof Node) {
      throw new \RuntimeException('Site overlay could not be loaded.');
    }

    if (!$site->hasField('field_overlay_uuid') || $site->get('field_overlay_uuid')->isEmpty()) {
      throw new \RuntimeException('Site overlay is missing its DroneNav UUID.');
    }

    return (string) $site->get('field_overlay_uuid')->value;
  }

}

