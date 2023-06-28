<?php

namespace Drupal\sir;

use Drupal\Core\Http\ClientFactory;
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

  public function instrumentListAll() {
    $endpoint = "/sirapi/api/instrument/all";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function instrumentList($useremail) {
    $endpoint = "/sirapi/api/instrument/maintaineremail/".rawurlencode($useremail);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function instrumentRendering($type,$instrumentUri) {
    $endpoint = "/sirapi/api/instrument/totext/".$type."/".rawurlencode($instrumentUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function instrumentAdd($instrumentJson) {
    $endpoint = "/sirapi/api/instrument/create/".rawurlencode($instrumentJson);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function instrumentDel($instrumentUri) {
    $endpoint = "/sirapi/api/instrument/delete/".rawurlencode($instrumentUri);    
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function attachmentList($instrumentUri) {
    $endpoint = "/sirapi/api/attachment/byinstrument/".rawurlencode($instrumentUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
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

  public function detectorList($useremail) {
    $endpoint = "/sirapi/api/detector/maintaineremail/".rawurlencode($useremail);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function detectorListByKeyword($keyword) {
    $endpoint = "/sirapi/api/detector/keyword/".rawurlencode($keyword);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function detectorAdd($detectorJson) {
    $endpoint = "/sirapi/api/detector/create/".rawurlencode($detectorJson);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function detectorDel($detectorUri) {
    $endpoint = "/sirapi/api/detector/delete/".rawurlencode($detectorUri);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function detectorAttach($detectorUri,$attachmentUri) {
    $endpoint = "/sirapi/api/detector/attach/".rawurlencode($detectorUri)."/".rawurlencode($attachmentUri);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function attachmentReset($attachmentUri) {
    $endpoint = "/sirapi/api/detector/detach/".rawurlencode($attachmentUri);    
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function experienceList($useremail) {
    $endpoint = "/sirapi/api/experience/maintaineremail/".rawurlencode($useremail);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function experienceListByKeyword($keyword) {
    $endpoint = "/sirapi/api/experience/keyword/".rawurlencode($keyword);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function experienceAdd($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function experienceDel($experienceUri) {
    $endpoint = "/sirapi/api/experience/delete/".rawurlencode($experienceUri);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionList($experienceUri) {
    $endpoint = "/sirapi/api/responseoption/byexperience/".rawurlencode($experienceUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionAdd($api_url,$endpoint, $data) {
    return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
  }

  public function responseOptionDel($responseOptionUri) {
    $endpoint = "/sirapi/api/responseoption/delete/".rawurlencode($responseOptionUri);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function repositoryConf($api_url,$endpoint, $data) {
    return $this->perform_http_request('GET',$api_url.$endpoint,$data);          
  }

  public function repoInfo() {
    $endpoint = "/sirapi/api/repo";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function informantList() {
    $endpoint = "/sirapi/api/repo/table/informants";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function languageList() {
    $endpoint = "/sirapi/api/repo/table/languages";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function generationActivityList() {
    $endpoint = "/sirapi/api/repo/table/generationactivities";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function getApiUrl() {
    $config = \Drupal::config(static::CONFIGNAME);           
    return $config->get("api_url");
  }

  public static function perform_http_request($method, $url, $data = false) {   
    //dpm($url);    
    try {
      $client = new Client();
      $res = $client->request($method, $url, $data);
      //\Drupal::messenger()->addMessage(t("(perform_http_request) Status code: ".$res->getStatusCode()));
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