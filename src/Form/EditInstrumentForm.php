<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sir\Constant;
use Drupal\sir\Utils;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

class EditInstrumentForm extends FormBase {

  protected $instrumentUri;

  protected $instrument;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri; 
  }

  public function getInstrument() {
    return $this->instrument;
  }

  public function setInstrument($instrument) {
    return $this->instrument = $instrument; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {
    $uri=$instrumenturi;
    $uri_decode=base64_decode($uri);
    $this->setInstrumentUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($this->getInstrumentUri());
    $obj = json_decode($rawresponse);
        //dpm($obj);
    if ($obj->isSuccessful) {
      $this->setInstrument($obj->body);
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Instrument."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }

    $hasInformant = Constant::DEFAULT_INFORMANT;
    if ($this->getInstrument()->hasInformant != NULL && $this->getInstrument()->hasInformant != '') {
      $hasInformant = $this->getInstrument()->hasInformant;
    }

    $hasLanguage = Constant::DEFAULT_LANGUAGE;
    if ($this->getInstrument()->hasLanguage != NULL && $this->getInstrument()->hasLanguage != '') {
      $hasLanguage = $this->getInstrument()->hasLanguage;
    }

    $form['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getInstrument()->label,
    ];
    $form['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
      '#default_value' => $this->getInstrument()->hasShortName,
    ];
    $form['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => $hasInformant,
    ];
    $form['instrument_instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
      '#default_value' => $this->getInstrument()->hasInstruction,
    ];
    $form['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $hasLanguage,
    ];
    $form['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getInstrument()->hasVersion,
    ];
    $form['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getInstrument()->comment,
    ];
    $form['instrument_date_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Field (Optional)'),
      '#default_value' => $this->getInstrument()->hasDateField,
    ];
    $form['instrument_subject_id_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject ID Field (Optional)'),
      '#default_value' => $this->getInstrument()->hasSubjectIDField,
    ];
    $form['instrument_subject_relationship_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject Relationship Field (Optional)'),
      '#default_value' => $this->getInstrument()->hasSubjectRelationshipField,
    ];
    $form['instrument_page_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page Number (Optional)'),
      '#default_value' => $this->getInstrument()->hasPageNumber,
    ];
    $form['instrument_copyright_notice'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Copyright notice (Optional)'),
      '#default_value' => $this->getInstrument()->hasCopyrightNotice,
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
      if(strlen($form_state->getValue('instrument_name')) < 1) {
        $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
        $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid abbreviation'));
      }
      if(strlen($form_state->getValue('instrument_language')) < 1) {
        $form_state->setErrorByName('instrument_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('instrument_version')) < 1) {
        $form_state->setErrorByName('instrument_version', $this->t('Please enter a valid version'));
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
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
      return;
    } 

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $instrumentJson = '{"uri":"'.$this->getInstrumentUri().'",'.
        '"typeUri":"'.VSTOI::QUESTIONNAIRE.'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasInstruction":"'.$form_state->getValue('instrument_instructions').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"comment":"'.$form_state->getValue('instrument_description').'",'.
        '"hasDateField":"'.$form_state->getValue('instrument_date_field').'",'.
        '"hasSubjectIDField":"'.$form_state->getValue('instrument_subject_id_field').'",'.
        '"hasSubjectRelationshipField":"'.$form_state->getValue('instrument_subject_relationship_field').'",'.
        '"hasPageNumber":"'.$form_state->getValue('instrument_page_number').'",'.
        '"hasCopyrightNotice":"'.$form_state->getValue('instrument_copyright_notice').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->instrumentDel($this->getInstrumentUri());
      $newInstrument = $fusekiAPIservice->instrumentAdd($instrumentJson);
    
      \Drupal::messenger()->addMessage(t("Instrument has been updated successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Instrument: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }

  }

}