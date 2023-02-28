<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\ConfigFormBase;
 use Drupal\Core\Form\FormStateInterface;

 class SIRSettingsForm extends ConfigFormBase {

     /**
     * Settings Variable.
     */
    Const CONFIGNAME = "sir.settings";

     /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "sir_form_settings";
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

     public function buildForm(array $form, FormStateInterface $form_state){
        $config = $this->config(static::CONFIGNAME);
       
       # print " 2 ".$config->get("api_get_instrument"); exit();


        $form['sir_home'] = [
            '#type' => 'checkbox',
            '#title' => 'Do you want SIR to be the home (first page) of the Drupal?',
            '#default_value' => $config->get("sir_home"),
        ];

        $form['site_name'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Name (ex. UCLA’s RCADS Repository)',
            '#default_value' => $config->get("site_name"),
        ];

        $form['site_logo'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Logo',
            '#default_value' => $config->get("site_logo"),
        ];

        $form['repository_abbreviation'] = [
            '#type' => 'textfield',
            '#title' => 'Institution name abbreviation (ex: UFMG, UCLA, RPI, etc.)',
            '#required' => TRUE,
            '#default_value' => $config->get("repository_abbreviation"),
        ];

        $form['api_url'] = [
            '#type' => 'textfield',
            '#title' => 'SIR API Base URL',
            '#default_value' => $config->get("api_url"),
        ];

        return Parent::buildForm($form, $form_state);


     }

     public function validateForm(array &$form, FormStateInterface $form_state) {
        if(strlen($form_state->getValue('repository_abbreviation')) < 1) {
          $form_state->setErrorByName('repository_abbreviation', $this->t('Please inform the abbreviation of your institution name'));
        }
      }


     
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config(static::CONFIGNAME);
        $config->set("sir_home", $form_state->getValue('sir_home'));
        $config->set("repository_abbreviation", trim($form_state->getValue('repository_abbreviation')));
        $config->set("repository_iri", trim($form_state->getValue('repository_abbreviation')));
        $config->set("site_name", trim($form_state->getValue('site_name')));
        $config->set("api_url", $form_state->getValue('api_url'));
        $config->save();
        

        $configdrupal = \Drupal::service('config.factory')->getEditable('system.site');
        $configdrupal->set('name', $form_state->getValue('site_name')); 
        $configdrupal->save();

        $messenger = \Drupal::service('messenger');
        $messenger->addMessage($this->t('Your new configuration has been saved'));
    }

 }