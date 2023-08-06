<?php

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\FormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\sir\Utils;

 class DescribeHeaderForm extends FormBase {

    protected $element;
  
    public function getElement() {
      return $this->element;
    }
  
    public function setElement($obj) {
      return $this->element = $obj; 
    }
  
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "describe_header_form";
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state){

        // RETRIEVE PARAMETERS FROM HTML REQUEST
        $request = \Drupal::request();
        $pathInfo = $request->getPathInfo();
        $pathElements = (explode('/',$pathInfo));
        if (sizeof($pathElements) >= 4) {
          $elementuri = $pathElements[3];
        }
        // RETRIEVE REQUESTED ELEMENT
        $uri=base64_decode(rawurldecode($elementuri));
        $full_uri = Utils::plainUri($uri);
        $api = \Drupal::service('sir.api_connector');
        $this->setElement($api->parseObjectResponse($api->getUri($full_uri)));

        if ($this->getElement() == NULL || $this->getElement() == "") {

          $form['message'] = [
            '#type' => 'item',
            '#title' => t("<b>FAILED TO RETRIEVE ELEMENT FROM PROVIDED URI</b>"),
          ];

          $form['element_uri'] = [
            '#type' => 'textfield',
            '#title' => '<b>URI</b>',
            '#default_value' => $full_uri,
            '#disabled' => TRUE,
          ];

          $form['element_type'] = [
            '#type' => 'textfield',
            '#title' => '<b>Type</b>',
            '#default_value' => 'NONE',
            '#disabled' => TRUE,
          ];
        
        } else {

          $form['element_uri'] = [
            '#type' => 'textfield',
            '#title' => '<b>URI</b>',
            '#default_value' => $this->getElement()->uri,
            '#disabled' => TRUE,
          ];

          $form['element_type'] = [
            '#type' => 'textfield',
            '#title' => '<b>Type</b>',
            '#default_value' => $this->getElement()->typeUri,
            '#disabled' => TRUE,
          ];
        
        }
    
        return $form;        

    }

    
    public function validateForm(array &$form, FormStateInterface $form_state) {
    }
     
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
    }

 }