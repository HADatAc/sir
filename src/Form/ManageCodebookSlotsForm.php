<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class ManageCodebookSlotsForm extends FormBase {

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
    return 'manage_codebook_slot_form';
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
    $api = \Drupal::service('rep.api_connector');
    $rawcodebook = $api->getUri($this->getCodebookUri());
    $objcodebook = json_decode($rawcodebook);
    $codebook = NULL;
    if ($objcodebook->isSuccessful) {
      $codebook = $objcodebook->body;
    }

    // RETRIEVE RESPONSEOPTION SLOTS BY CODEBOOK
    $slot_list = $api->codebookSlotList($this->getCodebookUri());
    $obj = json_decode($slot_list);
    $slots = [];
    if ($obj->isSuccessful) {
      $slots = $obj->body;
    }

    //dpm($slots);

    if (sizeof($slots) <= 0) {
      return new RedirectResponse(Url::fromRoute('sir.add_codebookslots', ['codebookuri' => base64_encode($this->getCodebookUri())])->toString());
    }

    # BUILD HEADER

    $header = [
      'slot_priority' => t('Priority'),
      'slot_content' => t("Response Option's Content"),
      'slot_response_option' => t("Response Option's URI"),
      'slot_response_status' => t("Status"),
    ];

    # POPULATE DATA
    $root_url = \Drupal::request()->getBaseUrl();
    $output = array();
    // dpm($slots);

    foreach ($slots as $slot) {

      // In case there is an empty slot do not break the loop
      if ($slot === null) {
        \Drupal::messenger()->addWarning(t("There were missing slots on the structure that could not be presented, redo ou recheck."));
        continue;
      }

      $content = "";
      if ($slot->hasResponseOption != null) {
        $rawresponseoption = $api->getUri($slot->hasResponseOption);
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
        $responseOptionUriStr = t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($slot->hasResponseOption).'">' . Utils::namespaceUri($slot->hasResponseOption) . '</a>');
      }
      $output[$slot->uri] = [
        'slot_priority' => $slot->hasPriority,
        'slot_content' => $content,
        'slot_response_option' => $responseOptionUriStr,
        'slot_response_status' => Utils::plainStatus($responseoption->hasStatus) ?? '',
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
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'edit-element-button'],
      ],
    ];
    $form['delete_slots'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete All Response Option Slots'),
      '#name' => 'delete_slots',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'delete-element-button'],
        'onclick' => 'if(!confirm("Really Delete?")){return false;}',
      ],
    ];
    $form['slot_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No response option slots found'),
      //'#ajax' => [
      //  'callback' => '::codebookSlotAjaxCallback',
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
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
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

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

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
        \Drupal::messenger()->addWarning(t("Select the exact response option slot to be edited."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("Select only one response option slot to edit. No more than one slot can be edited at once."));
      } else {
        $first = array_shift($rows);
        Utils::trackingStoreUrls($uid, $previousUrl, 'sir.edit_codebook_slot');
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($first));
        $form_state->setRedirectUrl($url);
      }
    }

    // DELETE RESPONSE OPTION SLOT
    if ($button_name === 'delete_slots') {
      $api = \Drupal::service('rep.api_connector');
      $api->codebookSlotDel($this->getCodebookUri());

      \Drupal::messenger()->addMessage(t("Response Option Slot(s) has/have been deleted successfully."));
      self::backUrl();
      return;
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.manage_codebook_slots');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
