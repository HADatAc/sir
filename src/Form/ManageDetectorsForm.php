<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\core\Url;

class ManageDetectorsForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  protected $instrumentUri;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri; 
  }

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
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

    # GET CONTENT

    $uri=$instrumenturi ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setInstrumentUri($uri_decode);

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    // RETRIEVE INSTRUMENT BY URI
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $endpoint_instrument = "/sirapi/api/uri/".rawurlencode($this->getInstrumentUri());
    $rawinstrument = $fusekiAPIservice->getUri($api_url,$endpoint_instrument);
    $objinstrument = json_decode($rawinstrument);
    $instrument = NULL;
    if ($objinstrument->isSuccessful) {
      $instrument = $objinstrument->body;
    }

    // RETRIEVE DETECTORS BY INSTRUMENT
    $endpoint_detector = "/sirapi/api/detector/byinstrument/".rawurlencode($this->getInstrumentUri());
    $detector_list = $fusekiAPIservice->detectorList($api_url,$endpoint_detector);
    $obj = json_decode($detector_list);
    $detectors = [];
    if ($obj->isSuccessful) {
      $detectors = $obj->body;
    }
    #dpm($detectors);

    # BUILD HEADER

    $header = [
      'detector_priority' => t('Priority'),
      'detector_content' => t('Content'),
      'detector_language' => t('Language'),
      'detector_version' => t('Version'),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($detectors as $detector) {
      $output[$detector->uri] = [
        'detector_priority' => $detector->hasPriority,     
        'detector_content' => $detector->hasContent,     
        'detector_language' => $detector->hasLanguage,
        'detector_version' => $detector->hasVersion,
      ];
    }

    # PUT FORM TOGETHER

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>Items of Instrument <font color="DarkGreen">' . $instrument->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Items maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['add_detector'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Item'),
      '#name' => 'add_detector',
    ];
    $form['reuse_detector'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reuse Existing Item'),
      '#name' => 'reuse_detector',
    ];
    $form['translate_detector'] = [
      '#type' => 'submit',
      '#value' => $this->t('Translate Existing Item'),
      '#name' => 'translate_detector',
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
    $form['bottom_space'] = [
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
      $url->setRouteParameter('instrumenturi', base64_encode($this->getInstrumentUri()));
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
          $this->deleteResponseOption($api_url,"/sirapi/api/detector/delete/".$uriEncoded,[]);  
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

  public function deleteResponseOption($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $fusekiAPIservice->detectorDel($api_url,$endpoint,$data);
    return true;
  }
  
}