<?php

namespace Drupal\dronenav_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_api\Service\DroneNavApiGatewayService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides Drupal gateway endpoints for DroneNav Reference Data.
 */
final class ReferenceDataGatewayController extends ControllerBase {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly DroneNavApiGatewayService $gateway,
  ) {}

  /**
   * Creates the controller from Drupal's service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_api.gateway')
    );
  }

  /**
   * Returns all Reference Data from the DroneNav API.
   */
  public function get(): JsonResponse {
    $result = $this->gateway->getReferenceData();

    if (!$result['success']) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $result['message'],
      ], $result['status']);
    }

    /*
     * Preserve the Flask API response shape for the React application.
     */
    return new JsonResponse(
      $result['data'],
      $result['status']
    );
  }

}

