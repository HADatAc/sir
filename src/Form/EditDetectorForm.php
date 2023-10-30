<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Constant;
use Drupal\sir\Utils;
use Drupal\sir\Vocabulary\VSTOI;

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

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $codebookLabel = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setDetector($this->retrieveDetector($this->getDetectorUri()));
    if ($this->getDetector() == NULL) {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Detector."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('detector'));
    } else {
      $wasGeneratedBy = $this->getDetector()->wasGeneratedBy;
      if ($this->getDetector()->wasDerivedFrom != NULL) {
        $this->setSourceDetector($this->retrieveDetector($this->getDetector()->wasDerivedFrom));
        if ($this->getSourceDetector() != NULL && $this->getSourceDetector()->hasContent != NULL) { 
          $sourceContent = $this->getSourceDetector()->hasContent;
        }
      }
      if ($this->getDetector()->codebook != NULL) {
        $codebookLabel = $this->getDetector()->codebook->label . ' [' . $this->getDetector()->codebook->uri . ']';
      }
    }

    //dpm($this->getDetector());

    $form['detector_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getDetector()->hasContent,
    ];
    $form['detector_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
    ];
    $form['detector_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
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
    $form['detector_was_derived_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Was Derived From'),
      '#default_value' => $sourceContent,
      '#disabled' => TRUE,
    ];
    $form['detector_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
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
      $form_state->setRedirectUrl(Utils::selectBackUrl('detector'));
      return;
    } 

    try{
  
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $hasCodebook = '';
      if ($form_state->getValue('detector_codebook') != NULL && $form_state->getValue('detector_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
      } 

      $wasDerivedFrom = '';
      if ($this->getSourceDetector() === NULL || $this->getSourceDetector()->uri === NULL) {
        $wasDerivedFrom = 'null';
      } else {
        $wasDerivedFrom = $this->getSourceDetector()->uri;
      } 

      $detectorJson = '{"uri":"'.$this->getDetector()->uri.'",'.
        '"typeUri":"'.VSTOI::DETECTOR.'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
        '"hasContent":"'.$form_state->getValue('detector_content').'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasLanguage":"'.$form_state->getValue('detector_language').'",'.
        '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
        '"comment":"'.$form_state->getValue('detector_description').'",'.
        '"wasDerivedFrom":"'.$wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('detector_was_generated_by').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->detectorDel($this->getDetectorUri());
      $updatedDetector = $fusekiAPIservice->detectorAdd($detectorJson);    
      \Drupal::messenger()->addMessage(t("Detector has been updated successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('detector'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Detector: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('detector'));
    }
  }

  public function retrieveDetector($detectorUri) {
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($detectorUri);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      return $obj->body;
    }
    return NULL; 
  }

}