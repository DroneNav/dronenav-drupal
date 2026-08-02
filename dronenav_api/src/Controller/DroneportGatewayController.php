<?php

namespace Drupal\dronenav_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_api\Service\DroneNavApiGatewayService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * Provides Drupal gateway endpoints for DroneNav Droneports.
 */
final class DroneportGatewayController extends ControllerBase {

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
   * Returns all Zones from the DroneNav API.
   */
  public function list(): JsonResponse {
    $result = $this->gateway->getDroneports();

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

  /**
   * Returns one Droneport from the DroneNav API.
   */
  public function get(string $droneport_id): JsonResponse {
    $result = $this->gateway->getDroneport($droneport_id);

    if (!$result['success']) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $result['message'],
      ], $result['status']);
    }

    return new JsonResponse(
      $result['data'],
      $result['status']
    );
  }

  /**
   * Creates a Droneport.
   */
  public function createDroneport(Request $request): JsonResponse {

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

    $result = $this->gateway->createDroneport($payload);

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

  /**
   * Updates a Droneport.
   */
  public function updateDroneport(string $droneport_id, Request $request): JsonResponse {

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

    $result = $this->gateway->updateDroneport($droneport_id, $payload);

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

  /**
   * Deletes one Droneport from the DroneNav API.
   */
  public function delete(string $droneport_id): JsonResponse {
    $result = $this->gateway->deleteDroneport($droneport_id);

    if (!$result['success']) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => $result['message'],
      ], $result['status']);
    }

    return new JsonResponse(
      $result['data'],
      $result['status']
    );
  }


}

