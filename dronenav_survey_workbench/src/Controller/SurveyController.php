<?php

namespace Drupal\dronenav_survey_workbench\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_survey_workbench\Service\SurveyService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SurveyController extends ControllerBase {

  protected SurveyService $surveyService;

  public function __construct(SurveyService $survey_service) {
    $this->surveyService = $survey_service;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_survey_workbench.survey')
    );
  }

  public function startSurvey(int $nid) {
    $overlay = $this->entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (!$overlay) {
      throw $this->createNotFoundException();
    }

    $survey = $this->surveyService
      ->getOrCreateWorkingSurvey($overlay);

    return $this->redirect('entity.node.edit_form', [
      'node' => $survey->id(),
    ]);
  }

  public function resetSurvey(int $nid): RedirectResponse {

    $survey = $this->entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (!$survey || $survey->bundle() !== 'working_overlay_survey') {
      throw $this->createNotFoundException();
    }

    try {
      $this->surveyService->resetSurvey($survey);

      $this->messenger()->addStatus(
        $this->t('Survey "@title" has been reset.', [
          '@title' => $survey->label(),
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
      $referer ?: '/survey-workbench/surveys'
    );
  }

}
