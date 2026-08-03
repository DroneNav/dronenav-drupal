<?php

namespace Drupal\dronenav_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_api\Service\DroneNavApiGatewayService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides Drupal gateway endpoints for DroneNav Reference Data.
 */
final class FlightContextGatewayController extends ControllerBase {

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
   * Returns a flight context.
   */
  public function get(Request $request): JsonResponse {

    $payload = json_decode(
      $request->getContent(),
      TRUE
    );

    if (!is_array($payload)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid JSON request.',
      ], 400);
    }

    $result = $this->gateway->getFlightContext($payload);

    if (!$result['success']) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $result['message'],
        'data' => $result['data'],
      ], $result['status']);
    }

    return new JsonResponse(
      $result['data'],
      $result['status']
    );
  }



}

