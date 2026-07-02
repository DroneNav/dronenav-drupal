<?php

namespace Drupal\dronenav_survey_workbench\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;

class SurveySummaryService {

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

  public function getOrCreateWorkingSurveySummary(Node $site_overlay): Node {
    return $this->findWorkingSurveySummary($site_overlay) ?: $this->createWorkingSurveySummary($site_overlay);
  }

  protected function findWorkingSurveySummary(Node $site_overlay): ?Node {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_site_survey_summary')
      ->condition('field_overlay_surveys.target_id', $site_overlay->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->sort('nid', 'ASC')
      ->execute();

    return empty($ids) ? NULL : $storage->load(reset($ids));
  }

  protected function createWorkingSurveySummary(Node $site_overlay): Node {
    $summary = Node::create([
      'type' => 'working_site_survey_summary',
      'title' => $this->generateSurveySummaryTitleFromSite($site_overlay),
      'status' => 0,
      'field_overlay_surveys' => [
        ['target_id' => $site_overlay->id()],
      ],
    ]);

    $summary->save();

    return $summary;
  }

  public function isSurveySummaryPendingReview(Node $summary): bool {
    if ($summary->bundle() !== 'working_site_survey_summary') {
      return FALSE;
    }

    $site_overlay = $this->findSiteOverlay($summary);

    if (!$site_overlay) {
      return FALSE;
    }

    $status = $this->getSiteSurveySummaryStatus(
      $site_overlay->get('field_overlay_uuid')->value
    );

    return $status === 'surveyed' || $status === 'approved';
  }

  public function submitSurveySummary(Node $summary): void {
    if ($summary->bundle() !== 'working_site_survey_summary') {
      throw new \InvalidArgumentException('Only working site survey summaries can be submitted.');
    }

    $site_overlay = $this->findSiteOverlay($summary);

    if (!$site_overlay) {
      throw new \RuntimeException('Site survey summary is missing its site overlay.');
    }

    if ($summary->hasField('field_prepared_by') && $summary->get('field_prepared_by')->isEmpty()) {
      throw new \RuntimeException('Prepared by user is required before submitting this site survey summary.');
    }

    $surveyor = $summary->hasField('field_prepared_by')
      ? $summary->get('field_prepared_by')->entity
      : NULL;

    if (!$surveyor) {
      throw new \RuntimeException('Prepared by user could not be loaded.');
    }

    $this->submitSiteSurveySummaryStatus(
      $site_overlay->get('field_overlay_uuid')->value,
      $surveyor->getAccountName()
    );

    if ($summary->hasField('field_submitted_by')) {
      $summary->set('field_submitted_by', ['target_id' => $this->currentUser->id()]);
    }

    if ($summary->hasField('field_submission_date')) {
      $summary->set('field_submission_date', date('Y-m-d'));
    }

    $summary->setTitle($this->generateSurveySummaryTitle($summary));
    $summary->setPublished(TRUE);
    $summary->save();
  }

  public function resetSurveySummary(Node $summary): void {
    if ($summary->bundle() !== 'working_site_survey_summary') {
      throw new \InvalidArgumentException('Only working site survey summaries can be reset.');
    }

    if ($this->isSurveySummaryPendingReview($summary)) {
      throw new \RuntimeException('This site survey summary is currently pending governance review and cannot be reset.');
    }

    $fields_to_clear = [
      'field_submission_date',
      'field_submitted_by',
      'field_prepared_date',
      'field_prepared_by',
      'field_summary_notes',
      'field_summary_type',
    ];

    foreach ($fields_to_clear as $field_name) {
      if ($summary->hasField($field_name)) {
        $summary->set($field_name, NULL);
      }
    }

    $summary->setTitle($this->generateSurveySummaryTitle($summary));
    $summary->setUnpublished();
    $summary->save();
  }

  protected function findSiteOverlay(Node $summary): ?Node {
    if ($summary->hasField('field_site') && !$summary->get('field_site')->isEmpty()) {
      $site = $summary->get('field_site')->entity;

      if ($site instanceof Node) {
        return $site;
      }
    }

    if (!$summary->hasField('field_overlay_surveys') || $summary->get('field_overlay_surveys')->isEmpty()) {
      return NULL;
    }

    foreach ($summary->get('field_overlay_surveys')->referencedEntities() as $overlaySurvey) {
      if (!$overlaySurvey instanceof Node || $overlaySurvey->bundle() !== 'working_overlay_survey') {
        continue;
      }

      if (!$overlaySurvey->hasField('field_overlay') || $overlaySurvey->get('field_overlay')->isEmpty()) {
        continue;
      }

      $overlay = $overlaySurvey->get('field_overlay')->entity;

      if ($overlay instanceof Node && $overlay->bundle() === 'site') {
        return $overlay;
      }
    }

    return NULL;
  }

  protected function generateSurveySummaryTitle(Node $summary): string {
    $site_overlay = $this->findSiteOverlay($summary);

    if (!$site_overlay) {
      return 'Site Survey Summary - ' . date('Y-m-d');
    }

    return $this->generateSurveySummaryTitleFromSite($site_overlay);
  }

  protected function generateSurveySummaryTitleFromSite(Node $site_overlay): string {
    return $site_overlay->label() . ' - ' . date('Y-m-d');
  }

  protected function submitSiteSurveySummaryStatus(string $site_uuid, string $surveyed_by): void {
    $this->httpClient->post(
      self::API_BASE . '/governance/sites/' . $site_uuid . '/survey-package',
      [
        'json' => [
          'surveyed_by' => $surveyed_by,
        ],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );
  }

  protected function getSiteSurveySummaryStatus(string $site_uuid): ?string {
    try {
      $response = $this->httpClient->get(
        self::API_BASE . '/sites/' . $site_uuid,
        [
          'timeout' => 15,
          'verify' => FALSE,
        ]
      );

      $data = json_decode($response->getBody()->getContents(), TRUE);

      return $data['survey_status'] ?? NULL;
    }
    catch (\Exception $e) {
      \Drupal::logger('dronenav_survey_workbench')->error($e->getMessage());
      return NULL;
    }
  }

}
