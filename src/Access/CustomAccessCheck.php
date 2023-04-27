<?php

namespace Drupal\sir\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

class CustomAccessCheck implements AccessInterface {

  public function access(AccountInterface $account) {
    // Check if the user is authenticated.
    if ($account->isAuthenticated()) {
      return AccessResult::allowed();
    }

    // Deny access if the user is not authenticated.
    return AccessResult::forbidden();
  }
}