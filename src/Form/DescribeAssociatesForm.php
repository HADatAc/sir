<?php

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\FormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\sir\Entity\Attachment;
 use Drupal\sir\Utils;
 use Drupal\sir\Vocabulary\SIRGUI;
 use Drupal\sir\Vocabulary\VSTOI;

 class DescribeAssociatesForm extends FormBase {

    protected $element;

    protected $associates;
  
    public function getElement() {
      return $this->element;
    }
  
    public function setElement($object) {
      return $this->element = $object; 
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
        $api = \Drupal::service('sir.api_connector');
        $finalUri = $api->getUri(Utils::plainUri($uri));
        if ($finalUri != NULL) {
          $this->setElement($api->parseObjectResponse($finalUri,'getUri'));
          if ($this->getElement() != NULL) {
            //var_dump($this->getElement());
            if ($this->getElement()->hascoTypeUri == VSTOI::INSTRUMENT) {
              $this->setAssociates($api->parseObjectResponse($api->attachmentList($this->getElement()->uri),'attachmentList'));
            }
          }
        }

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

          //$form['associates_detectors_header'] = [
          //  '#type' => 'item',
          //  '#title' => '<h4>Items</h4>',
          //];
  
            // PUT FORM TOGETHER
          $form['associates_table'] = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $output,
            '#empty' => t('No associated items'),
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