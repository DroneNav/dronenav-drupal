<?php

namespace Drupal\dronenav_flight_plan\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\dronenav_flight_plan\Service\FlightStatusUpdateService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives Flight Plan status updates from NAVProxy.
 */
final class FlightStatusCallbackController extends ControllerBase {

  /**
   * Constructs the callback controller.
   */
  public function __construct(
    private readonly FlightStatusUpdateService $flightStatusUpdateService,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Creates the controller from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_flight_plan.flight_status_update'),
      $container->get('logger.channel.dronenav_flight_plan'),
    );
  }

  /**
   * Receives a Flight Plan status callback from NAVProxy.
   */
  public function receive(Request $request): JsonResponse {
    $configured_token = trim((string) Settings::get(
      'dronenav_navproxy_callback_token',
      ''
    ));

    if ($configured_token === '') {
      $this->logger->critical(
        'The NAVProxy callback token is not configured in settings.php.'
      );

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'The callback endpoint is not configured.',
      ], 503);
    }

    $provided_token = trim((string) $request->headers->get(
      'X-DroneNav-Callback-Token',
      ''
    ));

    if (
      $provided_token === ''
      || !hash_equals($configured_token, $provided_token)
    ) {
      $this->logger->warning(
        'A Flight Plan status callback was rejected because authentication failed.'
      );

      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Unauthorized.',
      ], 401);
    }

    $content_type = strtolower((string) $request->headers->get(
      'Content-Type',
      ''
    ));

    if (!str_contains($content_type, 'application/json')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Content-Type must be application/json.',
      ], 415);
    }

    $body = trim($request->getContent());

    if ($body === '') {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'The request body is empty.',
      ], 400);
    }

    try {
      $payload = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'The request body contains invalid JSON.',
      ], 400);
    }

    if (!is_array($payload)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'The request body must contain a JSON object.',
      ], 400);
    }

    $flight_execution_id = trim((string) (
      $payload['flight_execution_id'] ?? ''
    ));

    $status = strtolower(trim((string) (
      $payload['status'] ?? ''
    )));

    $occurred_at = trim((string) (
      $payload['occurred_at'] ?? ''
    ));

    if ($flight_execution_id === '') {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'flight_execution_id is required.',
      ], 400);
    }

    if ($status === '') {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'status is required.',
      ], 400);
    }

    if ($occurred_at !== '') {
      try {
        new \DateTimeImmutable($occurred_at);
      }
      catch (\Exception $exception) {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'occurred_at must be a valid ISO-8601 datetime.',
        ], 400);
      }
    }

    $result = $this->flightStatusUpdateService->updateStatus(
      $flight_execution_id,
      $status,
      $occurred_at !== '' ? $occurred_at : NULL,
    );

    return new JsonResponse(
      $result,
      $result['http_status'] ?? 200
    );
  }

}

