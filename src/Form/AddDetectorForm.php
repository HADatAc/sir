<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\sir\Entity\Tables;

class AddDetectorForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_detector_form';
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
  public function buildForm(array $form, FormStateInterface $form_state, $sourcedetectoruri = NULL) {
    $config = $this->config(static::CONFIGNAME);           
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $api_url = $config->get("api_url");

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = array(t('Original'), t('Translation'), t('Generalization'), t('Specialization'));

    $form['detector_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
    ];
    $form['detector_experience'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Experience'),
      '#autocomplete_route_name' => 'sir.detector_experience_autocomplete',
    ];
    $form['detector_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['detector_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['detector_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['detector_was_derived_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Was Derived From'),
      '#default_value' => '',
      '#disabled' => TRUE,
    ];
    $form['detector_was_derived_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => t('Original'),
      '#disabled' => TRUE,
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
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
      $form_state->setRedirectUrl($url);
      return;
  } 

    try{
      $config = $this->config(static::CONFIGNAME);     
      $api_url = $config->get("api_url");
      $repository_abbreviation = $config->get("repository_abbreviation");
  
      $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();


      // CREATE A NEW DETECTOR
      $iid = time().rand(10000,99999).$uid;
      $data = [
        'uri' => 'http://hadatac.org/kb/test/Detector'.$iid,
        'typeUri' => 'http://hadatac.org/ont/vstoi#Detector',
        'hascoTypeUri' => 'http://hadatac.org/ont/vstoi#Detector',
        'hasContent' => $form_state->getValue('detector_content'),
        'hasExperience' => $form_state->getValue('detector_experience'),
        'hasLanguage' => $form_state->getValue('detector_language'),
        'hasVersion' => $form_state->getValue('detector_version'),
        'comment' => $form_state->getValue('detector_description'),
        'hasSIRMaintainerEmail' => $uemail, 
      ];
      $datap = '{"uri":"http://hadatac.org/kb/test/Detector'.$iid.'",'.
        '"typeUri":"http://hadatac.org/ont/vstoi#Detector",'.
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#Detector",'.
        '"hasContent":"'.$form_state->getValue('detector_content').'",'.
        '"hasExperience":"'.$form_state->getValue('detector_experience').'",'.
        '"hasLanguage":"'.$form_state->getValue('detector_language').'",'.
        '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
        '"comment":"'.$form_state->getValue('detector_description').'",'.
        '"hasSIRMaintainerEmail":"'.$uemail.'"}';
      $dataJ = json_encode($data);
      $dataE = rawurlencode($datap);
      $newDetector = $this->addDetector($api_url,"/sirapi/api/detector/create/".$dataE,$data);

      $url = Url::fromRoute('sir.manage_detectors');
      $form_state->setRedirectUrl($url);
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Detector: ".$e->getMessage()));
      $url = Url::fromRoute('sir.edit_attachment');
      $url->setRouteParameter('attachmenturi', base64_encode($this->getAttachmentUri()));
      $form_state->setRedirectUrl($url);
    }

  }

  public function addDetector($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newDetector = $fusekiAPIservice->responseOptionAdd($api_url,$endpoint,$data);
    if(!empty($newDetectort)){
      return $newDetector;
    }
    return [];
  }


}