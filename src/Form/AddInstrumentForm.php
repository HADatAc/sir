<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddInstrumentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    $form['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
    ];
    $form['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => Constant::DEFAULT_INFORMANT,
    ];
    $form['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => Constant::DEFAULT_LANGUAGE,
    ];
    $form['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
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
      if(strlen($form_state->getValue('instrument_name')) < 1) {
        $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
        $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid abbreviation'));
      }
      if(strlen($form_state->getValue('instrument_language')) < 1) {
        $form_state->setErrorByName('instrument_language', $this->t('Please enter a valid language'));
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
      $useremail = \Drupal::currentUser()->getEmail();
      $newInstrumentUri = Utils::uriGen('instrument');
      $instrumentJson = '{"uri":"'.$newInstrumentUri.'",'.
        '"typeUri":"'.VSTOI::QUESTIONNAIRE.'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"comment":"'.$form_state->getValue('instrument_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $api->instrumentAdd($instrumentJson);    
      \Drupal::messenger()->addMessage(t("Instrument has been added successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding instrument: ".$e->getMessage()));
      self::backUrl();
      return;
 }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_instrument');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }
  


}