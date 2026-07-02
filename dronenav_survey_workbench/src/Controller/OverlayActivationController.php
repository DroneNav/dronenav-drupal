<?php

namespace Drupal\dronenav_survey_workbench\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OverlayActivationController extends ControllerBase {

  public function activate(int $nid): RedirectResponse {

    /** @var \Drupal\node\Entity\Node $review */
    $review = \Drupal\node\Entity\Node::load($nid);

    if (!$review) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    \Drupal::service('dronenav_survey_workbench.overlay_activation')
      ->activate($review);

    $this->messenger()->addStatus(
      $this->t('Overlay activated successfully.')
    );

    return new RedirectResponse('/review-workbench/reviews');
  }

  public function deactivate(int $nid): RedirectResponse {

    /** @var \Drupal\node\Entity\Node $review */
    $review = \Drupal\node\Entity\Node::load($nid);

    if (!$review) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    \Drupal::service('dronenav_survey_workbench.overlay_activation')
      ->deactivate($review);

    $this->messenger()->addStatus(
      $this->t('Overlay deactivated successfully.')
    );

    return new RedirectResponse('/review-workbench/reviews');
  }


}
