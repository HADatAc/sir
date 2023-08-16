<?php

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\FormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\sir\Entity\Attachment;
 use Drupal\sir\Utils;
 use Drupal\sir\Vocabulary\SIRAPI;

 class DescribeAssociatesForm extends FormBase {

    protected $elementUri;

    protected $associates;
  
    public function getElementUri() {
      return $this->elementUri;
    }
  
    public function setElementUri($uri) {
      return $this->elementUri = $uri; 
    }
  
    public function getAssociates() {
      return $this->associates;
    }
  
    public function setAssociates($obj) {
      return $this->associates = $obj; 
    }
  
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "describe_associates_form";
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
        $this->setElementUri(Utils::plainUri($uri));
        $api = \Drupal::service('sir.api_connector');
        $this->setAssociates($api->parseObjectResponse($api->attachmentList($this->getElementUri())),'attachmentList');

        $form['associates_header'] = [
          '#type' => 'item',
          '#title' => '<h3>Associated Elements</h3>',
        ];

        if ($this->getAssociates() == NULL || sizeof($this->getAssociates()) <= 0) {

          $form['associates_table'] = [
            '#type' => 'item',
            '#title' => t('<ul><li>NONE</li></ul>'),
          ];

        } else {
          
          $header = Attachment::generateHeader();
          $output = Attachment::generateOutput($this->getAssociates());    

          $form['associates_detectors_header'] = [
            '#type' => 'item',
            '#title' => '<h4>Items</h4>',
          ];
  
            // PUT FORM TOGETHER
          $form['associates_table'] = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $output,
            '#empty' => t('No response options found'),
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