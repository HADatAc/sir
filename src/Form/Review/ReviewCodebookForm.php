<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;

class ReviewCodebookForm extends FormBase {

  protected $codebookUri;

  protected $codebook;

  public function getCodebookUri() {
    return $this->codebookUri;
  }

  public function setCodebookUri($uri) {
    return $this->codebookUri = $uri;
  }

  public function getCodebook() {
    return $this->codebook;
  }

  public function setCodebook($cb) {
    return $this->codebook = $cb;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_codebook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebookuri = NULL) {
    $uri=$codebookuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setCodebookUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getCodebookUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setCodebook($obj->body);
      #dpm($this->getCodebook());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Codebook."));
      self::backUrl();
      return;
    }

    $form['information'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-publication',
    ];

    $form['codebook_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Codebook Form'),
      '#group' => 'information',
    ];

    $form['codebook_information']['codebook_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getCodebook()->label,
      '#disabled' => TRUE,
    ];
    $form['codebook_information']['codebook_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getCodebook()->hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['codebook_information']['codebook_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getCodebook()->hasVersion,
      '#disabled' => TRUE,
    ];
    $form['codebook_information']['codebook_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getCodebook()->comment,
      '#disabled' => TRUE,
    ];
    $form['codebook_information']['codebook_hasSIRManagerEmail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getCodebook()->hasSIRManagerEmail,
      '#disabled' => TRUE,
    ];

    // RESPONSE OPTIONS TAB

    $form['responseoption_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Response Options'),
      '#group' => 'information',
    ];

    /*****************************/
    /* RETRIEVE RESPONSE OPTIONS */
    /*****************************/
    $slot_list = $api->codebookSlotList($this->getCodebook()->uri);
    $obj = json_decode($slot_list);
    $slots = [];
    if ($obj->isSuccessful) {
      $slots = $obj->body;
    }

    # BUILD HEADER

    $header = [
      'slot_priority' => t('Priority'),
      'slot_content' => t("Response Option's Content"),
      'slot_response_option' => t("Response Option's URI"),
      'slot_response_status' => t("Status"),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($slots as $slot) {
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
        $responseOptionUriStr = Utils::namespaceUri($slot->hasResponseOption);
      }
      $output[$slot->uri] = [
        'slot_priority' => $slot->hasPriority,
        'slot_content' => $content,
        'slot_response_option' => $responseOptionUriStr,
        'slot_response_status' => parse_url($slot->responseOption->hasStatus, PHP_URL_FRAGMENT),
        '#disabled' => TRUE
      ];
    }

    # PUT FORM TOGETHER

    $form['responseoption_information']['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Response Option Slots</h4>'),
      '#attributes' => [
        'class' => ['mt-5 mb-1'],
      ],
    ];

    $form['responseoption_information']['slot_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No response option slots found'),
    ];

    // REVIEW NOTES TAB
    $form['codebook_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getCodebook()->hasReviewNote,
    ];

    $form['codebook_haseditoremail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reviewer Email'),
      '#default_value' => \Drupal::currentUser()->getEmail(),
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    $form['review_approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Approve'),
      '#name' => 'review_approve',
      '#attributes' => [
        'class' => ['btn', 'btn-success', 'aprove-button'],
      ],
    ];
    $form['review_reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#name' => 'review_reject',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];


    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('codebook_name')) < 1) {
        $form_state->setErrorByName('codebook_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('codebook_language')) < 1) {
        $form_state->setErrorByName('codebook_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('codebook_version')) < 1) {
        $form_state->setErrorByName('codebook_version', $this->t('Please enter a valid version'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    if ($button_name === 'review_reject' && strlen($form_state->getValue('responseoption_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $codebookJson = '{"uri":"'. $this->getCodebook()->uri .'",'.
        '"typeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hasStatus":"'.$this->getCodebook()->hasStatus.'",'.
        '"label":"'.$form_state->getValue('codebook_name').'",'.
        '"hasLanguage":"'.$form_state->getValue('codebook_language').'",'.
        '"hasVersion":"'.$form_state->getValue('codebook_version').'",'.
        '"comment":"'.$form_state->getValue('codebook_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->codebookDel($this->getCodebook()->uri);
      $api->codebookAdd($codebookJson);

      \Drupal::messenger()->addMessage(t("Codebook has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating Codebook: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, \Drupal::request()->getRequestUri());
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }



}
