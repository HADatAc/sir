<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sir\Utils;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageCodebookSlotsForm extends FormBase {

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
    return 'manage_codebook_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $experienceuri = NULL) {

    # GET CONTENT
    $uri=$experienceuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setExperienceUri($uri_decode);

    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    // RETRIEVE EXPERIENCE BY URI
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawexperience = $fusekiAPIservice->getUri($this->getExperienceUri());
    $objexperience = json_decode($rawexperience);
    $experience = NULL;
    if ($objexperience->isSuccessful) {
      $experience = $objexperience->body;
    }

    // RETRIEVE CODEBOOK SLOTS BY EXPERIENCE
    $slot_list = $fusekiAPIservice->codebookSlotList($this->getExperienceUri());
    $obj = json_decode($slot_list);
    $slots = [];
    if ($obj->isSuccessful) {
      $slots = $obj->body;
    }

    if (sizeof($slots) <= 0) {
      return new RedirectResponse(Url::fromRoute('sir.add_codebook_slots', ['experienceuri' => base64_encode($this->getExperienceUri())])->toString());
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
      '#title' => t('<h3>Slots of Experience <font color="DarkGreen">' . $experience->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Slots maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['edit_selected_slot'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected Codebook Slot'),
      '#name' => 'edit_slot',
    ];
    $form['delete_slots'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete All Codebook Slots'),
      '#name' => 'delete_slots',
    ];
    $form['slot_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No codebook slots found'),
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

  /*
  public function attachmentAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('slot_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }
  }
  */

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

    // EDIT CODEBOOK SLOT
    if ($button_name === 'edit_slot') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact codebook slot to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one codebook slot to edit. No more than one slot can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE CODEBOOK SLOT
    if ($button_name === 'delete_slots') {
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->codebookSlotDel($this->getExperienceUri());
    
      \Drupal::messenger()->addMessage(t("Codebook slots has been deleted successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));
    }  
  }
  
}