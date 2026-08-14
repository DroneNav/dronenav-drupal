<?php

namespace Drupal\dronenav_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dronenav_api\Service\DroneNavApiGatewayService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides Drupal gateway endpoints for DroneNav Governance Overlays.
 */
final class GovernanceOverlayGatewayController extends ControllerBase {

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
   * Returns one overlay site package from the DroneNav API.
   */
  public function getPackage(string $overlay_id): JsonResponse {
    $result = $this->gateway->getOverlayPackage($overlay_id);

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
   * Survey an Overlay Site Package.
   */
  public function surveyPackage(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->surveyOverlayPackage($overlay_id, $payload);

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
   * Expire Survey of an Overlay Site Package.
   */
  public function expireSurveyPackage(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->expireSurveyOverlayPackage($overlay_id, $payload);

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
   * Activate an Overlay Site Package.
   */
  public function activatePackage(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->activateSitePackage($overlay_id, $payload);

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
   * Deactivate an Overlay Site Package.
   */
  public function deactivatePackage(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->deactivateSitePackage($overlay_id, $payload);

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
   * Survey a site overlay.
   */
  public function surveySiteOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->surveyOverlay('site', $overlay_id, $payload);

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
   * Survey a zone overlay.
   */
  public function surveyZoneOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->surveyOverlay('zone', $overlay_id, $payload);

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
   * Survey a droneport overlay.
   */
  public function surveyDroneportOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->surveyOverlay('droneport', $overlay_id, $payload);

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
   * Survey a route overlay.
   */
  public function surveyRouteOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->surveyOverlay('route', $overlay_id, $payload);

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
   * Expire a survey site overlay.
   */
  public function expireSurveySiteOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->expireOverlaySurvey('site', $overlay_id, $payload);

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
   * Expire a survey zone overlay.
   */
  public function expireSurveyZoneOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->expireOverlaySurvey('zone', $overlay_id, $payload);

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
   * Expire a survey droneport overlay.
   */
  public function expireSurveyDroneportOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->expireOverlaySurvey('droneport', $overlay_id, $payload);

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
   * Expire a survey route overlay.
   */
  public function expireSurveyRouteOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->expireOverlaySurvey('route', $overlay_id, $payload);

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
   * Activate a zone overlay.
   */
  public function activateZoneOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->activateOverlay('zone', $overlay_id, $payload);

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
   * Activate a droneport overlay.
   */
  public function activateDroneportOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->activateOverlay('droneport', $overlay_id, $payload);

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
   * Activate a route overlay.
   */
  public function activateRouteOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->activateOverlay('route', $overlay_id, $payload);

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
   * Deactivate a zone overlay.
   */
  public function deactivateZoneOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->deactivateOverlay('zone', $overlay_id, $payload);

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
   * Deactivate a droneport overlay.
   */
  public function deactivateDroneportOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->deactivateOverlay('droneport', $overlay_id, $payload);

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
   * Deactivate a route overlay.
   */
  public function deactivateRouteOverlay(string $overlay_id, Request $request): JsonResponse {

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

    $result = $this->gateway->deactivateOverlay('route', $overlay_id, $payload);

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

