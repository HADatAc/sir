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

 class UriForm extends FormBase {

    protected $elementUri;

    public function getElementUri() {
      return $this->elementUri;
    }
  
    public function setElementUri($uri) {
      return $this->elementUri = $uri; 
    }
  
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "uri_form";
    }

    /**
     * {@inheritdoc}
     */

     public function buildForm(array $form, FormStateInterface $form_state){

        $form['element_uri'] = [
            '#type' => 'textfield',
            '#title' => 'URI to be described',
            '#required' => TRUE,
        ];
        $form['submit_describe'] = [
            '#type' => 'submit',
            '#value' => $this->t('Describe'),
            '#name' => 'describe',
        ];
        $form['submit_back'] = [
            '#type' => 'submit',
            '#value' => $this->t('Back'),
            '#name' => 'back',
        ];
        $form['space'] = [
            '#type' => 'item',
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
        $submitted_values = $form_state->cleanValues()->getValues();
        $triggering_element = $form_state->getTriggeringElement();
        $button_name = $triggering_element['#name'];
    
        if ($button_name === 'back') {
            $url = Url::fromRoute('sir.index');
            $form_state->setRedirectUrl($url);
            return;
        } 
    
        if ($button_name === 'describe') {
            $newUri = Utils::plainUri($form_state->getValue('element_uri'));
            $url = Url::fromRoute('sir.describe_element', ['elementuri' => base64_encode($newUri)]);
            $form_state->setRedirectUrl($url);
            return;
        } 
      
        $url = Url::fromRoute('sir.index');
        $form_state->setRedirectUrl($url);
        return;
    }

 }