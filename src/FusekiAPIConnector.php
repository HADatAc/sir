<?php

namespace Drupal\sir;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\JsonResponse;


class FusekiAPIConnector {
  private $client;
  private $query;

  /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  public function __construct(ClientFactory $client){
  }

  public function getApiUrl() {
    $config = \Drupal::config(static::CONFIGNAME);           
    return $config->get("api_url");
  }

  public function getUri($uri) {
    $endpoint = "/sirapi/api/uri/".rawurlencode($uri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [
      //if we add auth in future
      'auth' => ['user', 'pass']
    ];    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function instrumentsList($api_url,$endpoint) {
    $instruments = [];
    //if we add auth in future
    $data = [
          'auth' => ['user', 'pass']
    ];
    
    $instruments = $this->perform_http_request('GET',$api_url.$endpoint,$data);   
    
    return($instruments);
  }

  public function instrumentAdd($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function instrumentDel($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function attachmentList($instrumentUri) {
    $endpoint = "/sirapi/api/attachment/byinstrument/".rawurlencode($instrumentUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [
      //if we add auth in future
      'auth' => ['user', 'pass']
    ];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function attachmentAdd($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function attachmentDel($attachmentUri) {
    $endpoint = "/sirapi/api/attachment/delete/".rawurlencode($attachmentUri);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function detectorList($api_url,$endpoint) {
    $detectors = [];
    //if we add auth in future
    $data = [
          'auth' => ['user', 'pass']
    ];
    
    $detectors = $this->perform_http_request('GET',$api_url.$endpoint,$data);   
    
    return($detectors);
  }

  public function detectorAdd($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function detectorDel($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function detectorAttach($api_url,$endpoint, $data) {
    return $this->perform_http_request('GET',$api_url.$endpoint,$data);          
  }

  public function detectorDetach($api_url,$endpoint, $data) {
    return $this->perform_http_request('GET',$api_url.$endpoint,$data);          
  }

  public function experiencesList($api_url,$endpoint) {
    $experiences = [];
    //if we add auth in future
    $data = [
          'auth' => ['user', 'pass']
    ];
    
    $experiences = $this->perform_http_request('GET',$api_url.$endpoint,$data);   
    
    return($experiences);
  }

  public function experienceAdd($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function experienceDel($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function responseOptionList($experienceUri) {
    $endpoint = "/sirapi/api/responseoption/byexperience/".rawurlencode($experienceUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [
      //if we add auth in future
      'auth' => ['user', 'pass']
    ];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionAdd($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function responseOptionDel($responseOptionUri) {
    $endpoint = "/sirapi/api/responseoption/delete/".rawurlencode($responseOptionUri);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [
      //if we add auth in future
      'auth' => ['user', 'pass']
    ];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function repositoryConf($api_url,$endpoint, $data) {
    return $this->perform_http_request('GET',$api_url.$endpoint,$data);          
  }

  public function languageList() {
    $endpoint = "/sirapi/api/repo/table/languages";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [
      //if we add auth in future
      'auth' => ['user', 'pass']
    ];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public static function perform_http_request($method, $url, $data = false) {   
    //dpm($url);    
    try {
      $client = new Client();
      $res = $client->request($method, $url, $data);
      if($res->getStatusCode() != '200') {
        echo "error";
        exit();
      }
      return($res->getBody());
    } catch (RequestException $e) {
      // log exception
      print_r($e);
    }
  }


}