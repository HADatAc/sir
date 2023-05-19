<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

class EditAttachmentForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  protected $attachmentUri;

  protected $attachment;

  public function getAttachmentUri() {
    return $this->attachmentUri;
  }

  public function setAttachmentUri($uri) {
    return $this->attachmentUri = $uri; 
  }

  public function getAttachment() {
    return $this->attachment;
  }

  public function setAttachment($obj) {
    return $this->attachment = $obj; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_attachment_form';
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
  public function buildForm(array $form, FormStateInterface $form_state, $attachmenturi = NULL) {
    $uri=$attachmenturi;
    $uri_decode=base64_decode($uri);
    $this->setAttachmentUri($uri_decode);

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $endpoint = "/sirapi/api/uri/".rawurlencode($this->getAttachmentUri());

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($api_url,$endpoint);
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setAttachment($obj->body);
      #dpm($this->getDetector());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Attachment."));
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
    }

    $form['attachment_uri'] = [
      '#type' => 'textfield',
      '#title' => t('Attachment URI'),
      '#value' => $this->getAttachmentUri(),
      '#disabled' => TRUE,
    ];
    $form['attachment_instrument'] = [
      '#type' => 'textfield',
      '#title' => t('Instrument URI'),
      '#value' => $this->getAttachment()->belongsTo,
      '#disabled' => TRUE,
    ];
    $form['attachment_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
      '#default_value' => $this->getAttachment()->hasPriority,
      '#disabled' => TRUE,
    ];
    $form['attachment_detector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Detector'),
      '#default_value' => $this->getAttachment()->hasDetector,
      '#autocomplete_route_name' => 'sir.attachment_detector_autocomplete',
    ];
    $form['new_detector_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Item'),
      '#name' => 'new_detector',
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
      if(strlen($form_state->getValue('attachment_priority')) < 1) {
        $form_state->setErrorByName('attachment_priority', $this->t('Please enter a valid priority value'));
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
      $url = Url::fromRoute('sir.manage_attachments');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getAttachment()->belongsTo));
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'new_detector') {
      $url = Url::fromRoute('sir.add_detector');
      $url->setRouteParameter('attachmenturi', base64_encode($this->getAttachmentUri()));
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

  public function addAttachment($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newAttachment = $fusekiAPIservice->attachmentAdd($api_url,$endpoint,$data);
    if(!empty($newAttachment)){
      return $newAttachment;
    }
    return [];
  }

  public function deleteAttachment($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $fusekiAPIservice->attachmentDel($api_url,$endpoint,$data);
    return true;
  }
  


}