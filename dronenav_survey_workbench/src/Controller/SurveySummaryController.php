<?php

namespace Drupal\dronenav_survey_workbench\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_survey_workbench\Service\SurveySummaryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SurveySummaryController extends ControllerBase {

  protected SurveySummaryService $summaryService;

  public function __construct(SurveySummaryService $summary_service) {
    $this->summaryService = $summary_service;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_survey_workbench.survey_summary')
    );
  }

  public function startSurveySummary(int $nid) {
    $overlay = $this->entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (!$overlay) {
      throw $this->createNotFoundException();
    }

    $surveySummary = $this->summaryService
      ->getOrCreateWorkingSurveySummary($overlay);

    return $this->redirect('entity.node.edit_form', [
      'node' => $surveySummary->id(),
    ]);
  }

  public function resetSurveySummary(int $nid): RedirectResponse {

    $surveySummary = $this->entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (!$surveySummary || $surveySummary->bundle() !== 'working_site_survey_summary') {
      throw $this->createNotFoundException();
    }

    try {
      $this->summaryService->resetSurveySummary($surveySummary);

      $this->messenger()->addStatus(
        $this->t('Survey Summary "@title" has been reset.', [
          '@title' => $surveySummary->label(),
        ])
      );
    }
    catch (\Throwable $e) {
      $this->messenger()->addError(
        $this->t('@message', [
          '@message' => $e->getMessage(),
        ])
      );
    }

    $referer = \Drupal::request()->headers->get('referer');

    return new RedirectResponse(
      $referer ?: '/survey-workbench/survey-summaries'
    );
  }

}
