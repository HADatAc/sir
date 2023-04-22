<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\ConfigFormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\file\Entity\File;

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

        $form['svg_file'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Site logo in SVG format'),
            '#upload_validators' => [
              'file_validate_extensions' => ['svg'],
            ],
            '#upload_location' => 'public://temp_svg/',
            '#required' => TRUE,
          ];

        $form['repository_abbreviation'] = [
            '#type' => 'textfield',
            '#title' => 'Institution name abbreviation (ex: ufmg, ucla, rpi, etc.)',
            '#required' => TRUE,
            '#default_value' => $config->get("repository_abbreviation"),
        ];

        $form['repository_description'] = [
            '#type' => 'textarea',
            '#title' => ' description for the repository that appears in the SIR APIs GUI',
            '#required' => TRUE,
            '#default_value' => $config->get("repository_description"),
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
        
        //save confs
        $config->set("sir_home", $form_state->getValue('sir_home'));
        $config->set("repository_abbreviation", trim($form_state->getValue('repository_abbreviation')));
        $config->set("repository_iri", trim($form_state->getValue('repository_abbreviation')));
        $config->set("repository_description", trim($form_state->getValue('repository_description')));
        $config->set("site_name", trim($form_state->getValue('site_name')));
        $config->set("api_url", $form_state->getValue('api_url'));
        $config->save();
        
        //site name
        $configdrupal = \Drupal::service('config.factory')->getEditable('system.site');
        $configdrupal->set('name', $form_state->getValue('site_name')); 
        $configdrupal->save();

        //update Repository configuration
        //title
        $data = [];
        $newInstrument = $this->repositoryConf($form_state->getValue('api_url'),"/sirapi/api/repo/title/".$form_state->getValue('site_name'),$data);
        //description
        $data = [];
        $newInstrument = $this->repositoryConf($form_state->getValue('api_url'),"/sirapi/api/repo/description/".$form_state->getValue('repository_description'),$data);
        
        // Get the uploaded file.
        $file_id = $form_state->getValue('svg_file')[0];
        $file = File::load($file_id);
      
        // Set the file to be permanent and save.
        $file->setPermanent();
        $file->save();
      
        // Move the file to the desired location.
        $destination = DRUPAL_ROOT . '/themes/contrib/bootstrap_barrio/subtheme/logo.svg';
        if (file_exists($destination)) {
          // Replace the existing file if necessary.
          unlink($destination);
        }
      
        // Use the file_system service to copy the file.
        $file_system = \Drupal::service('file_system');
        $file_system->copy($file->getFileUri(), $destination, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
      
        // Save the filename in configuration.
        $this->config('sir.settings')
          ->set('svg_file', $file_id)
          ->save();
      


        $messenger = \Drupal::service('messenger');
        $messenger->addMessage($this->t('Your new SIR configuration has been saved'));
    }

    public function repositoryConf($api_url,$endpoint,$data){
        /** @var \FusekiAPI$fusekiAPIservice */
        $fusekiAPIservice = \Drupal::service('sir.api_connector');
    
        $newInstrument = $fusekiAPIservice->repositoryConf($api_url,$endpoint,$data);
        if(!empty($newInstrument)){
          return $newInstrument;
        }
        return [];
      }

 }