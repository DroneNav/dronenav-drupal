<?php

namespace Drupal\dronenav_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_api\Service\DroneNavApiGatewayService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * Provides Drupal gateway endpoints for DroneNav Sites.
 */
final class SiteGatewayController extends ControllerBase {

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
   * Returns all Sites from the DroneNav API.
   */
  public function list(): JsonResponse {
    $result = $this->gateway->getSites();

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
   * Returns one Site from the DroneNav API.
   */
  public function get(string $site_id): JsonResponse {
    $result = $this->gateway->getSite($site_id);

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
   * Creates a Site.
   */
  public function createSite(Request $request): JsonResponse {

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

    $result = $this->gateway->createSite($payload);

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
   * Updates a Site.
   */
  public function updateSite(string $site_id, Request $request): JsonResponse {

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

    $result = $this->gateway->updateSite($site_id, $payload);

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
   * Deletes one Site from the DroneNav API.
   */
  public function delete(string $site_id): JsonResponse {
    $result = $this->gateway->deleteSite($site_id);

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

