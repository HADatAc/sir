<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class LoadMoreController extends ControllerBase {
  public function loadMoreCards() {
    // Example response to return more card content
    $response_data = [
      'commands' => [
        [
          'command' => 'insert',
          'selector' => '#element-table',
          'data' => '<div class="col-md-4">New Card Content Loaded via AJAX</div>',
        ],
      ],
    ];

    return new JsonResponse($response_data);
  }
}
