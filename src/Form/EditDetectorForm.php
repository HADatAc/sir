<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class EditDetectorForm extends FormBase {

  protected $detectorUri;

  protected $detector;

  protected $sourceDetector;

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

  public function getSourceDetector() {
    return $this->sourceDetector;
  }

  public function setSourceDetector($obj) {
    return $this->sourceDetector = $obj; 
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
  public function buildForm(array $form, FormStateInterface $form_state, $detectoruri = NULL) {
    $uri=$detectoruri;
    $uri_decode=base64_decode($uri);
    $this->setDetectorUri($uri_decode);

    $sourceContent = '';
    $stemLabel = '';
    $codebookLabel = '';
    $this->setDetector($this->retrieveDetector($this->getDetectorUri()));
    if ($this->getDetector() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Detector."));
      self::backUrl();
      return;
    } else {
      if ($this->getDetector()->detectorStem != NULL) {
        $stemLabel = $this->getDetector()->detectorStem->hasContent . ' [' . $this->getDetector()->detectorStem->uri . ']';
      }
      if ($this->getDetector()->codebook != NULL) {
        $codebookLabel = $this->getDetector()->codebook->label . ' [' . $this->getDetector()->codebook->uri . ']';
      }
    }

    //dpm($this->getDetector());

    $form['detector_stem'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Detector Stem'),
      '#default_value' => $stemLabel,
      '#autocomplete_route_name' => 'sir.detector_stem_autocomplete',
    ];
    $form['detector_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
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
      if(strlen($form_state->getValue('detector_stem')) < 1) {
        $form_state->setErrorByName('detector_stem', $this->t('Please enter a valid detector stem'));
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
      self::backUrl();
      return;
    } 

    try{
  
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $hasStem = '';
      if ($form_state->getValue('detector_stem') != NULL && $form_state->getValue('detector_stem') != '') {
        $hasStem = Utils::uriFromAutocomplete($form_state->getValue('detector_stem'));
      } 

      $hasCodebook = '';
      if ($form_state->getValue('detector_codebook') != NULL && $form_state->getValue('detector_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
      } 

      $detectorJson = '{"uri":"'.$this->getDetector()->uri.'",'.
        '"typeUri":"'.VSTOI::DETECTOR.'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
        '"hasDetectorStem":"'.$hasStem.'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->detectorDel($this->getDetectorUri());
      $updatedDetector = $api->detectorAdd($detectorJson);    
      \Drupal::messenger()->addMessage(t("Detector has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Detector: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveDetector($detectorUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($detectorUri);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      return $obj->body;
    }
    return NULL; 
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.edit_detector');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}