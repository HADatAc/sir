<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\ConfigFormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\Core\Url;

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

        $home = "";
        if ($config->get("sir_home")!= NULL) {
            $home = $config->get("sir_home");
        }
        $form['sir_home'] = [
            '#type' => 'checkbox',
            '#title' => 'Do you want SIR to be the home (first page) of the Drupal?',
            '#default_value' => $home,
        ];

        $shortName = "";
        if ($config->get("site_label")!= NULL) {
            $shortName = $config->get("site_label");
        }
        $form['site_label'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Short Name (ex. "ChildFIRST")',
            '#default_value' => $shortName,
        ];

        $fullName = "";
        if ($config->get("site_name")!= NULL) {
            $fullName = $config->get("site_name");
        }
        $form['site_name'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Full Name (ex. "ChildFIRST: Focus on Innovation")',
            '#default_value' => $fullName,
            '#description' => 'This value is the website name.',
        ];

        $domainUrl = "";
        if ($config->get("repository_domain_url")!= NULL) {
            $domainUrl = $config->get("repository_domain_url");
        }
        $form['repository_domain_url'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Domain URL (ex: http://childfirst.ucla.edu, http://tw.rpi.edu, etc.)',
            '#required' => TRUE,
            '#default_value' => $domainUrl,
            '#description' => 'This value is used to compose the URL of SIR elements created within this repository',
        ];

        $domainNamespace = "";
        if ($config->get("repository_domain_namespace")!= NULL) {
            $domainNamespace = $config->get("repository_domain_namespace");
        }
        $form['repository_domain_namespace'] = [
            '#type' => 'textfield',
            '#title' => 'Namespace for Domain URL (ex: ufmg, ucla, rpi, etc.)',
            '#required' => TRUE,
            '#default_value' => $domainNamespace,
        ];

        $description = "";
        if ($config->get("repository_description")!= NULL) {
            $description = $config->get("repository_description");
        }
        $form['repository_description'] = [
            '#type' => 'textarea',
            '#title' => ' description for the repository that appears in the SIR APIs GUI',
            '#required' => TRUE,
            '#default_value' => $description,
        ];

        $form['api_url'] = [
            '#type' => 'textfield',
            '#title' => 'SIR API Base URL',
            '#default_value' => $config->get("api_url"),
        ];

        return Parent::buildForm($form, $form_state);


     }

    public function validateForm(array &$form, FormStateInterface $form_state) {
        if(strlen($form_state->getValue('site_label')) < 1) {
            $form_state->setErrorByName('site_label', $this->t("Please inform repository's short name."));
        }
        if(strlen($form_state->getValue('site_name')) < 1) {
            $form_state->setErrorByName('site_name', $this->t("Please inform repository's full name."));
        }
   }
     
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config(static::CONFIGNAME);
        
        //save confs
        $config->set("sir_home", $form_state->getValue('sir_home'));
        $config->set("site_label", trim($form_state->getValue('site_label')));
        $config->set("site_name", trim($form_state->getValue('site_name')));
        $config->set("repository_domain_url", trim($form_state->getValue('repository_domain_url')));
        $config->set("repository_domain_namespace", trim($form_state->getValue('repository_domain_namespace')));
        $config->set("repository_description", trim($form_state->getValue('repository_description')));
        $config->set("api_url", $form_state->getValue('api_url'));
        $config->save();
        
        //site name
        $configdrupal = \Drupal::service('config.factory')->getEditable('system.site');
        $configdrupal->set('name', $form_state->getValue('site_name')); 
        $configdrupal->save();

        //update Repository configuration
        $fusekiAPIservice = \Drupal::service('sir.api_connector');

        //label 
        $fusekiAPIservice->repoUpdateLabel(
            $form_state->getValue('api_url'),
            $form_state->getValue('site_label'));

        //title
        $fusekiAPIservice->repoUpdateTitle(
            $form_state->getValue('api_url'),
            $form_state->getValue('site_name'));

        //description
        $fusekiAPIservice->repoUpdateDescription(
            $form_state->getValue('api_url'),
            $form_state->getValue('repository_description'));
        
        //namespace
        $fusekiAPIservice->repoUpdateNamespace(
            $form_state->getValue('api_url'),
            $form_state->getValue('repository_domain_namespace'),
            $form_state->getValue('repository_domain_url'));
      
        // Save the filename in configuration.
        //$this->config('sir.settings')
        //  ->set('svg_file', $file_id)
        //  ->save();
      
        $messenger = \Drupal::service('messenger');
        $messenger->addMessage($this->t('Your new SIR configuration has been saved'));

        $url = Url::fromRoute('sir.repo_info');
        $form_state->setRedirectUrl($url);
        
    }

 }