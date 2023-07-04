<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\core\Url;
use Drupal\sir\Entity\Tables;

class ManageResponseOptionsForm extends FormBase {

  protected $experienceUri;

  public function getExperienceUri() {
    return $this->experienceUri;
  }

  public function setExperienceUri($uri) {
    return $this->experienceUri = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_responseoptions_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $experienceuri = NULL) {

    # GET CONTENT
    $uri=$experienceuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setExperienceUri($uri_decode);

    $useremail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    $tables = new Tables;
    $languages = $tables->getLanguages();

    // RETRIEVE EXPERIENCE BY URI
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawexperience = $fusekiAPIservice->getUri($this->getExperienceUri());
    $objexperience = json_decode($rawexperience);
    $experience = NULL;
    if ($objexperience->isSuccessful) {
      $experience = $objexperience->body;
    }

    // RETRIEVE RESPONSE OPTIONS BY EXPERIENCE
    $responseoption_list = $fusekiAPIservice->responseoptionList($this->getExperienceUri());
    $obj = json_decode($responseoption_list);
    $responseoptions = [];
    if ($obj->isSuccessful) {
      $responseoptions = $obj->body;
    }

    # dpm($obj);

    # BUILD HEADER

    $header = [
      'responseoption_priority' => t('Priority'),
      'responseoption_content' => t('Content'),
      'responseoption_language' => t('Language'),
      'responseoption_version' => t('Version'),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($responseoptions as $responseoption) {
      $output[$responseoption->uri] = [
        'responseoption_priority' => $responseoption->hasPriority,     
        'responseoption_content' => $responseoption->hasContent,     
        'responseoption_language' => $languages[$responseoption->hasLanguage],
        'responseoption_version' => $responseoption->hasVersion,
      ];
    }

    # PUT FORM TOGETHER

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>Response Options of Experience <font color="DarkGreen">' . $experience->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Response Options maintained by <font color="DarkGreen">' . $name . ' (' . $useremail . ')</font></h4>'),
    ];
    $form['add_responseoption'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Response Option'),
      '#name' => 'add_responseoption',
    ];
    $form['reuse_responseoption'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reuse Existing Response Option'),
      '#name' => 'reuse_responseoption',
    ];
    $form['edit_selected_responseoption'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected Response Option'),
      '#name' => 'edit_responseoption',
    ];
    $form['delete_selected_responseoptions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected Response Options'),
      '#name' => 'delete_responseoption',
    ];
    $form['responseoption_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
      //'#ajax' => [
      //  'callback' => '::responseoptionAjaxCallback', 
      //  'disable-refocus' => FALSE, 
      //  'event' => 'change',
      //  'wrapper' => 'edit-output', 
      //  'progress' => [
      //    'type' => 'throbber',
      //    'message' => $this->t('Verifying entry...'),
      //  ],
      //]    
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
  }

  public function responseoptionAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('responseoption_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }
  }

  /**
   * {@inheritdoc}
   */   
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];
  
    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('responseoption_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // ADD RESPONSE OPTION
    if ($button_name === 'add_responseoption') {
      $url = Url::fromRoute('sir.add_response_option');
      $url->setRouteParameter('experienceuri', base64_encode($this->getExperienceUri()));
      $form_state->setRedirectUrl($url);
    }  

    // EDIT RESPONSE OPTION
    if ($button_name === 'edit_responseoption') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact response option to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("No more than one response option can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_response_option');
        $url->setRouteParameter('responseoptionuri', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE RESPONSE OPTION
    if ($button_name === 'delete_responseoption') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addMessage(t("At least one response option needs to be selected to be deleted."));      
      } else {
        foreach($rows as $uri) {
          $fusekiAPIservice = \Drupal::service('sir.api_connector');
          $fusekiAPIservice->responseoptionDel($uri);
        }
        \Drupal::messenger()->addMessage(t("Selected response option(s) has/have been deleted successfully."));      
      }
    }  

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_experiences');
      $form_state->setRedirectUrl($url);
    }  
  }

}