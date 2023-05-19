<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageAttachmentsForm extends FormBase {

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
    return 'manage_attachments_form';
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

    // RETRIEVE ATTACHMENTS BY INSTRUMENT
    $endpoint_detector = "/sirapi/api/attachment/byinstrument/".rawurlencode($this->getInstrumentUri());
    $attachment_list = $fusekiAPIservice->attachmentList($api_url,$endpoint_detector);
    $obj = json_decode($attachment_list);
    $attachments = [];
    if ($obj->isSuccessful) {
      $attachments = $obj->body;
    }

    if (sizeof($attachments) <= 0) {
      return new RedirectResponse(Url::fromRoute('sir.add_attachments', ['instrumenturi' => base64_encode($this->getInstrumentUri())])->toString());
    }

    #dpm($detectors);

    # BUILD HEADER

    $header = [
      'attachment_priority' => t('Priority'),
      'attachment_content' => t("Item's Content"),
      'attachment_detector' => t("Item's URI"),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($attachments as $attachment) {
      $detector = NULL;
      $content = "";
      if ($attachment->hasDetector != null) {
        $endpoint_instrument = "/sirapi/api/uri/".rawurlencode($attachment->hasDetector);
        $rawdetector = $fusekiAPIservice->getUri($api_url,$endpoint_instrument);
        $objdetector = json_decode($rawdetector);
        if ($objdetector->isSuccessful) {
          $detector = $objdetector->body;
          if (isset($detector->hasContent)) {
            $content = $detector->hasContent;
          } 
        }
      }
      $output[$attachment->uri] = [
        'attachment_priority' => $attachment->hasPriority,     
        'attachment_content' => $content,     
        'attachment_detector' => $attachment->hasDetector,     
      ];
    }

    # PUT FORM TOGETHER

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>Attachments of Instrument <font color="DarkGreen">' . $instrument->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Attachments maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['edit_selected_attachment'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected Item'),
      '#name' => 'edit_attachment',
    ];
    $form['delete_attachments'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete All Attachments'),
      '#name' => 'delete_attachments',
    ];
    $form['attachment_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
      '#ajax' => [
        'callback' => '::attachmentAjaxCallback', 
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

  public function attachmentAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('attachment_table');
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
    $selected_rows = $form_state->getValue('attachment_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    $config = $this->config(static::CONFIGNAME);     
    $api_url = $config->get("api_url");

    // EDIT ATTACHMENT
    if ($button_name === 'edit_attachment') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact attachment to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one attachment to edit. No more than one attachment can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_attachment');
        $url->setRouteParameter('attachmenturi', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE ATTACHMENTS
    if ($button_name === 'delete_attachments') {
      $data = [];
      $datap = $this->getInstrumentUri();
      $dataE = rawurlencode($datap);
      $deleteAttachments = $this->deleteAttachments($api_url,"/sirapi/api/attachment/delete/".$dataE,$data);
    
      \Drupal::messenger()->addMessage(t("Attachments has been deleted successfully."));
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
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

  public function deleteAttachments($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $fusekiAPIservice->attachmentDel($api_url,$endpoint,$data);
    return [];
  }


  
}