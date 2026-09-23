<?php

namespace Drupal\dronenav_survey_workbench\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

class SurveyWorkbenchContextService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Returns the current user's Home Authority UUID.
   */
  public function getHomeAuthorityId(): ?string {
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());

    if (
      !$user ||
      !$user->hasField('field_authority') ||
      $user->get('field_authority')->isEmpty()
    ) {
      return NULL;
    }

    $authority = $user->get('field_authority')->entity;

    if (
      !$authority ||
      !$authority->hasField('field_authority_id') ||
      $authority->get('field_authority_id')->isEmpty()
    ) {
      return NULL;
    }

    return $authority->get('field_authority_id')->value;
  }

  /**
   * Returns the current user's Home Authority node ID.
   */
  public function getHomeAuthorityNodeId(): ?int {
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());

    if (
      !$user ||
      !$user->hasField('field_authority') ||
      $user->get('field_authority')->isEmpty()
    ) {
      return NULL;
    }

    return (int) $user->get('field_authority')->target_id;
  }


}

