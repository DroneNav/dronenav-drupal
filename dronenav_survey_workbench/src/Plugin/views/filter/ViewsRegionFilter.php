<?php

namespace Drupal\dronenav_survey_workbench\Plugin\views\filter;

use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filters Workbench records by geographic Region.
 */
#[ViewsFilter("dronenav_region")]
class ViewsRegionFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $data = \Drupal::service('dronenav_survey_workbench.survey')
      ->getRegions();

    $options = [];

    foreach ($data['regions'] ?? [] as $region) {
      $options[$region['region_id']] = $region['region_name'];
    }

    $this->valueOptions = $options;

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    if (empty($this->value)) {
      return;
    }

    $region_id = (string) reset($this->value);

    if ($region_id === '') {
      return;
    }

    $view_id = $this->view->id();

    if (!in_array(
        $view_id,
        [
            'surveys',
            'survey_summaries',
            'overlay_reviews',
            'operational_site_reviews',
        ],
        TRUE
    )) {
        return;
    }

    if ($view_id === 'operational_site_reviews') {
        $storage = \Drupal::entityTypeManager()->getStorage('node');

        $review_ids = $storage->getQuery()
            ->condition('type', 'working_site_operational_review')
            ->accessCheck(FALSE)
            ->execute();

        $survey_service = \Drupal::service('dronenav_survey_workbench.survey');
        $matching_ids = [];

        foreach ($storage->loadMultiple($review_ids) as $review) {
            if (
                !$review->hasField('field_site_survey_summary') ||
                $review->get('field_site_survey_summary')->isEmpty()
            ) {
                continue;
            }

            $summary = $review->get('field_site_survey_summary')->entity;

            if (
                !$summary ||
                !$summary->hasField('field_site') ||
                $summary->get('field_site')->isEmpty()
            ) {
                continue;
            }

            $site = $summary->get('field_site')->entity;

            if (
                !$site ||
                !$site->hasField('field_overlay_uuid') ||
                $site->get('field_overlay_uuid')->isEmpty()
            ) {
                continue;
            }

            $geometry = $survey_service->getOverlayGeometry(
                'site',
                $site->get('field_overlay_uuid')->value
            );

            if (!$geometry) {
                continue;
            }

            if ($survey_service->geometryIntersectsRegion($region_id, $geometry)) {
                $matching_ids[] = $review->id();
            }
        }

        if (empty($matching_ids)) {
            $matching_ids = [0];
        }

        $this->query->addWhere(
            $this->options['group'],
            'node_field_data.nid',
            $matching_ids,
            'IN'
        );

        return;
    }

    if ($view_id === 'overlay_reviews') {
      $storage = \Drupal::entityTypeManager()->getStorage('node');

      $review_ids = $storage->getQuery()
        ->condition('type', 'working_overlay_review')
        ->accessCheck(FALSE)
        ->execute();

      $survey_service = \Drupal::service('dronenav_survey_workbench.survey');
      $matching_ids = [];

      foreach ($storage->loadMultiple($review_ids) as $review) {
        if (
          !$review->hasField('field_survey') ||
          $review->get('field_survey')->isEmpty()
        ) {
          continue;
        }

        $survey = $review->get('field_survey')->entity;

        if (
          !$survey ||
          !$survey->hasField('field_overlay') ||
          $survey->get('field_overlay')->isEmpty()
        ) {
          continue;
        }

        $overlay = $survey->get('field_overlay')->entity;

        if (
          !$overlay ||
          !$overlay->hasField('field_overlay_uuid') ||
          $overlay->get('field_overlay_uuid')->isEmpty()
        ) {
          continue;
        }

        $geometry = $survey_service->getOverlayGeometry(
          $overlay->bundle(),
          $overlay->get('field_overlay_uuid')->value
        );

        if (!$geometry) {
          continue;
        }

        if ($survey_service->geometryIntersectsRegion($region_id, $geometry)) {
          $matching_ids[] = $review->id();
        }
      }

      if (empty($matching_ids)) {
        $matching_ids = [0];
      }

      $this->query->addWhere(
        $this->options['group'],
        'node_field_data.nid',
        $matching_ids,
        'IN'
      );

      return;
    }

    if ($view_id === 'survey_summaries') {
      $storage = \Drupal::entityTypeManager()->getStorage('node');

      $summary_ids = $storage->getQuery()
        ->condition('type', 'working_site_survey_summary')
        ->accessCheck(FALSE)
        ->execute();

      $survey_service = \Drupal::service('dronenav_survey_workbench.survey');
      $matching_ids = [];

      foreach ($storage->loadMultiple($summary_ids) as $summary) {
        if (
          !$summary->hasField('field_site') ||
          $summary->get('field_site')->isEmpty()
        ) {
          continue;
        }

        $site = $summary->get('field_site')->entity;

        if (
          !$site ||
          !$site->hasField('field_overlay_uuid') ||
          $site->get('field_overlay_uuid')->isEmpty()
        ) {
          continue;
        }

        $geometry = $survey_service->getOverlayGeometry(
          'site',
          $site->get('field_overlay_uuid')->value
        );

        if (!$geometry) {
          continue;
        }

        if ($survey_service->geometryIntersectsRegion($region_id, $geometry)) {
          $matching_ids[] = $summary->id();
        }
      }

      if (empty($matching_ids)) {
        $matching_ids = [0];
      }

      $this->query->addWhere(
        $this->options['group'],
        'node_field_data.nid',
        $matching_ids,
        'IN'
      );

      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $survey_ids = $storage->getQuery()
      ->condition('type', 'working_overlay_survey')
      ->accessCheck(FALSE)
      ->execute();

    $survey_service = \Drupal::service('dronenav_survey_workbench.survey');
    $matching_ids = [];

    foreach ($storage->loadMultiple($survey_ids) as $survey) {
      if (
        !$survey->hasField('field_overlay') ||
        $survey->get('field_overlay')->isEmpty()
      ) {
        continue;
      }

      $overlay = $survey->get('field_overlay')->entity;

      if (
        !$overlay ||
        !$overlay->hasField('field_overlay_uuid') ||
        $overlay->get('field_overlay_uuid')->isEmpty()
      ) {
        continue;
      }

      $geometry = $survey_service->getOverlayGeometry(
        $overlay->bundle(),
        $overlay->get('field_overlay_uuid')->value
      );

      if (!$geometry) {
        continue;
      }

      if ($survey_service->geometryIntersectsRegion($region_id, $geometry)) {
        $matching_ids[] = $survey->id();
      }
    }

    if (empty($matching_ids)) {
      $matching_ids = [0];
    }

    $this->query->addWhere(
      $this->options['group'],
      'node_field_data.nid',
      $matching_ids,
      'IN'
    );
  }

}

