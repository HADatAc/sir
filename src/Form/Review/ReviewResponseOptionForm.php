<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\sir\Entity\ResponseOption;

class ReviewResponseOptionForm extends FormBase {

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
    return 'review_responseoption_form';
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
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getResponseOption()->hasLanguage,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getResponseOption()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getResponseOption()->comment,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    if ($this->getResponseOption()->wasDerivedFrom !== NULL) {
      $form['responseoption_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getResponseOption()->wasDerivedFrom,
        '#attributes' => [
          'disabled' => 'disabled',
        ],
      ];
    }
    $form['responseoption_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getResponseOption()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getResponseOption()->hasReviewNote,
    ];
    $form['responseoption_haseditoremail'] = [
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
      '#title' => t('<br>'),
    ];
    $form['back'] = [
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
      $api = \Drupal::service('rep.api_connector');
      // APPROVE
      if ($button_name === 'review_approve') {

        // IF wasDerivedFrom not NULL
        if ($this->getResponseOption()->wasDerivedFrom !== NULL) {

          // PARENT TO DEPRECATED
          ResponseOption::cloneResponseOption($this->getResponseOption()->wasDerivedFrom, VSTOI::DEPRECATED);

          // THIS TO CURRENT
          ResponseOption::cloneResponseOption($this->getResponseOption()->uri, VSTOI::CURRENT);

          // $responseOptionJSON = '{"uri":"'. $this->getResponseOption()->uri .'",'.
          //   '"typeUri":"'.$this->getResponseOption()->typeUri.'",'.
          //   '"hascoTypeUri":"'.$this->getResponseOption()->hascoTypeUri.'",'.
          //   '"hasContent":"'.$this->getResponseOption()->hasContent.'",'.
          //   '"hasLanguage":"'.$this->getResponseOption()->hasLanguage.'",'.
          //   '"hasVersion":"'.$this->getResponseOption()->hasVersion.'",'.
          //   '"hasStatus":"'.VSTOI::CURRENT.'",'.
          //   '"comment":"'.$this->getResponseOption()->comment.'",'.
          //   '"hasSIRManagerEmail":"' . $this->getResponseOption()->hasSIRManagerEmail . '",'.
          //   '"hasReviewNote":"' . $form_state->getValue('responseoption_hasreviewnote') . '",'.
          //   '"hasEditorEmail":"' . \Drupal::currentUser()->getEmail() . '",'.
          //   '"wasDerivedFrom":"' . $this->getResponseOption()->wasDerivedFrom .
          //   '"}';

          // //dpm($responseOptionJSON);

          // // UPDATE BY DELETING AND CREATING
          // $api->responseOptionDel($this->getResponseOption()->uri);
          // $api->responseOptionAdd($responseOptionJSON);

          // //GET PARENT VALUES
          // $rawresponse = $api->getUri($this->getResponseOption()->wasDerivedFrom);
          // $obj = json_decode($rawresponse);
          // $result = $obj->body;
          // $responseOptionParentJSON = '{"uri":"'. $this->getResponseOption()->wasDerivedFrom .'",'.
          //   '"typeUri":"'.$result->typeUri.'",'.
          //   '"hascoTypeUri":"'.$result->hascoTypeUri.'",'.
          //   '"hasContent":"'.$result->hasContent.'",'.
          //   '"hasLanguage":"'.$result->hasLanguage.'",'.
          //   '"hasVersion":"'.$result->hasVersion.'",'.
          //   '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
          //   '"comment":"'.$result->comment.'",'.
          //   '"hasSIRManagerEmail":"' . $result->hasSIRManagerEmail . '",'.
          //   '"hasReviewNote":"' . $result->hasReviewNote . '",'.
          //   '"hasEditorEmail":"'.$result->hasEditorEmail . '",'.
          //   '"wasDerivedFrom":"'.$result->wasDerivedFrom .
          //   '"}';

          // dpm($responseOptionParentJSON);

          // // UPDATE DERIVED FROM RECORD
          // $api->responseOptionDel($result->wasDerivedFrom);
          // $api->responseOptionAdd($responseOptionParentJSON);

        } else {

          // THIS TO CURRENT
          ResponseOption::cloneResponseOption($this->getResponseOption()->uri, VSTOI::CURRENT);

          // // MUST UPDATE STATUS FROM PREVIOUS PARENT
          // $responseOptionJSON = '{"uri":"'. $this->getResponseOption()->uri .'",'.
          //   '"typeUri":"'.$this->getResponseOption()->typeUri.'",'.
          //   '"hascoTypeUri":"'.$this->getResponseOption()->hascoTypeUri.'",'.
          //   '"hasContent":"'.$this->getResponseOption()->hasContent.'",'.
          //   '"hasLanguage":"'.$this->getResponseOption()->hasLanguage.'",'.
          //   '"hasVersion":"'.$this->getResponseOption()->hasVersion.'",'.
          //   '"hasStatus":"'.VSTOI::CURRENT.'",'.
          //   '"comment":"'.$this->getResponseOption()->comment.'",'.
          //   '"hasSIRManagerEmail":"' . $this->getResponseOption()->hasSIRManagerEmail.'",'.
          //   '"hasReviewNote":"' . $form_state->getValue('responseoption_hasreviewnote').'",'.
          //   '"hasEditorEmail":"'.\Drupal::currentUser()->getEmail().'",'.
          //   '"wasDerivedFrom":"'.$this->getResponseOption()->wasDerivedFrom.
          //   '"}';

          // // UPDATE BY DELETING AND CREATING
          // $api->responseOptionDel($this->getResponseOption()->uri);
          // $api->responseOptionAdd($responseOptionJSON);
        }

        \Drupal::messenger()->addMessage(t("Response Option has been Approved."));
        self::backUrl();
        return;

      } else {

        // REJECT

        // THIS TO DRAFT
        ResponseOption::cloneResponseOption($this->getResponseOption()->uri, VSTOI::DRAFT);


        // $responseOptionJSON = '{"uri":"'. $this->getResponseOption()->uri .'",'.
        // '"typeUri":"'.$this->getResponseOption()->typeUri.'",'.
        // '"hascoTypeUri":"'.$this->getResponseOption()->hascoTypeUri.'",'.
        // '"hasContent":"'.$this->getResponseOption()->hasContent.'",'.
        // '"hasLanguage":"'.$this->getResponseOption()->hasLanguage.'",'.
        // '"hasStatus":"'.VSTOI::DRAFT.'",'.
        // '"hasVersion":"'.$this->getResponseOption()->hasVersion.'",'.
        // '"comment":"'.$this->getResponseOption()->comment.'",'.
        // '"hasSIRManagerEmail":"' . $this->getResponseOption()->hasSIRManagerEmail . '",'.
        // '"hasReviewNote":"' . $form_state->getValue('responseoption_hasreviewnote') . '",'.
        // '"hasEditorEmail":"'.\Drupal::currentUser()->getEmail().
        // '"}';

        // // UPDATE BY DELETING AND CREATING
        // $api->responseOptionDel($this->getResponseOption()->uri);
        // $api->responseOptionAdd($responseOptionJSON);

        \Drupal::messenger()->addMessage(t("Response Option has been Rejected."));
        self::backUrl();
        return;

      }


    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while approving/rejecting the Response Option: ".$e->getMessage()));
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
