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

  /**
   *   GENERIC
   */

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
    $data = [];
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
    $data = [];
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
    $data = [];
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
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  /**
   *   INSTRUMENTS
   */

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
    if ($type == 'fhir' || $type == 'rdf') {
      $endpoint = "/sirapi/api/instrument/to".$type."/".rawurlencode($instrumentUri);
    } else {
      $endpoint = "/sirapi/api/instrument/totext/".$type."/".rawurlencode($instrumentUri);
    }
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

  /**
   *    ATTACHMENTS
   */

  public function attachmentList($instrumentUri) {
    $endpoint = "/sirapi/api/attachment/byinstrument/".rawurlencode($instrumentUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function attachmentAdd($instrumentUri,$totalAttachments) {
    $endpoint = "/sirapi/api/attachment/create/".rawurlencode($instrumentUri)."/".rawurlencode($totalAttachments);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function attachmentDel($attachmentUri) {
    $endpoint = "/sirapi/api/attachment/delete/".rawurlencode($attachmentUri);
    $method = 'POST';
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

  /**
   *   DETECTORS
   */

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

  /**
   *   EXPERIENCE
   */

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

  public function experienceAdd($experienceJson) {
    $endpoint = "/sirapi/api/experience/create/".rawurlencode($experienceJson);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function experienceDel($experienceUri) {
    $endpoint = "/sirapi/api/experience/delete/".rawurlencode($experienceUri);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  /** 
   *   CODEBOOK SLOT
   */

  public function codebookSlotList($experienceUri) {
    $endpoint = "/sirapi/api/codebookslot/byexperience/".rawurlencode($experienceUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function codebookSlotAdd($experienceUri,$totalCodebookSlots) {
    $endpoint = "/sirapi/api/codebookslot/create/".rawurlencode($experienceUri)."/".rawurlencode($totalCodebookSlots);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function codebookSlotDel($codebookSlotUri) {
    $endpoint = "/sirapi/api/codebookslot/delete/".rawurlencode($codebookSlotUri);
    $method = 'POST';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function codebookSlotReset($codebookSlotUri) {
    $endpoint = "/sirapi/api/responseoption/detach/".rawurlencode($codebookSlotUri);    
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /** 
   *   RESPONSE OPTION
   */

  public function responseOptionList($experienceUri) {
    $endpoint = "/sirapi/api/responseoption/byexperience/".rawurlencode($experienceUri);
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionListByKeyword($keyword) {
    $endpoint = "/sirapi/api/responseoption/keyword/".rawurlencode($keyword);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionAdd($responseoptionJSON) {
    $endpoint = "/sirapi/api/responseoption/create/".rawurlencode($responseoptionJSON);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function responseOptionDel($responseOptionUri) {
    $endpoint = "/sirapi/api/responseoption/delete/".rawurlencode($responseOptionUri);
    $method = "POST";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function responseOptionAttach($responseOptionUri,$codebookSlotUri) {
    $endpoint = "/sirapi/api/responseoption/attach/".rawurlencode($responseOptionUri)."/".rawurlencode($codebookSlotUri);
    $method = 'GET';
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /**
   *   REPOSITORY
   */

  public function repoInfo() {
    $endpoint = "/sirapi/api/repo";
    $method = "GET";
    $api_url = $this->getApiUrl();
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function repoInfoNewIP($api_url) {
    $endpoint = "/sirapi/api/repo";
    $method = "GET";
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);   
  }

  public function repoUpdateLabel($api_url, $label) {
    $endpoint = "/sirapi/api/repo/label/".rawurlencode($label);
    $method = "GET";
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoUpdateTitle($api_url, $title) {
    $endpoint = "/sirapi/api/repo/title/".rawurlencode($title);
    $method = "GET";
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoUpdateDescription($api_url, $description) {
    $endpoint = "/sirapi/api/repo/description/".rawurlencode($description);
    $method = "GET";
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  public function repoUpdateNamespace($api_url, $namespace, $baseUrl) {
    $endpoint = "/sirapi/api/repo/namespace/default/".rawurlencode($namespace)."/".rawurlencode($baseUrl);
    $method = "GET";
    $data = [];
    return $this->perform_http_request($method,$api_url.$endpoint,$data);          
  }

  /**
   *   AUXILIARY TABLES
   */

  public function namespaceList() {
    $endpoint = "/sirapi/api/repo/table/namespaces";
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

  /**
   *   AUXILIATY METHODS
   */

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
        $error_message = "API request returned the following error message:" . $e->getMessage();
        Drupal::messenger()->addMessage($error_message);
        return(NULL);
      }
      return($res->getBody());
    } catch(Exception $e){
      $error_message = "Site IP may be incorrect. Error message:" . $e->getMessage();
      Drupal::messenger()->addMessage($error_message);
      return(NULL);
    }
    
  }

}