<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\rep\Utils;
/**
 * Class JsonApiTaskController
 * @package Drupal\sir\Controller
 */
class JsonApiTaskController extends ControllerBase{

  /**
   * @return JsonResponse
   */
  public function handleTasksAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $keyword = Xss::filter($input);
    $api = \Drupal::service('rep.api_connector');
    $task_list = $api->listByKeyword('task',$keyword,10,0);
    $obj = json_decode($task_list);
    $tasks = [];
    if ($obj->isSuccessful) {
      $processes = $obj->body;
    }
    foreach ($tasks as $task) {
      $results[] = [
        'value' => $task->label . ' [' . $task->uri . ']',
        'label' => $task->label,
      ];
    }
    return new JsonResponse($results);
  }
}
