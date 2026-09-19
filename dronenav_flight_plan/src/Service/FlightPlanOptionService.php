<?php

namespace Drupal\dronenav_flight_plan\Service;

use Drupal\node\Entity\Node;


class FlightPlanOptionService {

  public function getSiteOptions(
    ?int $authority_nid
  ): array {

    $options = [];

    if (!$authority_nid) {
      return $options;
    }

    $authority = Node::load($authority_nid);

    if (
      !$authority ||
      !$authority->hasField('field_authority_id') ||
      $authority->get('field_authority_id')->isEmpty()
    ) {
      return $options;
    }

    $authority_id = $authority
      ->get('field_authority_id')
      ->value;

    $site_nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'site')
      ->condition('status', 1)
      ->condition('field_authority_id', $authority_id)
      ->sort('title')
      ->execute();

    if (!$site_nids) {
      return $options;
    }

    foreach (Node::loadMultiple($site_nids) as $site) {
      $options[$site->id()] = $site->label();
    }

    return $options;
  }

  public function getDronePortOptions(
    ?int $site_nid
  ): array {

    $options = [
      '_none' => '- None -',
    ];

    if (!$site_nid) {
      return $options;
    }

    $site = Node::load($site_nid);

    if (
      !$site ||
      !$site->hasField('field_overlay_uuid') ||
      $site->get('field_overlay_uuid')->isEmpty()
    ) {
      return $options;
    }

    $site_uuid = $site
      ->get('field_overlay_uuid')
      ->value;

    $droneport_nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'droneport')
      ->condition('status', 1)
      ->condition('field_parent_site_id', $site_uuid)
      ->sort('title')
      ->execute();

    if (!$droneport_nids) {
      return $options;
    }

    foreach (Node::loadMultiple($droneport_nids) as $droneport) {
      $options[$droneport->id()] = $droneport->label();
    }

    return $options;
  }

  public function getRouteOptions(
    ?int $origin_site_nid,
    ?int $destination_site_nid
  ): array {

    $options = [
      '_none' => '- None -',
    ];

    if (
      !$origin_site_nid ||
      !$destination_site_nid
    ) {
      return $options;
    }

    $route_nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'route')
      ->condition('status', 1)
      ->execute();

    if (!$route_nids) {
      return $options;
    }

    foreach (Node::loadMultiple($route_nids) as $route) {
      $options[$route->id()] = $route->label();
    }

    return $options;
  }




}
