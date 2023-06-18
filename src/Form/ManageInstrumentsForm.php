<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\core\Url;
use Drupal\sir\Entity\Tables;

class ManageInstrumentsForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_instruments_form';
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
  public function buildForm(array $form, FormStateInterface $form_state) {

    # GET CONTENT

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;
    $endpoint = "/sirapi/api/instrument/maintaineremail/".rawurlencode($uemail);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $instrument_list = $fusekiAPIservice->instrumentsList($api_url,$endpoint);
    $obj = json_decode($instrument_list);
    $instruments = [];
    if ($obj->isSuccessful) {
      $instruments = $obj->body;
    }
    #dpm($instruments);

    # BUILD HEADER

    $header = [
      'instrument_name' => t('Name'),
      'instrument_abbreviation' => t('Abbreviation'),
      'instrument_language' => t('Language'),
      'instrument_version' => t('Version'),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($instruments as $instrument) {
      $output[$instrument->uri] = [
        'instrument_name' => $instrument->label,     
        'instrument_abbreviation' => $instrument->hasShortName,     
        'instrument_language' => $languages[$instrument->hasLanguage],
        'instrument_version' => $instrument->hasVersion,
      ];
    }

    # PUT FORM TOGETHER

    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h3>instruments maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h3>'),
    ];
    $form['add_instrument'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Instrument'),
      '#name' => 'add_instrument',
    ];
    $form['edit_selected_instrument'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected Instrument'),
      '#name' => 'edit_instrument',
    ];
    $form['manage_attachments'] = [
      '#type' => 'submit',
      '#value' => $this->t('Manage Attachments of Selected Instrument'),
      '#name' => 'manage_attachments',
    ];
    $form['delete_selected_instruments'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected Instruments'),
      '#name' => 'delete_instrument',
    ];
    $form['instrument_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No instruments found'),
      '#ajax' => [
        'callback' => '::instrumentAjaxCallback', 
        'disable-refocus' => FALSE, 
        'event' => 'change',
        'wrapper' => 'edit-output', 
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Verifying entry...'),
        ],
      ]    
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

  public function instrumentAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('instrument_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }
  }

  /** 
   * public function validateForm(array &$form, FormStateInterface $form_state) {
   * }
   */

  /**
   * {@inheritdoc}
   */   
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];
  
    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('instrument_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    #dpm($rows);

    $config = $this->config(static::CONFIGNAME);     
    $api_url = $config->get("api_url");

    // ADD instrument
    if ($button_name === 'add_instrument') {
      $url = Url::fromRoute('sir.add_instrument');
      $form_state->setRedirectUrl($url);
    }  

    // EDIT instrument
    if ($button_name === 'edit_instrument') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact instrument to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("No more than one instrument can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_instrument', ['instrumenturi' => base64_encode($first)]);
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE instrument
    if ($button_name === 'delete_instrument') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addMessage(t("At least one instrument needs to be selected to be deleted."));      
      } else {
        foreach($rows as $uri) {
          $uriEncoded = rawurlencode($uri);
          $this->deleteinstrument($api_url,"/sirapi/api/instrument/delete/".$uriEncoded,[]);  
        }
        \Drupal::messenger()->addMessage(t("Selected instrument(s) has/have been deleted successfully."));      
      }
    }  

    // MANAGE ATTACHMENTS
    if ($button_name === 'manage_attachments') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact instrument which attachments are going to be managed."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one instrument. Items of no more than one instrument can be managed at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.manage_attachments', ['instrumenturi' => base64_encode($first)]);
        $form_state->setRedirectUrl($url);
      } 
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.index');
      $form_state->setRedirectUrl($url);
    }  
  }

  public function deleteinstrument($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newinstrument = $fusekiAPIservice->instrumentDel($api_url,$endpoint,$data);
    return true;
  }
 
}