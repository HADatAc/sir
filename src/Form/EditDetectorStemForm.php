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

class EditDetectorStemForm extends FormBase {

  protected $detectorStemUri;

  protected $detectorStem;

  protected $sourceDetectorStem;

  public function getDetectorStemUri() {
    return $this->detectorStemUri;
  }

  public function setDetectorStemUri($uri) {
    return $this->detectorStemUri = $uri;
  }

  public function getDetectorStem() {
    return $this->detectorStem;
  }

  public function setDetectorStem($obj) {
    return $this->detectorStem = $obj;
  }

  public function getSourceDetectorStem() {
    return $this->sourceDetectorStem;
  }

  public function setSourceDetectorStem($obj) {
    return $this->sourceDetectorStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_detectorstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $detectorstemuri = NULL) {
    $uri=$detectorstemuri;
    $uri_decode=base64_decode($uri);
    $this->setDetectorStemUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setDetectorStem($this->retrieveDetectorStem($this->getDetectorStemUri()));
    if ($this->getDetectorStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Detector Stem."));
      self::backUrl();
      return;
    } else {
      $wasGeneratedBy = $this->getDetectorStem()->wasGeneratedBy;
      if ($this->getDetectorStem()->wasDerivedFrom != NULL) {
        $this->setSourceDetectorStem($this->retrieveDetectorStem($this->getDetectorStem()->wasDerivedFrom));
        if ($this->getSourceDetectorStem() != NULL && $this->getSourceDetectorStem()->hasContent != NULL) {
          $sourceContent = $this->getSourceDetectorStem()->hasContent;
        }
      }
    }

    //dpm($this->getDetector());

    $form['detectorstem_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getDetectorStem()->hasContent,
    ];
    $form['detectorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getDetectorStem()->hasLanguage,
    ];
    $form['detectorstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getDetectorStem()->hasVersion,
    ];
    $form['detectorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getDetectorStem()->comment,
    ];
    $form['detectorstem_was_derived_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Was Derived From'),
      '#default_value' => $sourceContent,
      '#disabled' => TRUE,
    ];
    $form['detectorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
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
      if(strlen($form_state->getValue('detectorstem_content')) < 1) {
        $form_state->setErrorByName('detectorstem_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('detectorstem_language')) < 1) {
        $form_state->setErrorByName('detectorstem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('detectorstem_version')) < 1) {
        $form_state->setErrorByName('detectorstem_version', $this->t('Please enter a valid version'));
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

      $wasDerivedFrom = '';
      if ($this->getSourceDetectorStem() === NULL || $this->getSourceDetectorStem()->uri === NULL) {
        $wasDerivedFrom = 'null';
      } else {
        $wasDerivedFrom = $this->getSourceDetectorStem()->uri;
      }

      $detectorStemJson = '{"uri":"'.$this->getDetectorStem()->uri.'",'.
        '"typeUri":"'.VSTOI::DETECTOR_STEM.'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
        '"hasContent":"'.$form_state->getValue('detectorstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('detectorstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('detectorstem_version').'",'.
        '"comment":"'.$form_state->getValue('detectorstem_description').'",'.
        '"wasDerivedFrom":"'.$wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('detectorstem_was_generated_by').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->detectorStemDel($this->getDetectorStemUri());
      $updatedDetectorStem = $api->detectorStemAdd($detectorStemJson);
      \Drupal::messenger()->addMessage(t("Detector Stem has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Detector Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveDetectorStem($detectorStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($detectorStemUri);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      return $obj->body;
    }
    return NULL;
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, \Drupal::request()->getRequestUri());
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
