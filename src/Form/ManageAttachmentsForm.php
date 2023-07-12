<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sir\Utils;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageAttachmentsForm extends FormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

    # GET CONTENT
    $uri=$instrumenturi ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setInstrumentUri($uri_decode);

    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    // RETRIEVE INSTRUMENT BY URI
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawinstrument = $fusekiAPIservice->getUri($this->getInstrumentUri());
    $objinstrument = json_decode($rawinstrument);
    $instrument = NULL;
    if ($objinstrument->isSuccessful) {
      $instrument = $objinstrument->body;
    }

    // RETRIEVE ATTACHMENTS BY INSTRUMENT
    $attachment_list = $fusekiAPIservice->attachmentList($this->getInstrumentUri());
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
        $rawdetector = $fusekiAPIservice->getUri($attachment->hasDetector);
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
        'attachment_detector' => Utils::namespaceUri($attachment->hasDetector),     
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
      '#value' => $this->t('Edit Selected Attachment'),
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
      //'#ajax' => [
      //  'callback' => '::attachmentAjaxCallback', 
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
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->attachmentDel($this->getInstrumentUri());
    
      \Drupal::messenger()->addMessage(t("Attachments has been deleted successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }  
  }
  
}