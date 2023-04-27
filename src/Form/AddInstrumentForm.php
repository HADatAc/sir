<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

class AddInstrumentForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_instrument_form';
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
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
    ];
    $form['instrument_instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
    ];
    $form['instrument_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
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
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      $config = $this->config(static::CONFIGNAME);     
      $api_url = $config->get("api_url");
      $repository_abbreviation = $config->get("repository_abbreviation");
  
      $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();

      $iid = time().rand(10000,99999).$uid;
      
      $data = [
        'uri' => 'http://hadatac.org/kb/test/Instrument'.$iid,
        'typeUri' => 'http://hadatac.org/ont/vstoi#Questionnaire',
        'hascoTypeUri' => 'http://hadatac.org/ont/vstoi#Instrument',
        'label' => $form_state->getValue('instrument_name'),
        'hasShortName' => $form_state->getValue('instrument_abbreviation'),
        'hasInstruction' => $form_state->getValue('instrument_instructions'),
        'hasLanguage' => $form_state->getValue('instrument_language'),
        'hasVersion' => $form_state->getValue('instrument_version'),
        'comment' => $form_state->getValue('instrument_description'),
        'hasSIRMaintainerEmail' => $uemail, 
      ];
      
      $datap = '{"uri":"http://hadatac.org/kb/test/Instrument'.$iid.'",'.
        '"typeUri":"http://hadatac.org/ont/vstoi#Questionnaire",'.
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#Instrument",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInstruction":"'.$form_state->getValue('instrument_instructions').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"comment":"'.$form_state->getValue('instrument_description').'",'.
        '"hasSIRMaintainerEmail":"'.$uemail.'"}';

      $dataJ = json_encode($data);
    
      $dataE = rawurlencode($datap);

      $newinstrument = $this->addinstrument($api_url,"/sirapi/api/instrument/create/".$dataE,$data);
    
      \Drupal::messenger()->addMessage(t("Instruction has been added successfully."));
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding instrument: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
    }

  }

  public function addinstrument($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newinstrument = $fusekiAPIservice->instrumentAdd($api_url,$endpoint,$data);
    if(!empty($newinstrumentt)){
      return $newinstrument;
    }
    return [];
  }

}