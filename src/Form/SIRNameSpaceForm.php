<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

 namespace Drupal\sir\Form;

 use Drupal\Core\Form\ConfigFormBase;
 use Drupal\Core\Form\FormStateInterface;
 use Drupal\Core\Url;
 use Drupal\sir\Entity\Ontology;
 use Drupal\sir\Entity\Tables;

 class SIRNameSpaceForm extends ConfigFormBase {

     /**
     * Settings Variable.
     */
    Const CONFIGNAME = "sir.settings";

     /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "sir_form_namespace";
    }

    /**
     * {@inheritdoc}
     */

    protected function getEditableConfigNames() {
        return [
            static::CONFIGNAME,
        ];
    }

    protected $list;

    public function getList() {
      return $this->list;
    }
  
    public function setList($list) {
      return $this->list = $list; 
    }
  
    /**
     * {@inheritdoc}
     */

     public function buildForm(array $form, FormStateInterface $form_state){
        $config = $this->config(static::CONFIGNAME);

        $APIservice = \Drupal::service('sir.api_connector');
        $namespace_list = $APIservice->namespaceList();
        if ($namespace_list == NULL) {
            $empty_list = array();
            $this->setList($empty_list);
        } else {
            $obj = json_decode($namespace_list);
            if ($obj->isSuccessful) {
                $this->setList($obj->body);
            }
        }
        $header = Ontology::generateHeader();
        $output = Ontology::generateOutput($this->getList());   

        $form['filler_1'] = [
            '#type' => 'item',
            '#title' => $this->t('<br>'),
        ];

        $form['reload_triples_submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Reload Triples from All NameSpaces with URL'),
            '#name' => 'reload',
        ];
      
        $form['delete_triples_submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Delete Triples from All NameSpaces with URL'),
            '#name' => 'delete',
          ];
      
        $form['filler_2'] = [
            '#type' => 'item',
            '#title' => $this->t('<br>'),
        ];
      
        $form['element_table'] = [
            '#type' => 'tableselect',
            '#header' => $header,
            '#options' => $output,
            '#js_select' => FALSE,
            '#empty' => t('No NameSpace found'),
        ];

        $form['filler_3'] = [
            '#type' => 'item',
            '#title' => $this->t('<br>'),
        ];      

        $form['back_submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Back to SIR Settings'),
            '#name' => 'back',
        ];

        $form['filler_4'] = [
            '#type' => 'item',
            '#title' => $this->t('<br>'),
        ];      

        $form['actions']['submit']['#access'] = 'FALSE'; 
        //$form['actions']['edit-submit'] = [
        //    '#type' => 'hidden',
        //    '#title' => 'test',
        //];
        //$form['edit-submit']['#access'] = 'FALSE';
        //$form['edit-submit'] = [
            //'#class' => 'button button--primary js-form-submit form-submit',
          //  '#value' => 'hidden',
        //];

        return Parent::buildForm($form, $form_state);
    }

    public function validateForm(array &$form, FormStateInterface $form_state) {
    }
     
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $triggering_element = $form_state->getTriggeringElement();
        $button_name = $triggering_element['#name'];
    
        if ($button_name === 'back') {
          $form_state->setRedirectUrl(Url::fromRoute('sir.admin_settings_custom'));
          return;
        } 
              
        $APIservice = \Drupal::service('sir.api_connector');

        if ($button_name === 'reload') {
          $message = $APIservice->parseObjectResponse($APIservice->repoReloadNamespaceTriples());
          \Drupal::messenger()->addMessage(t($message));
          $form_state->setRedirectUrl(Url::fromRoute('sir.admin_namespace_settings_custom'));
          return;
        } 
                
        if ($button_name === 'delete') {
          $message = $APIservice->parseObjectResponse($APIservice->repoDeleteNamespaceTriples());
          \Drupal::messenger()->addMessage(t($message));
          $form_state->setRedirectUrl(Url::fromRoute('sir.admin_namespace_settings_custom'));
          return;
        } 
                
    }

 }