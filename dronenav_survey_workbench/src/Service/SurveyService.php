<?php

namespace Drupal\dronenav_survey_workbench\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;

class SurveyService {

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

  public function getOrCreateWorkingSurvey(Node $overlay): Node {
    return $this->findWorkingSurvey($overlay) ?: $this->createWorkingSurvey($overlay);
  }

  protected function findWorkingSurvey(Node $overlay): ?Node {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_overlay_survey')
      ->condition('field_overlay.target_id', $overlay->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->sort('nid', 'ASC')
      ->execute();

    return empty($ids) ? NULL : $storage->load(reset($ids));
  }

  protected function createWorkingSurvey(Node $overlay): Node {
    $survey = Node::create([
      'type' => 'working_overlay_survey',
      'title' => $overlay->label(),
      'status' => 0,
      'field_overlay' => ['target_id' => $overlay->id()],
    ]);

    $survey->save();
    return $survey;
  }

  public function isSurveyPendingReview(Node $survey): bool {
    if ($survey->bundle() !== 'working_overlay_survey') {
      return FALSE;
    }

    $overlay = $survey->get('field_overlay')->entity;

    if (!$overlay) {
      return FALSE;
    }

    $status = $this->getOverlaySurveyStatus(
      $overlay->bundle(),
      $overlay->get('field_overlay_uuid')->value
    );

    return $status === 'surveyed' || $status === 'approved';
  }

  public function submitSurvey(Node $survey): void {
    if ($survey->bundle() !== 'working_overlay_survey') {
      throw new \InvalidArgumentException('Only working overlay surveys can be submitted.');
    }

    $overlay = $survey->get('field_overlay')->entity;
    if (!$overlay) {
      throw new \RuntimeException('Survey is missing an overlay.');
    }

    if ($survey->get('field_surveyor')->isEmpty()) {
      throw new \RuntimeException('Surveyor is required before submitting this survey.');
    }

    $surveyor = $survey->get('field_surveyor')->entity;
    if (!$surveyor) {
      throw new \RuntimeException('Surveyor user could not be loaded.');
    }

    $this->submitOverlaySurveyStatus(
      $overlay->bundle(),
      $overlay->get('field_overlay_uuid')->value,
      $surveyor->getAccountName()
    );


    $this->markOverlayReviewPending($survey);

    if ($survey->hasField('field_submitted_by')) {
      $survey->set('field_submitted_by', ['target_id' => $this->currentUser->id()]);
    }

    if ($survey->hasField('field_submission_date')) {
      $survey->set('field_submission_date', date('Y-m-d'));
    }

    $survey->setTitle($this->generateSurveyTitle($survey));
    $survey->setPublished(TRUE);
    $survey->save();
  }

  protected function markOverlayReviewPending(Node $survey): void {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_overlay_review')
      ->condition('field_survey.target_id', $survey->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (empty($ids)) {
      return;
    }

    /** @var \Drupal\node\Entity\Node $review */
    $review = $storage->load(reset($ids));

    if (!$review) {
      return;
    }

    if ($review->hasField('field_review_status')) {
      if ($tid = $this->getReviewStatusTid('Pending')) {
        $review->set('field_review_status', ['target_id' => $tid]);
      }
    }

    // The review is awaiting another governance decision.
    $review->setPublished(FALSE);
    $review->save();
  }

  protected function getReviewStatusTid(string $name): ?int {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $ids = $storage->getQuery()
      ->condition('vid', 'review_status')   // Replace with your vocabulary machine name if different.
      ->condition('name', $name)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    return empty($ids) ? NULL : (int) reset($ids);
  }

  public function resetSurvey(Node $survey): void {
    if ($survey->bundle() !== 'working_overlay_survey') {
      throw new \InvalidArgumentException('Only working overlay surveys can be reset.');
    }

    $overlay = $survey->get('field_overlay')->entity;
    if (!$overlay) {
      throw new \RuntimeException('Survey is missing an overlay.');
    }

    $status = $this->getOverlaySurveyStatus(
      $overlay->bundle(),
      $overlay->get('field_overlay_uuid')->value
    );

    if ($status === 'surveyed' || $status === 'approved') {
      throw new \RuntimeException('This survey is currently pending governance review and cannot be reset.');
    }

    $fields_to_clear = [
      'field_documents',
      'field_measurements',
      'field_observations',
      'field_photos',
      'field_submission_date',
      'field_submitted_by',
      'field_survey_date',
      'field_survey_method',
      'field_survey_notes',
      'field_survey_type',
      'field_surveyor',
    ];

    foreach ($fields_to_clear as $field_name) {
      if ($survey->hasField($field_name)) {
        $survey->set($field_name, NULL);
      }
    }

    $survey->setTitle($this->generateSurveyTitle($survey));
    $survey->setUnpublished();
    $survey->save();
  }

  protected function generateSurveyTitle(Node $survey): string {
    $overlay = $survey->get('field_overlay')->entity;
    $overlay_title = $overlay ? $overlay->label() : 'Unknown Overlay';
    $geo_type = $overlay ? ucfirst($overlay->bundle()) : 'Overlay';

    $survey_date = date('Y-m-d');
    if ($survey->hasField('field_survey_date') && !$survey->get('field_survey_date')->isEmpty()) {
      $survey_date = $survey->get('field_survey_date')->value;
    }

    return $overlay_title . ' - ' . $geo_type . ' - ' . $survey_date;
  }

  protected function submitOverlaySurveyStatus(
    string $overlay_type,
    string $overlay_uuid,
    string $surveyed_by
  ): void {
    $this->httpClient->post(
      self::API_BASE . '/governance/overlays/' . $overlay_type . '/' . $overlay_uuid . '/survey',
      [
        'json' => ['surveyed_by' => $surveyed_by],
        'timeout' => 15,
        'verify' => FALSE,
      ]
    );
  }

  protected function getOverlaySurveyStatus(string $overlay_type, string $overlay_uuid): ?string {
    $endpoint_map = [
      'site' => '/sites/',
      'zone' => '/zones/',
      'droneport' => '/droneports/',
      'route' => '/routes/',
    ];

    if (!isset($endpoint_map[$overlay_type])) {
      return NULL;
    }

    try {
      $response = $this->httpClient->get(
        self::API_BASE . $endpoint_map[$overlay_type] . $overlay_uuid,
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

