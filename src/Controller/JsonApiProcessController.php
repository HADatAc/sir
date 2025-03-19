<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\rep\Utils;
/**
 * Class JsonApiProcessController
 * @package Drupal\sir\Controller
 */
class JsonApiProcessController extends ControllerBase{

  /**
   * @return JsonResponse
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $keyword = Xss::filter($input);
    $api = \Drupal::service('rep.api_connector');
    $process_list = $api->listByKeyword('process',$keyword,10,0);
    $obj = json_decode($process_list);
    $processes = [];
    if ($obj->isSuccessful) {
      $processes = $obj->body;
    }
    foreach ($processes as $process) {
      $results[] = [
        'value' => $process->label . ' [' . $process->uri . ']',
        'label' => $process->label,
      ];
    }
    return new JsonResponse($results);
  }

  public function loadDetectors(Request $request) {
    $api = \Drupal::service('rep.api_connector');
    $response = $api->detectorListFromInstrument($request->query->get('instrument_id'));

    // Decode Main JSON
    $data = json_decode($response, true);
    // Decode Body JSON
    $urls = json_decode($data['body'], true);

    $detectors = [];
    foreach ($urls as $url) {
      $detectorData = $api->getUri($url);
      $obj = json_decode($detectorData);
      $detectors[] = [
        'name' => isset($obj->body->label) ? $obj->body->label : '',
        'uri' => isset($obj->body->uri) ? $obj->body->uri : '',
        'status' => isset($obj->body->hasStatus) ? Utils::plainStatus($obj->body->hasStatus) : '',
        'hasStatus' => isset($obj->body->hasStatus) ? $obj->body->hasStatus : null,
      ];
    }
    return new JsonResponse($detectors);
  }

  public function updateDetectorWrapper(array &$form, FormStateInterface $form_state) {
    // Obtém o elemento que disparou o callback
    $trigger = $form_state->getTriggeringElement();

    // Extrai o índice do instrumento a partir do ID do campo
    $field_id = $trigger['#id'];
    if (preg_match('/instrument_selected_(\d+)/', $field_id, $matches)) {
      $i = $matches[1];
    }
    else {
      // Se não conseguir determinar o índice, retorna o wrapper completo
      return $form['process_instruments']['wrapper'];
    }

    // Obtém o valor selecionado (URI do instrumento)
    $instrument_uri = $form_state->getValue("instrument_selected_$i");

    if (!$instrument_uri) {
      // Se não houver URI, limpa o conteúdo do wrapper
      $form_state->set("instrument_detector_wrapper_$i", []);
      return $form['process_instruments']['wrapper']["instrument_$i"]['instrument_detector_wrapper_'.$i];
    }

    // Chama a API para obter a lista de detectores
    $api = \Drupal::service('rep.api_connector');
    $response = $api->detectorListFromInstrument($instrument_uri);

    // Decodifica o JSON da resposta
    $data = json_decode($response, true);
    if (!$data || !isset($data['body'])) {
      // Em caso de resposta inválida, limpa o conteúdo do wrapper
      $form_state->set("instrument_detector_wrapper_$i", []);
      return $form['process_instruments']['wrapper']["instrument_$i"]['instrument_detector_wrapper_'.$i];
    }

    // Decodifica o corpo da resposta
    $urls = json_decode($data['body'], true);

    // Processa os detectores
    $detectors = [];
    foreach ($urls as $url) {
      $detectorData = $api->getUri($url);
      $obj = json_decode($detectorData);
      $detectors[] = [
        'name' => isset($obj->body->label) ? $obj->body->label : '',
        'uri' => isset($obj->body->uri) ? $obj->body->uri : '',
        'status' => isset($obj->body->hasStatus) ? Utils::plainStatus($obj->body->hasStatus) : '',
        'hasStatus' => isset($obj->body->hasStatus) ? $obj->body->hasStatus : null,
      ];
    }

    // Armazena os detectores no Form State
    $form_state->set("instrument_detector_wrapper_$i", $detectors);

    // Retorna o wrapper atualizado
    return $form['process_instruments']['wrapper']["instrument_$i"]['instrument_detector_wrapper_'.$i];
  }

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
