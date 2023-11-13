<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sir\Utils;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageResponseOptionSlotsForm extends FormBase {

  protected $codebookUri;

  public function getCodebookUri() {
    return $this->codebookUri;
  }

  public function setCodebookUri($uri) {
    return $this->codebookUri = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_responseoption_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebookuri = NULL) {

    # GET CONTENT
    $uri=$codebookuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setCodebookUri($uri_decode);

    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    // RETRIEVE CODEBOOK BY URI
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawcodebook = $fusekiAPIservice->getUri($this->getCodebookUri());
    $objcodebook = json_decode($rawcodebook);
    $codebook = NULL;
    if ($objcodebook->isSuccessful) {
      $codebook = $objcodebook->body;
    }

    // RETRIEVE RESPONSEOPTION SLOTS BY CODEBOOK
    $slot_list = $fusekiAPIservice->responseOptionSlotList($this->getCodebookUri());
    $obj = json_decode($slot_list);
    $slots = [];
    if ($obj->isSuccessful) {
      $slots = $obj->body;
    }

    if (sizeof($slots) <= 0) {
      return new RedirectResponse(Url::fromRoute('sir.add_responseoptionslots', ['codebookuri' => base64_encode($this->getCodebookUri())])->toString());
    }

    # BUILD HEADER

    $header = [
      'slot_priority' => t('Priority'),
      'slot_content' => t("Response Option's Content"),
      'slot_response_option' => t("Response Option's URI"),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($slots as $slot) {
      $content = "";
      if ($slot->hasResponseOption != null) {
        $rawresponseoption = $fusekiAPIservice->getUri($slot->hasResponseOption);
        $objresponseoption = json_decode($rawresponseoption);
        if ($objresponseoption->isSuccessful) {
          $responseoption = $objresponseoption->body;
          if (isset($responseoption->hasContent)) {
            $content = $responseoption->hasContent;
          } 
        }
      }
      $responseOptionUriStr = "";
      if ($slot->hasResponseOption != NULL && $slot->hasResponseOption != '') {
        $responseOptionUriStr = Utils::namespaceUri($slot->hasResponseOption);
      }
      $output[$slot->uri] = [
        'slot_priority' => $slot->hasPriority,     
        'slot_content' => $content,     
        'slot_response_option' => $responseOptionUriStr,     
      ];
    }

    # PUT FORM TOGETHER

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>Response Option Slots of Codebook <font color="DarkGreen">' . $codebook->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Response Option Slots maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['edit_selected_slot'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected Response Option Slot'),
      '#name' => 'edit_slot',
    ];
    $form['delete_slots'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete All Response Option Slots'),
      '#name' => 'delete_slots',
    ];
    $form['slot_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No response option slots found'),
      //'#ajax' => [
      //  'callback' => '::responseOptionSlotAjaxCallback', 
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

  public function responseoOptionSlotAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('slot_table');
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
    $selected_rows = $form_state->getValue('slot_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // EDIT RESPONSEOPTION SLOT
    if ($button_name === 'edit_slot') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact response option slot to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one response option slot to edit. No more than one slot can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_responseoption_slot');
        $url->setRouteParameter('responseoptionsloturi', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE RESPONSE OPTION SLOT
    if ($button_name === 'delete_slots') {
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->responseOptionSlotDel($this->getCodebookUri());
    
      \Drupal::messenger()->addMessage(t("Response Option Slot(s) has/have been deleted successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('codebook'));
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('codebook'));
    }  
  }
  
}