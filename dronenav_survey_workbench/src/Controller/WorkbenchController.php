<?php

namespace Drupal\dronenav_survey_workbench\Controller;

use Drupal\Core\Controller\ControllerBase;

class WorkbenchController extends ControllerBase {

  public function dashboard(): array {
    return [
      '#markup' => '<p>DroneNav Survey Workbench dashboard placeholder.</p>',
    ];
  }

  public function surveyMap(int $nid): array {
    $survey = $this->entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (!$survey || $survey->bundle() !== 'working_overlay_survey') {
      throw $this->createNotFoundException();
    }

    if (!$survey->hasField('field_overlay') || $survey->get('field_overlay')->isEmpty()) {
      return [
        '#markup' => '<p>No overlay is assigned to this survey.</p>',
      ];
    }

    $overlay = $survey->get('field_overlay')->entity;

    if (!$overlay || !$overlay->hasField('field_overlay_uuid') || $overlay->get('field_overlay_uuid')->isEmpty()) {
      return [
        '#markup' => '<p>The assigned overlay is missing its DroneNav UUID.</p>',
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'root',
        'data-mode' => 'survey_readonly',
        'data-overlay-type' => $overlay->bundle(),
        'data-overlay-uuid' => $overlay->get('field_overlay_uuid')->value,
        'data-site-id' => $overlay->hasField('field_parent_site_id') && !$overlay->get('field_parent_site_id')->isEmpty()
          ? $overlay->get('field_parent_site_id')->value
          : '',
        'style' => 'height: 700px; width: 100%;',
      ],
      '#attached' => [
        'library' => [
          'dronenav_survey_workbench/react_map',
        ],
      ],
    ];
  }

  public function surveySummaryMap(int $nid): array {
    $surveySummary = $this->entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (!$surveySummary || $surveySummary->bundle() !== 'working_site_survey_summary') {
      throw $this->createNotFoundException();
    }

    if (!$surveySummary->hasField('field_overlay_surveys') || $surveySummary->get('field_overlay_surveys')->isEmpty()) {
      return [
        '#markup' => '<p>No overlays are assigned to this summary report.</p>',
      ];
    }

    $overlaySurveys = $surveySummary->get('field_overlay_surveys')->referencedEntities();

    $siteOverlay = NULL;

    foreach ($overlaySurveys as $overlaySurvey) {
      if (!$overlaySurvey instanceof \Drupal\node\Entity\Node) {
        continue;
      }

      if ($overlaySurvey->bundle() !== 'working_overlay_survey') {
        continue;
      }

      if (!$overlaySurvey->hasField('field_overlay') || $overlaySurvey->get('field_overlay')->isEmpty()) {
        continue;
      }

      $overlay = $overlaySurvey->get('field_overlay')->entity;

      if ($overlay instanceof \Drupal\node\Entity\Node && $overlay->bundle() === 'site') {
        $siteOverlay = $overlay;
        break;
      }
    }

    if (!$siteOverlay) {
      return [
        '#markup' => '<p>No site overlay is assigned to this summary report.</p>',
      ];
    }

    if (!$siteOverlay->hasField('field_overlay_uuid') || $siteOverlay->get('field_overlay_uuid')->isEmpty()) {
      return [
        '#markup' => '<p>The assigned site overlay is missing its DroneNav UUID.</p>',
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'root',
        'data-mode' => 'site_readonly',
        'data-site-id' => $siteOverlay->get('field_overlay_uuid')->value,
        'style' => 'height: 700px; width: 100%;',
      ],
      '#attached' => [
        'library' => [
          'dronenav_survey_workbench/react_map',
        ],
      ],
    ];
  }

  public function reactMapTest() {
    return [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'height: 700px; width: 100%;',
      ],
      'map' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'root',
          'data-mode' => 'survey_readonly',
          'data-overlay-type' => 'route',
          'data-overlay-uuid' => '019eadd9-906b-7ca6-8965-48954549afcf',
          'style' => 'height: 700px; width: 100%;',
        ],
      ],
      '#attached' => [
        'library' => [
          'dronenav_survey_workbench/react_map',
        ],
      ],
    ];
  }

}

