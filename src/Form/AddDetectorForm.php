<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sir\Constant;
use Drupal\sir\Utils;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

class AddDetectorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_detector_form';
  }

  protected $sourceDetectorUri;

  protected $sourceDetector;

  protected $detectorStem;

  protected $detectorslotUri;

  protected $detectorslot;

  public function getSourceDetectorUri() {
    return $this->sourceDetectorUri;
  }

  public function setSourceDetectorUri($uri) {
    return $this->sourceDetectorUri = $uri; 
  }

  public function getSourceDetector() {
    return $this->sourceDetector;
  }

  public function setSourceDetector($obj) {
    return $this->sourceDetector = $obj; 
  }

  public function getDetectorStem() {
    return $this->detectorStem;
  }

  public function setDetectorStem($stem) {
    return $this->detectorStem = $stem; 
  }

  public function getDetectorSlotUri() {
    return $this->detectorslotUri;
  }

  public function setDetectorSlotUri($attachuri) {
    return $this->detectorslotUri = $attachuri; 
  }

  public function getDetectorSlot() {
    return $this->detectorslot;
  }

  public function setDetectorSlot($attachobj) {
    return $this->detectorslot = $attachobj; 
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourcedetectoruri = NULL, $detectorsloturi = NULL) {

    // ESTABLISH API SERVICE
    $api = \Drupal::service('sir.api_connector');

    // HANDLE SOURCE DETECTOR,  IF ANY
    $sourceuri=$sourcedetectoruri;
    if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
      $this->setSourceDetector(NULL);
      $this->setSourceDetectorUri('');
    } else {
      $sourceuri_decode=base64_decode($sourceuri);
      $this->setSourceDetectorUri($sourceuri_decode);
      $rawresponse = $api->getUri($this->getSourceDetectorUri());
      //dpm($rawresponse);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setSourceDetector($obj->body);
        #dpm($this->getDetector());
      } else {
        $this->setSourceDetector(NULL);
        $this->setSourceDetectorUri('');
      }
    }
    $disabledDerivationOption = ($this->getSourceDetector() === NULL);

    // HANDLE DETECTOR_SLOT, IF ANY
    $attachuri=$detectorsloturi;
    if ($attachuri === NULL || $attachuri === 'EMPTY') {
      $this->setDetectorSlot(NULL);
      $this->setDetectorSlotUri('');
    } else {
      $attachuri_decode=base64_decode($attachuri);
      $this->setDetectorSlotUri($attachuri_decode);
      if ($this->getDetectorSlotUri() != NULL) {
        $attachrawresponse = $api->getUri($this->getDetectorSlotUri());
        $attachobj = json_decode($attachrawresponse);
        if ($attachobj->isSuccessful) {
          $this->setDetectorSlot($attachobj->body);
        }
      }
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    if ($this->getSourceDetector() != NULL) {
      $sourceContent = $this->getSourceDetector()->hasContent;
    }

    $form['detector_stem'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Detector Stem'),
      '#autocomplete_route_name' => 'sir.detector_stem_autocomplete',
    ];
    $form['detector_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
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

    // ESTABLISH API SERVICE
    $api = \Drupal::service('sir.api_connector');

    if ($button_name != 'back') {

      if ($form_state->getValue('detector_stem') == NULL || $form_state->getValue('detector_stem') == '') {
        $form_state->setErrorByName('detector_stem', $this->t('Detector stem value is empty. Please enter a valid stem.'));
      }
      $stemUri = Utils::uriFromAutocomplete($form_state->getValue('detector_stem'));
      $this->setDetectorStem($api->parseObjectResponse($api->getUri($stemUri),'getUri'));
      if($this->getDetectorStem() == NULL) {
        $form_state->setErrorByName('detector_stem', $this->t('Value for Detector Stem is not valid. Please enter a valid stem.'));
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

    // ESTABLISH API SERVICE
    $api = \Drupal::service('sir.api_connector');

    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('detector'));
      return;
    } 

    try {

      $hasCodebook = '';
      if ($form_state->getValue('detector_codebook') != NULL && $form_state->getValue('detector_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
      } 

      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW DETECTOR
      $newDetectorUri = Utils::uriGen('detector');
      $detectorJson = '{"uri":"'.$newDetectorUri.'",'.
        '"typeUri":"'.VSTOI::DETECTOR.'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
        '"hasDetectorStem":"'.$this->getDetectorStem()->uri.'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';
      $api->detectorAdd($detectorJson);
    
      // IF IN THE CONTEXT OF AN EXISTING DETECTOR_SLOT, ATTACH THE NEWLY CREATED DETECTOR TO THE DETECTOR_SLOT
      if ($this->getDetectorSlot() != NULL) {
        $api->detectorAttach($newDetectorUri,$this->getDetectorSlotUri());
        \Drupal::messenger()->addMessage(t("Detector [" . $newDetectorUri ."] has been added and attached to intrument [" . $this->getDetectorSlot()->belongsTo . "] successfully."));
        $url = Url::fromRoute('sir.edit_detectorslot');
        $url->setRouteParameter('detectorsloturi', base64_encode($this->getDetectorSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      } else {        
        \Drupal::messenger()->addMessage(t("Detector has been added successfully."));
        $form_state->setRedirectUrl(Utils::selectBackUrl('detector'));
        return;
      }
    } catch(\Exception $e) {
      if ($this->getDetectorSlot() != NULL) {
        \Drupal::messenger()->addMessage(t("An error occurred while adding the Detector: ".$e->getMessage()));
        $url = Url::fromRoute('sir.edit_detectorslot');
        $url->setRouteParameter('detectorsloturi', base64_encode($this->getDetectorSlotUri()));
        $form_state->setRedirectUrl($url);
      } else {
        \Drupal::messenger()->addMessage(t("An error occurred while adding the Detector: ".$e->getMessage()));
        $form_state->setRedirectUrl(Utils::selectBackUrl('detector'));
        }
    }
  }

}