<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class EditResponseOptionForm extends FormBase {

  protected $responseOptionUri;

  protected $responseOption;

  public function getResponseOptionUri() {
    return $this->responseOptionUri;
  }

  public function setResponseOptionUri($uri) {
    return $this->responseOptionUri = $uri;
  }

  public function getResponseOption() {
    return $this->responseOption;
  }

  public function setResponseOption($respOption) {
    return $this->responseOption = $respOption;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_responseoption_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $responseoptionuri = NULL) {
    $uri=$responseoptionuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setResponseOptionUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getResponseOptionUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setResponseOption($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Response Option."));
      self::backUrl();
      return;
    }

    $form['responseoption_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getResponseOption()->hasContent,
    ];
    $form['responseoption_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getResponseOption()->hasLanguage,
    ];
    $form['responseoption_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getResponseOption()->hasStatus === VSTOI::CURRENT || $this->getResponseOption()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getResponseOption()->hasVersion + 1 : $this->getResponseOption()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getResponseOption()->comment,
    ];

    if ($this->getResponseOption()->hasReviewNote !== NULL && $this->getResponseOption()->hasSatus !== null) {
      $form['responseoption_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getResponseOption()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['responseoption_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getResponseOption()->hasEditorEmail,
        '#attributes' => [
          'disabled' => 'disabled',
        ],
      ];
    }

    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('responseoption_content')) < 1) {
        $form_state->setErrorByName('responseoption_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('responseoption_language')) < 1) {
        $form_state->setErrorByName('responseoption_language', $this->t('Please enter a valid language'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $api = \Drupal::service('rep.api_connector');
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      // UPDATE

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getResponseOption()->hasStatus === VSTOI::CURRENT || $this->getResponseOption()->hasStatus === VSTOI::DEPRECATED) {

        // VERSION HAS CHANGED MUST CREATE NEW ONE
        $newResponseOptionUri = Utils::uriGen('responseoption');
        $responseOptionJSON_new = '{"uri":"'. $newResponseOptionUri .'",'.
          '"typeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
          '"hascoTypeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
          '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
          '"comment":"'.$form_state->getValue('responseoption_description').'",'.
          '"wasDerivedFrom":"'.$this->getResponseOption()->uri.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->responseOptionAdd($responseOptionJSON_new);
        \Drupal::messenger()->addMessage(t("New Version Response Option has been created successfully."));

      } else {

        // ITS DRAFT UPDATE CURRENT REGISTRY
        $responseOptionJSON = '{"uri":"'. $this->getResponseOption()->uri .'",'.
          '"typeUri":"'.$this->getResponseOption()->typeUri.'",'.
          '"hascoTypeUri":"'.$this->getResponseOption()->hascoTypeUri.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
          '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
          '"comment":"'.$form_state->getValue('responseoption_description').'",'.
          '"wasDerivedFrom":"'.$this->getResponseOption()->wasDerivedFrom.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->responseOptionDel($this->getResponseOption()->uri);
        $api->responseOptionAdd($responseOptionJSON);
        \Drupal::messenger()->addMessage(t("Response Option has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Response Option: ".$e->getMessage()));
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
