<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

class EditDetectorForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  protected $detectorUri;

  protected $detector;

  protected $instrumentUri;

  protected $priority;

  public function getDetectorUri() {
    return $this->detectorUri;
  }

  public function setDetectorUri($uri) {
    return $this->detectorUri = $uri; 
  }

  public function getDetector() {
    return $this->detector;
  }

  public function setDetector($obj) {
    return $this->detector = $obj; 
  }

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($instUri) {
    return $this->intrumentUri = $instUri; 
  }

  public function getPriority() {
    return $this->priority;
  }

  public function setPriority($priority) {
    return $this->priority = $priority; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_detector_form';
  }

  /**
     * {@inheritdoc}
     */

     protected function getEditableConfigNames() {
      return [
          static::CONFIGNAME,
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL, $prty = NULL) {
    $uri=$instrumenturi;
    $uri_decode=base64_decode($uri);
    $this->setInstrumentUri($uri_decode);

    $prty_orig=$prty;
    $prty_decode=base64_decode($prty_orig);
    $this->setPriority($prty_decode);

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $endpoint = "/sirapi/api/uri/".rawurlencode($this->getDetectorUri());

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($api_url,$endpoint);
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setDetector($obj->body);
      #dpm($this->getDetector());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Detector."));
      $url = Url::fromRoute('sir.manage_detectors');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetector()->isInstrumentAttachment));
      $form_state->setRedirectUrl($url);
    }

    $form['detector_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getDetector()->hasContent,
    ];
    $form['detector_experience'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Experience'),
      '#default_value' => $this->getDetector()->hasExperience,
      '#autocomplete_route_name' => 'sir.detector_experience_autocomplete',
    ];
    $form['detector_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
      '#default_value' => $this->getDetector()->hasLanguage,
    ];
    $form['detector_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getDetector()->hasVersion,
    ];
    $form['detector_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getDetector()->comment,
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];


    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('detector_priority')) < 1) {
        $form_state->setErrorByName('detector_priority', $this->t('Please enter a valid priority value'));
      }
      if(strlen($form_state->getValue('detector_content')) < 1) {
        $form_state->setErrorByName('detector_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('detector_language')) < 1) {
        $form_state->setErrorByName('detector_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('detector_version')) < 1) {
        $form_state->setErrorByName('detector_version', $this->t('Please enter a valid version'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_detectors');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetector()->isInstrumentAttachment));
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      $config = $this->config(static::CONFIGNAME);     
      $api_url = $config->get("api_url");
      $repository_abbreviation = $config->get("repository_abbreviation");
  
      $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();

      $data = [
        'uri' => $this->getDetector()->uri,
        'typeUri' => 'http://hadatac.org/ont/vstoi#Detector',
        'hascoTypeUri' => 'http://hadatac.org/ont/vstoi#Detector',
        'isInstrumentAttachment' => $this->getDetector()->isInstrumentAttachment,
        'hasPriority' => $form_state->getValue('detector_priority'),
        'hasContent' => $form_state->getValue('detector_content'),
        'hasExperience' => $form_state->getValue('detector_experience'),
        'hasLanguage' => $form_state->getValue('detector_language'),
        'hasVersion' => $form_state->getValue('detector_version'),
        'comment' => $form_state->getValue('detector_description'),
        'hasSIRMaintainerEmail' => $uemail, 
      ];
      
      $datap = '{"uri":"'. $this->getDetector()->uri .'",'.
        '"typeUri":"http://hadatac.org/ont/vstoi#Detector",'.
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#Detector",'.
        '"isInstrumentAttachment":"' . $this->getDetector()->isInstrumentAttachment . '",'.
        '"hasPriority":"'.$form_state->getValue('detector_priority').'",'.
        '"hasContent":"'.$form_state->getValue('detector_content').'",'.
        '"hasExperience":"'.$form_state->getValue('detector_experience').'",'.
        '"hasLanguage":"'.$form_state->getValue('detector_language').'",'.
        '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
        '"comment":"'.$form_state->getValue('detector_description').'",'.
        '"hasSIRMaintainerEmail":"'.$uemail.'"}';

      $dataJ = json_encode($data);
    
      $dataE = rawurlencode($datap);

      // UPDATE BY DELETING AND CREATING
      $uriEncoded = rawurlencode($this->getDetectorUri());
      $this->deleteDetector($api_url,"/sirapi/api/detector/delete/".$uriEncoded,[]);    
      $updatedDetector = $this->addDetector($api_url,"/sirapi/api/detector/create/".$dataE,$data);
    
      \Drupal::messenger()->addMessage(t("Detector has been updated successfully."));
      $url = Url::fromRoute('sir.manage_detectors');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetector()->isInstrumentAttachment));
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Detector: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_detectors');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetector()->isInstrumentAttachment));
      $form_state->setRedirectUrl($url);
    }

  }

  public function addDetector($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newDetector = $fusekiAPIservice->detectorAdd($api_url,$endpoint,$data);
    if(!empty($newDetectort)){
      return $newDetector;
    }
    return [];
  }

  public function deleteDetector($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $fusekiAPIservice->detectorDel($api_url,$endpoint,$data);
    return true;
  }
  


}