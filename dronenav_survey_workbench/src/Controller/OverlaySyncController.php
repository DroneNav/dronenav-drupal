<?php

namespace Drupal\dronenav_survey_workbench\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_survey_workbench\Service\OverlaySyncService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OverlaySyncController extends ControllerBase {

  protected OverlaySyncService $overlaySync;

  public function __construct(OverlaySyncService $overlay_sync) {
    $this->overlaySync = $overlay_sync;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_survey_workbench.overlay_sync')
    );
  }

  public function sync(): array {
    $result = $this->overlaySync->syncOverlays();

    return [
      '#markup' => sprintf(
        '<p>Overlay sync complete.</p>
         <p>
         Created: %d<br>
         Updated: %d<br>
         Skipped: %d<br>
         Surveys Created: %d<br>
         Summaries Created: %d<br>
         Overlay Reviews Created: %d<br>
         Site Reviews Created: %d
         </p>',
        $result['created'],
        $result['updated'],
        $result['skipped'],
        $result['surveys_created'],
        $result['summaries_created'],
        $result['overlay_reviews_created'],
        $result['site_reviews_created']
      ),
    ];
  }

}

