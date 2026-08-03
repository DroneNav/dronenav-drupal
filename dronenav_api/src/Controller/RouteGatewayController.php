<?php

namespace Drupal\dronenav_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_api\Service\DroneNavApiGatewayService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * Provides Drupal gateway endpoints for DroneNav Routes.
 */
final class RouteGatewayController extends ControllerBase {

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
   * Returns all Routes from the DroneNav API.
   */
  public function list(): JsonResponse {
    $result = $this->gateway->getRoutes();

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
   * Returns one Route from the DroneNav API.
   */
  public function get(string $route_id): JsonResponse {
    $result = $this->gateway->getRoute($route_id);

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
   * Returns Route Context Package from the DroneNav API.
   */
  public function getContextPackage(string $route_id): JsonResponse {
    $result = $this->gateway->getRouteContextPackage($route_id);

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
   * Creates a Route.
   */
  public function createRoute(Request $request): JsonResponse {

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

    $result = $this->gateway->createRoute($payload);

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
   * Upates a Route.
   */
  public function updateRoute(string $route_id, Request $request): JsonResponse {

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

    $result = $this->gateway->updateRoute($route_id, $payload);

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
   * Deletes one Route from the DroneNav API.
   */
  public function delete(string $route_id): JsonResponse {
    $result = $this->gateway->deleteRoute($route_id);

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

