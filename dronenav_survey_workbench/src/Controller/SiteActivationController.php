<?php

namespace Drupal\dronenav_survey_workbench\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteActivationController extends ControllerBase {

  public function activate(int $nid): RedirectResponse {
    $review = Node::load($nid);

    if (!$review) {
      throw new NotFoundHttpException();
    }

    try {
      \Drupal::service('dronenav_survey_workbench.overlay_activation')
        ->activateSite($review);

      $this->messenger()->addStatus(
        $this->t('Site activated successfully.')
      );
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    return new RedirectResponse('/review-workbench/site-reviews');
  }

  public function deactivate(int $nid): RedirectResponse {
    $review = Node::load($nid);

    if (!$review) {
      throw new NotFoundHttpException();
    }

    try {
      \Drupal::service('dronenav_survey_workbench.overlay_activation')
        ->deactivateSite($review);

      $this->messenger()->addStatus(
        $this->t('Site deactivated successfully.')
      );
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($e->getMessage());
    }

    return new RedirectResponse('/review-workbench/site-reviews');
  }

}

