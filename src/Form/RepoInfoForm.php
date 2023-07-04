<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\FormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\Core\Url;
 use Drupal\sir\Utils;

 class RepoInfoForm extends FormBase {

     /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "repo_info";
    }

     /**
     * {@inheritdoc}
     */

     public function buildForm(array $form, FormStateInterface $form_state){

        // SET SERVICES
        $messenger = \Drupal::service('messenger');
        $fusekiAPIservice = \Drupal::service('sir.api_connector');

        // RETRIEVE CONFIGURATION FROM CURRENT IP
        $repo = $fusekiAPIservice->repoInfo();
        $obj = json_decode($repo);
        if ($obj->isSuccessful) {
            $repoObj = $obj->body;
            $label = $repoObj->label;
            $name = $repoObj->title;
            $domainUrl = $repoObj->hasDefaultNamespaceURL;
            $domainNamespace = $repoObj->hasDefaultNamespaceAbbreviation;
            $description = $repoObj->comment;
        } else {
            $label = "";
            $name = "<<FAILED TO LOAD CONFIGURATION>>";
            $domainUrl = "";
            $domainNamespace = "";
            $description = "";
        }

        $form['site_label'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Short Name',
            '#default_value' => $label,
            '#disabled' => TRUE,
        ];

        $form['site_name'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Full Name',
            '#default_value' => $name,
            '#disabled' => TRUE,
        ];

        $form['repository_domain_url'] = [
            '#type' => 'textfield',
            '#title' => 'Repository Domain URL',
            '#default_value' => $domainUrl,
            '#disabled' => TRUE,
        ];

        $form['repository_domain_namespace'] = [
            '#type' => 'textfield',
            '#title' => 'Namespace for Domain URL',
            '#default_value' => $domainNamespace,
            '#disabled' => TRUE,
        ];

        $form['repository_description'] = [
            '#type' => 'textarea',
            '#title' => ' description for the repository that appears in the SIR APIs GUI',
            '#default_value' => $description,
            '#disabled' => TRUE,
        ];

        $form['api_url'] = [
            '#type' => 'textfield',
            '#title' => 'SIR API Base URL',
            '#default_value' => Utils::configApiUrl(),
            '#disabled' => TRUE,
        ];
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Back'),
            '#name' => 'back',
        ];
        $form['space'] = [
            '#type' => 'label',
            '#value' => $this->t('<br><br>'),
        ];

        return $form;

    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
    }
     
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $url = Url::fromRoute('sir.index');
        $form_state->setRedirectUrl($url);
    }

 }