<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\core\Url;
use Drupal\sir\Entity\Tables;

class ManageDetectorsForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_detectors_form';
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

    // GET CONTENT
    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    // GET LANGUAGES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    // RETRIEVE DETECTORS BY MAINTAINER
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $endpoint_detector = "/sirapi/api/detector/maintaineremail/".rawurlencode($uemail);
    $detector_list = $fusekiAPIservice->detectorList($api_url,$endpoint_detector);
    $detectors = [];
    if ($detector_list != null) {
      $obj = json_decode($detector_list);
      if ($obj->isSuccessful) {
        $detectors = $obj->body;
      }
    }
    #dpm($detectors);

    # BUILD HEADER

    $header = [
      'detector_content' => t('Content'),
      'detector_language' => t('Language'),
      'detector_version' => t('Version'),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($detectors as $detector) {
      $output[$detector->uri] = [
        'detector_content' => $detector->hasContent,     
        'detector_language' => $languages[$detector->hasLanguage],
        'detector_version' => $detector->hasVersion,
      ];
    }

    # PUT FORM TOGETHER

    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Items maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['add_detector'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New Original Item'),
      '#name' => 'add_detector',
    ];
    $form['derive_detector'] = [
      '#type' => 'submit',
      '#value' => $this->t('Derive New Item From Selected Item'),
      '#name' => 'derived_detector',
    ];
    $form['edit_selected_detector'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected Item'),
      '#name' => 'edit_detector',
    ];
    $form['delete_selected_detectors'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected Items'),
      '#name' => 'delete_detector',
    ];
    $form['bottom_space1'] = [
      '#type' => 'item',
      '#title' => t('<br>'),
    ];
    $form['detector_label1'] = [
      '#type' => 'label',
      '#title' => t('Page 1 of 2&nbsp;&nbsp;&nbsp;'),
    ];
    $form['detector_first'] = [
      '#type' => 'submit',
      '#value' => $this->t('First'),
      '#name' => 'first',
    ];
    $form['detector_prev'] = [
      '#type' => 'submit',
      '#value' => $this->t('Previous'),
      '#name' => 'prev',
    ];
    $form['detector_next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#name' => 'next',
    ];
    $form['detector_last'] = [
      '#type' => 'submit',
      '#value' => $this->t('Last'),
      '#name' => 'last',
    ];
    $form['detector_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
      '#ajax' => [
        'callback' => '::detectorAjaxCallback', 
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
    $form['bottom_space2'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
  }

  public function detectorAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('detector_table');
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
    $selected_rows = $form_state->getValue('detector_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    $config = $this->config(static::CONFIGNAME);     
    $api_url = $config->get("api_url");

    // ADD DETECTOR
    if ($button_name === 'add_detector') {
      $url = Url::fromRoute('sir.add_detector');
      $form_state->setRedirectUrl($url);
    }  

    // EDIT DETECTOR
    if ($button_name === 'edit_detector') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact item to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one item to edit. No more than one item can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_detector');
        $url->setRouteParameter('detectoruri', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE DETECTOR
    if ($button_name === 'delete_detector') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addMessage(t("At least one item needs to be selected to be deleted."));      
      } else {
        foreach($rows as $uri) {
          $uriEncoded = rawurlencode($uri);
          $this->deleteDetector($api_url,"/sirapi/api/detector/delete/".$uriEncoded,[]);  
        }
        \Drupal::messenger()->addMessage(t("Selected item(s) has/have been deleted successfully."));      
      }
    }  

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
    }  
  }

  public function deleteDetector($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $fusekiAPIservice->detectorDel($api_url,$endpoint,$data);
    return true;
  }
  
}