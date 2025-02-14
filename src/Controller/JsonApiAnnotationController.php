<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiAnnotationController
 * @package Drupal\sir\Controller
 */
class JsonApiAnnotationController extends ControllerBase{

  /**
   * @return JsonResponse
   */
  // public function handleAutocomplete(Request $request) {
  //   $results = [];
  //   $input = $request->query->get('q');
  //   if (!$input) {
  //     return new JsonResponse($results);
  //   }
  //   $keyword = Xss::filter($input);

  //   // $containeruri = base64_decode($containeruri);
  //   // $manageremail = base64_decode($manageremail);
  //   //dpm($containeruri);
  //   $api = \Drupal::service('rep.api_connector');
  //   $annotation_list = $api->listByKeyword('annotation',$keyword,10,0);
  //   //$annotation_list = $api->listByManagerEmailByContainer($containeruri, 'annotation',$manageremail,10,0);
  //   $obj = json_decode($annotation_list);
  //   $annotations = [];
  //   if ($obj->isSuccessful) {
  //     $annotations = $obj->body;
  //   }
  //   foreach ($annotations as $annotation) {
  //     $results[] = [
  //       'value' => $annotation->hasContentWithStyle . ' [' . $annotation->uri . ']',
  //       'label' => $annotation->hasContentWithStyle . ' [' . $annotation->uri . ']',
  //     ];
  //   }
  //   return new JsonResponse($results);
  // }
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');

    if (!$input) {
        return new JsonResponse($results);
    }

    $keyword = Xss::filter($input);

    $api = \Drupal::service('rep.api_connector');
    $annotation_list = $api->listByKeyword('annotation', $keyword, 10, 0);
    $obj = json_decode($annotation_list);
    $annotations = $obj->isSuccessful ? $obj->body : [];

    foreach ($annotations as $annotation) {
        $originalText = $annotation->hasContentWithStyle;

        // Expressão regex para encontrar a palavra-chave e o contexto ao redor
        if (preg_match('/(.{0,5})(' . preg_quote($keyword, '/') . ')(.{0,5})/iu', $originalText, $matches)) {
            $trimmedText = trim($matches[1] . $matches[2] . $matches[3]);
        } else {
            $trimmedText = $originalText; // Se não encontrar, mantém o original
        }

        $results[] = [
            'value' => html_entity_decode($trimmedText) . ' [' . $annotation->uri . ']',
            'label' => $trimmedText . ' [' . $annotation->uri . ']',
        ];
    }

    return new JsonResponse($results);
}


}
