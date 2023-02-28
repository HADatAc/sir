<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use BorderCloud\SPARQL\SparqlClient;

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
      $sir_home = $config->get("sir_home");
  
      $uid = \Drupal::currentUser()->id();
      $iid = time().rand(10000,99999).$uid;
  
      $sc = new SparqlClient();
      $sc->setEndpointWrite($config->get("api_url").'/sir/update');
      $q = "
      INSERT DATA { 
        <https://hadatac.org/sir/".$sir_home."/Questionnarie".$iid."> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual>, <http://hadatac.org/ont/hasco/Questionnaire>; <http://hadatac.org/ont/hasco/hasShortName> '".$form_state->getValue('instrument_abbreviation')."' ; <http://www.w3.org/2000/01/rdf-schema#comment> '".$form_state->getValue('instrument_description')."' ;  <http://www.w3.org/2000/01/rdf-schema#label> '".$form_state->getValue('instrument_name')."' .  
        }";
      $res = $sc->query($q,'raw');
      $err = $sc->getErrors();
  
      #echo $q;
      #exit();
  
     /* if ($err) {
          print_r($err);
          throw new Exception(print_r($err,true));
      }
      var_dump($res);
      */
      \Drupal::messenger()->addMessage(t("Data has been added successfully."));
    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the data: ".$e->getMessage()));
    }
   
  

  }
}