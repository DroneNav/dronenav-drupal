<?php

namespace Drupal\dronenav_survey_workbench\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;

class OverlaySyncService {

  private const API_BASE = 'https://api.dronenav.org/api';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ClientInterface $httpClient;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ClientInterface $http_client
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
  }

  public function syncOverlays(): array {
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $surveys_created = 0;
    $summaries_created = 0;
    $overlay_reviews_created = 0;
    $site_reviews_created = 0;

    $overlays = [];

    $sites_response = $this->getOverlaysFromApi('/sites');
    $sites = $sites_response['sites'] ?? [];

    foreach ($sites as $site) {
      if (empty($site['site_id']) || empty($site['site_name'])) {
        $skipped++;
        continue;
      }

      $overlays[] = [
        'type' => 'site',
        'uuid' => $site['site_id'],
        'title' => $site['site_name'],
        'authority_id' => $site['authority_id'] ?? NULL,
      ];
    }

    $zones_response = $this->getOverlaysFromApi('/zones');
    $zones = $zones_response['zones'] ?? [];

    foreach ($zones as $zone) {
      if (empty($zone['zone_id']) || empty($zone['zone_name'])) {
        $skipped++;
        continue;
      }

      $overlays[] = [
        'type' => 'zone',
        'uuid' => $zone['zone_id'],
        'title' => $zone['zone_name'],
        'parent_site_id' => $zone['site_id'],
      ];
    }

    $droneports_response = $this->getOverlaysFromApi('/droneports');
    $droneports = $droneports_response['droneports'] ?? [];

    foreach ($droneports as $droneport) {
      if (empty($droneport['droneport_id']) || empty($droneport['droneport_name'])) {
        $skipped++;
        continue;
      }

      $overlays[] = [
        'type' => 'droneport',
        'uuid' => $droneport['droneport_id'],
        'title' => $droneport['droneport_name'],
        'parent_site_id' => $droneport['site_id'],
      ];
    }

    $routes_response = $this->getOverlaysFromApi('/routes');
    $routes = $routes_response['routes'] ?? [];

    foreach ($routes as $route) {
      if (empty($route['route_id']) || empty($route['route_name'])) {
        $skipped++;
        continue;
      }

      $overlays[] = [
        'type' => 'route',
        'uuid' => $route['route_id'],
        'title' => $route['route_name'],
      ];
    }


    foreach ($overlays as $overlay) {
      if (empty($overlay['type']) || empty($overlay['uuid']) || empty($overlay['title'])) {
       \Drupal::logger('dronenav_survey_workbench')->warning(
           'Skipped overlay: @overlay',
           ['@overlay' => print_r($overlay, TRUE)]
        );
        $skipped++;
        continue;
      }

      $bundle = $overlay['type'];

      if (!in_array($bundle, ['site', 'zone', 'route', 'droneport'], TRUE)) {
        $skipped++;
        continue;
      }

      $existing = $this->findOverlayNode($bundle, $overlay['uuid']);

      if ($existing) {
        $existing->setTitle($overlay['title']);

        if ($existing->hasField('field_overlay_last_synced')) {
          $existing->set('field_overlay_last_synced', date('Y-m-d\TH:i:s'));
        }

        if ($existing->hasField('field_parent_site_id')) {
          $existing->set('field_parent_site_id', $overlay['parent_site_id'] ?? NULL);
        }

        $existing->save();
        $updated++;

        if ($this->ensureWorkingSurvey($existing)) {
          $surveys_created++;
        }

        $survey = $this->findWorkingSurveyForOverlay($existing);
        if ($survey && $this->ensureWorkingOverlayReview($survey)) {
          $overlay_reviews_created++;
        }

        if ($bundle === 'site' && $this->ensureWorkingSurveySummary($existing)) {
          $summaries_created++;
        }

        if ($bundle === 'site') {
          $site_survey = $this->findWorkingSurveyForOverlay($existing);
          $summary = $site_survey ? $this->findWorkingSurveySummaryForSiteSurvey($site_survey) : NULL;

          if ($summary && $this->ensureWorkingSiteReview($summary)) {
            $site_reviews_created++;
          }
        }
      }
      else {
        $node = Node::create([
          'type' => $bundle,
          'title' => $overlay['title'],
          'field_overlay_uuid' => $overlay['uuid'],
          'status' => 0,
        ]);

        if ($node->hasField('field_overlay_last_synced')) {
          $node->set('field_overlay_last_synced', date('Y-m-d\TH:i:s'));
        }

        if ($node->hasField('field_parent_site_id')) {
          $node->set('field_parent_site_id', $overlay['parent_site_id'] ?? NULL);
        }

        $node->save();
        $created++;

        if ($this->ensureWorkingSurvey($node)) {
          $surveys_created++;
        }

        $survey = $this->findWorkingSurveyForOverlay($node);
        if ($survey && $this->ensureWorkingOverlayReview($survey)) {
          $overlay_reviews_created++;
        }

        if ($bundle === 'site' && $this->ensureWorkingSurveySummary($node)) {
          $summaries_created++;
        }

        if ($bundle === 'site') {
          $site_survey = $this->findWorkingSurveyForOverlay($node);
          $summary = $site_survey ? $this->findWorkingSurveySummaryForSiteSurvey($site_survey) : NULL;

          if ($summary && $this->ensureWorkingSiteReview($summary)) {
            $site_reviews_created++;
          }
        }
      }
    }

    return [
      'created' => $created,
      'updated' => $updated,
      'skipped' => $skipped,
      'surveys_created' => $surveys_created,
      'summaries_created' => $summaries_created,
      'overlay_reviews_created' => $overlay_reviews_created,
      'site_reviews_created' => $site_reviews_created,
    ];
  }

  protected function ensureWorkingSurvey(Node $overlay): bool {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_overlay_survey')
      ->condition('field_overlay.target_id', $overlay->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!empty($ids)) {
      return FALSE;
    }

    $survey = Node::create([
      'type' => 'working_overlay_survey',
      'title' => $overlay->label(),
      'status' => 0,
      'field_overlay' => [
        'target_id' => $overlay->id(),
      ],
    ]);

    $survey->save();

    return TRUE;
  }

  protected function ensureWorkingSurveySummary(Node $site_overlay): bool {
    if ($site_overlay->bundle() !== 'site') {
      return FALSE;
    }

    if (!$site_overlay->hasField('field_overlay_uuid') || $site_overlay->get('field_overlay_uuid')->isEmpty()) {
      return FALSE;
    }

    $site_survey = $this->findWorkingSurveyForOverlay($site_overlay);

    if (!$site_survey) {
      return FALSE;
    }

    $site_uuid = $site_overlay->get('field_overlay_uuid')->value;
    $package = $this->getOverlaysFromApi('/sites/' . $site_uuid . '/package');

    $target_ids = [];

    // Site survey first.
    $target_ids[] = ['target_id' => $site_survey->id()];

    foreach (($package['zones'] ?? []) as $zone) {
      if (!empty($zone['zone_id'])) {
        $proxy = $this->findOverlayNode('zone', $zone['zone_id']);
        $survey = $proxy ? $this->findWorkingSurveyForOverlay($proxy) : NULL;

        if ($survey) {
          $target_ids[] = ['target_id' => $survey->id()];
        }
      }
    }

    foreach (($package['droneports'] ?? []) as $droneport) {
      if (!empty($droneport['droneport_id'])) {
        $proxy = $this->findOverlayNode('droneport', $droneport['droneport_id']);
        $survey = $proxy ? $this->findWorkingSurveyForOverlay($proxy) : NULL;

        if ($survey) {
          $target_ids[] = ['target_id' => $survey->id()];
        }
      }
    }

    foreach (($package['routes'] ?? []) as $route) {
      if (!empty($route['route_id'])) {
        $proxy = $this->findOverlayNode('route', $route['route_id']);
        $survey = $proxy ? $this->findWorkingSurveyForOverlay($proxy) : NULL;

        if ($survey) {
          $target_ids[] = ['target_id' => $survey->id()];
        }
      }
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_site_survey_summary')
      ->condition('field_overlay_surveys.target_id', $site_survey->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!empty($ids)) {
      $summary = $storage->load(reset($ids));

      if ($summary && $summary->isPublished()) {
        return FALSE;
      }

      if ($summary && $summary->hasField('field_site')) {
        $summary->set('field_site', [
          'target_id' => $site_overlay->id(),
        ]);
      }

      if ($summary && $summary->hasField('field_overlay_surveys')) {
        $summary->set('field_overlay_surveys', $target_ids);
        $summary->save();
      }

      return FALSE;
    }

    $summary = Node::create([
      'type' => 'working_site_survey_summary',
      'title' => $site_overlay->label(),
      'status' => 0,
      'field_site' => [
        'target_id' => $site_overlay->id(),
      ],
      'field_overlay_surveys' => $target_ids,
    ]);

    $summary->save();

    return TRUE;
  }

  protected function findWorkingSurveyForOverlay(Node $overlay): ?Node {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_overlay_survey')
      ->condition('field_overlay.target_id', $overlay->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    return empty($ids) ? NULL : $storage->load(reset($ids));
  }

  protected function findOverlayNode(string $bundle, string $uuid): ?Node {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', $bundle)
      ->condition('field_overlay_uuid', $uuid)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (empty($ids)) {
      return NULL;
    }

    return $storage->load(reset($ids));
  }

  protected function getOverlaysFromApi(string $path): array {
    try {
      $response = $this->httpClient->get(self::API_BASE . $path, [
        'timeout' => 15,
        'verify' => FALSE,
      ]);

      return json_decode($response->getBody()->getContents(), TRUE) ?? [];
    }
    catch (\Exception $e) {
      \Drupal::logger('dronenav_survey_workbench')->error($e->getMessage());
      return [];
    }
  }

  protected function ensureWorkingOverlayReview(Node $survey): bool {
    if ($survey->bundle() !== 'working_overlay_survey') {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_overlay_review')
      ->condition('field_survey.target_id', $survey->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!empty($ids)) {
      return FALSE;
    }

    $review = Node::create([
      'type' => 'working_overlay_review',
      'title' => $survey->label(),
      'status' => 0,
      'field_operational_status' => 0,
      'field_survey' => [
        'target_id' => $survey->id(),
      ],
    ]);

    $review->set('field_review_status', ['target_id' => 'Pending']);

    $review->save();

    return TRUE;
  }

  protected function findWorkingSurveySummaryForSiteSurvey(Node $site_survey): ?Node {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_site_survey_summary')
      ->condition('field_overlay_surveys.target_id', $site_survey->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    return empty($ids) ? NULL : $storage->load(reset($ids));
  }

  protected function ensureWorkingSiteReview(Node $summary): bool {
    if ($summary->bundle() !== 'working_site_survey_summary') {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->condition('type', 'working_site_operational_review')
      ->condition('field_site_survey_summary.target_id', $summary->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!empty($ids)) {
      return FALSE;
    }

    $review = Node::create([
      'type' => 'working_site_operational_review',
      'title' => $summary->label(),
      'status' => 0,
      'field_operational_status' => 0,
      'field_site_survey_summary' => [
        'target_id' => $summary->id(),
      ],
    ]);

    $review->save();

    return TRUE;
  }

}

