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
      '#required' => TRUE,
    ];
    $form['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
      '#required' => TRUE,
    ];
    $form['instrument_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
      '#required' => TRUE,
    ];
    $form['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#required' => TRUE,
    ];
    $form['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Questionnaire description'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save!'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if(strlen($form_state->getValue('instrument_name')) < 1) {
      $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name for the Questionnaire'));
    }
    if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
      $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid abbreviation of the Questionnaire'));
    }
    if(strlen($form_state->getValue('instrument_description')) < 1) {
      $form_state->setErrorByName('instrument_description', $this->t('Please enter a valid description of the Questionnaire'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

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
        'comment' => $form_state->getValue('instrument_description')
      ];
      

      $datap = '{"uri":"http://hadatac.org/kb/test/Instrument'.$iid.'","typeUri":"http://hadatac.org/ont/vstoi#Questionnaire","hascoTypeUri":"http://hadatac.org/ont/vstoi#Instrument","label":"'.$form_state->getValue('instrument_name').'","comment":"'.$form_state->getValue('instrument_description').'","hasShortName":"'.$form_state->getValue('instrument_abbreviation').'","hasSIROwnerEmail":"'.$uemail.'"}';

    $dataJ = json_encode($data);
    
    $dataE = rawurlencode($datap);

    $newInstrument = $this->addInstrument($api_url,"/sirapi/api/instrument/create/".$dataE,$data);
    
    
  $root_url = \Drupal::request()->getBaseUrl();
  $url = $root_url.'/sir/manage/editinstruments';
  $url_object = Url::fromUri($url);
  $form_state->setRedirectUrl($url_object);

  \Drupal::messenger()->addMessage(t("Questionnarie has been added successfully."));

      

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Questionnarie: ".$e->getMessage()));
    }

  }

  public function addInstrument($api_url,$endpoint,$data){
    /** @var \FusekiAPI$fusekiAPIservice */
    $fusekiAPIservice = \Drupal::service('sir.api_connector');

    $newInstrument = $fusekiAPIservice->instrumentAdd($api_url,$endpoint,$data);
    if(!empty($newInstrument)){
      return $newInstrument;
    }
    return [];
  }


}