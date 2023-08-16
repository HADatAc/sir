<?php

namespace Drupal\sir;

use Drupal\Core\Http\ClientFactory;
use Drupal\sir\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException; 
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

class FusekiAPIConnector {
  private $client;
  private $query;
  private $error;
  private $error_message;
  private $bearer;

  /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  public function __construct(ClientFactory $client){
  }

  /**
   *   GENERIC
   */

  public function getUri($uri) {
    $endpoint = "/sirapi/api/uri/".rawurlencode($uri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function getUsage($uri) {
    $endpoint = "/sirapi/api/usage/".rawurlencode($uri);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function getDerivation($uri) {
    $endpoint = "/sirapi/api/derivation/".rawurlencode($uri);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  // valid values for elementType: "instrument", "detector", "experience", "responseoption"
  public function listByKeywordAndLanguage($elementType, $keyword, $language, $pageSize, $offset) {
    $endpoint = "/sirapi/api/".
      $elementType.
      "/keywordlanguage/".
      rawurlencode($keyword)."/".
      rawurlencode($language)."/".
      $pageSize."/".
      $offset;
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  // valid values for elementType: "instrument", "detector", "experience", "responseoption"
  public function listSizeByKeywordAndLanguage($elementType, $keyword, $language) {
    $endpoint = "/sirapi/api/".
      $elementType.
      "/keywordlanguage/total/".
      rawurlencode($keyword)."/".
      rawurlencode($language);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  // valid values for elementType: "instrument", "detector", "experience", "responseoption"
  public function listByMaintainerEmail($elementType, $maintaineremail, $pageSize, $offset) {
    $endpoint = "/sirapi/api/".
      $elementType.
      "/maintaineremail/".
      $maintaineremail."/".
      $pageSize."/".
      $offset;
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  // valid values for elementType: "instrument", "detector", "experience", "responseoption"
  public function listSizeByMaintainerEmail($elementType, $maintaineremail, ) {
    $endpoint = "/sirapi/api/".
      $elementType . 
      "/maintaineremail/total/" . 
      $maintaineremail;
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  /**
   *   INSTRUMENTS
   */

  public function instrumentListAll() {
    $endpoint = "/sirapi/api/instrument/all";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function instrumentList($useremail) {
    $endpoint = "/sirapi/api/instrument/maintaineremail/".rawurlencode($useremail);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function instrumentRendering($type,$instrumentUri) {
    if ($type == 'fhir' || $type == 'rdf') {
      $endpoint = "/sirapi/api/instrument/to".$type."/".rawurlencode($instrumentUri);
    } else {
      $endpoint = "/sirapi/api/instrument/totext/".$type."/".rawurlencode($instrumentUri);
    }
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function instrumentAdd($instrumentJson) {
    $endpoint = "/sirapi/api/instrument/create/".rawurlencode($instrumentJson);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function instrumentDel($instrumentUri) {
    $endpoint = "/sirapi/api/instrument/delete/".rawurlencode($instrumentUri);    
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();    
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /**
   *  
   *    ATTACHMENTS
   * 
   */

  public function attachmentList($instrumentUri) {
    $endpoint = "/sirapi/api/attachment/byinstrument/".rawurlencode($instrumentUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function attachmentAdd($instrumentUri,$totalAttachments) {
    $endpoint = "/sirapi/api/attachment/create/".rawurlencode($instrumentUri)."/".rawurlencode($totalAttachments);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function attachmentDel($attachmentUri) {
    $endpoint = "/sirapi/api/attachment/delete/".rawurlencode($attachmentUri);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function attachmentReset($attachmentUri) {
    $endpoint = "/sirapi/api/detector/detach/".rawurlencode($attachmentUri);    
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /**
   *   DETECTORS
   */

  public function detectorList($useremail) {
    $endpoint = "/sirapi/api/detector/maintaineremail/".rawurlencode($useremail);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function detectorListByKeyword($keyword) {
    $endpoint = "/sirapi/api/detector/keyword/".rawurlencode($keyword);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function detectorAdd($detectorJson) {
    $endpoint = "/sirapi/api/detector/create/".rawurlencode($detectorJson);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function detectorDel($detectorUri) {
    $endpoint = "/sirapi/api/detector/delete/".rawurlencode($detectorUri);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function detectorAttach($detectorUri,$attachmentUri) {
    $endpoint = "/sirapi/api/detector/attach/".rawurlencode($detectorUri)."/".rawurlencode($attachmentUri);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /**
   *   EXPERIENCE
   */

  public function experienceList($useremail) {
    $endpoint = "/sirapi/api/experience/maintaineremail/".rawurlencode($useremail);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function experienceListByKeyword($keyword) {
    $endpoint = "/sirapi/api/experience/keyword/".rawurlencode($keyword);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function experienceAdd($experienceJson) {
    $endpoint = "/sirapi/api/experience/create/".rawurlencode($experienceJson);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function experienceDel($experienceUri) {
    $endpoint = "/sirapi/api/experience/delete/".rawurlencode($experienceUri);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  /** 
   *   CODEBOOK SLOT
   */

  public function codebookSlotList($experienceUri) {
    $endpoint = "/sirapi/api/codebookslot/byexperience/".rawurlencode($experienceUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function codebookSlotAdd($experienceUri,$totalCodebookSlots) {
    $endpoint = "/sirapi/api/codebookslot/create/".rawurlencode($experienceUri)."/".rawurlencode($totalCodebookSlots);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function codebookSlotDel($codebookSlotUri) {
    $endpoint = "/sirapi/api/codebookslot/delete/".rawurlencode($codebookSlotUri);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function codebookSlotReset($codebookSlotUri) {
    $endpoint = "/sirapi/api/responseoption/detach/".rawurlencode($codebookSlotUri);    
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /** 
   *   RESPONSE OPTION
   */

  public function responseOptionList($experienceUri) {
    $endpoint = "/sirapi/api/responseoption/byexperience/".rawurlencode($experienceUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionListByKeyword($keyword) {
    $endpoint = "/sirapi/api/responseoption/keyword/".rawurlencode($keyword);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionAdd($responseoptionJSON) {
    $endpoint = "/sirapi/api/responseoption/create/".rawurlencode($responseoptionJSON);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function responseOptionDel($responseOptionUri) {
    $endpoint = "/sirapi/api/responseoption/delete/".rawurlencode($responseOptionUri);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionAttach($responseOptionUri,$codebookSlotUri) {
    $endpoint = "/sirapi/api/responseoption/attach/".rawurlencode($responseOptionUri)."/".rawurlencode($codebookSlotUri);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /**
   *   REPOSITORY
   */

  public function repoInfo() {
    $endpoint = "/sirapi/api/repo";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function repoInfoNewIP($api_url) {
    $endpoint = "/sirapi/api/repo";
    $method = "GET";
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function repoUpdateLabel($api_url, $label) {
    $endpoint = "/sirapi/api/repo/label/".rawurlencode($label);
    $method = "GET";
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoUpdateTitle($api_url, $title) {
    $endpoint = "/sirapi/api/repo/title/".rawurlencode($title);
    $method = "GET";
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoUpdateDescription($api_url, $description) {
    $endpoint = "/sirapi/api/repo/description/".rawurlencode($description);
    $method = "GET";
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoUpdateNamespace($api_url, $namespace, $baseUrl) {
    $endpoint = "/sirapi/api/repo/namespace/default/".rawurlencode($namespace)."/".rawurlencode($baseUrl);
    $method = "GET";
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoReloadNamespaceTriples() {
    $endpoint = "/sirapi/api/repo/ont/load";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoDeleteNamespaceTriples() {
    $endpoint = "/sirapi/api/repo/ont/delete";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /** 
   *
   *   ERROR METHODS    
   * 
   */

   public function getError() {
    return $this->error;
  }

  public function getErrorMessage() {
    return $this->error_message;
  }

  /**
   *   AUXILIARY TABLES
   */

  public function namespaceList() {
    $endpoint = "/sirapi/api/repo/table/namespaces";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function informantList() {
    $endpoint = "/sirapi/api/repo/table/informants";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    var_dump($api_url.$endpoint);
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function languageList() {
    $endpoint = "/sirapi/api/repo/table/languages";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function generationActivityList() {
    $endpoint = "/sirapi/api/repo/table/generationactivities";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = $this->getHeader();
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  /**
   *   AUXILIATY METHODS
   */

  public function getApiUrl() {
    $config = \Drupal::config(static::CONFIGNAME);           
    return $config->get("api_url");
  }

  public function getHeader() {
    if ($this->bearer == NULL) {
      $this->bearer = "Bearer " . JWT::jwt();
    }
    return ['headers' => 
      [
        'Authorization' => $this->bearer
      ]
    ];
  }

  public function perform_http_request($method, $url, $data = false) {   
    $client = new Client();
    $res=NULL;
    $this->error=NULL;
    $this->error_message="";
    try {
      $res = $client->request($method,$url,$data);
    } 
    catch(ConnectException $e){
      $this->error="CON";
      $this->error_message = "Connection error the following message: " . $e->getMessage();
      return(NULL);
    }
    catch(ClientException $e){
      $res = $e->getResponse();
      if($res->getStatusCode() != '200') {
        $this->error=$res->getStatusCode();
        $this->error_message = "API request returned the following status code: " . $res->getStatusCode();
        return(NULL);
      }
    } 
    return($res->getBody()); 
  }   

  public function parseObjectResponse($response, $methodCalled) {
    if ($this->error != NULL) {
      if ($this->error == 'CON') {
        \Drupal::messenger()->addError(t("Connection with API is broken. Either the Internet is down, the API is down or the API IP configuration is incorrect."));
      } else {
        \Drupal::messenger()->addError(t("API ERROR " . $this->error . ". Message: " . $this->error_message));
      }
      return NULL;
    }
    if ($response == NULL || $response == "") {
        \Drupal::messenger()->addError(t("API service has returned no response: called " . $methodCalled));
        return NULL;
    }
    $obj = json_decode($response);
    if ($obj->isSuccessful) {
      return $obj->body;
    }
    \Drupal::messenger()->addError(t("API service has failed with following message: " . $obj->body));
    return NULL; 
  }

}