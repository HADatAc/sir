<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class ReviewActuatorForm extends FormBase {

  protected $actuatorUri;

  protected $actuator;

  protected $sourceActuator;

  public function getActuatorUri() {
    return $this->actuatorUri;
  }

  public function setActuatorUri($uri) {
    return $this->actuatorUri = $uri;
  }

  public function getActuator() {
    return $this->actuator;
  }

  public function setActuator($obj) {
    return $this->actuator = $obj;
  }

  public function getSourceActuator() {
    return $this->sourceActuator;
  }

  public function setSourceActuator($obj) {
    return $this->sourceActuator = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_actuator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $actuatoruri = NULL) {


    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';


    $uri=$actuatoruri;
    $uri_decode=base64_decode($uri);
    $this->setActuatorUri($uri_decode);

    $sourceContent = '';
    $stemLabel = '';
    $codebookLabel = '';
    $this->setActuator($this->retrieveActuator($this->getActuatorUri()));
    if ($this->getActuator() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Actuator."));
      self::backUrl();
      return;
    } else {
      if ($this->getActuator()->actuatorStem != NULL) {
        $stemLabel = $this->getActuator()->actuatorStem->hasContent . ' [' . $this->getActuator()->actuatorStem->uri . ']';
      }
      if ($this->getActuator()->codebook != NULL) {
        $codebookLabel = $this->getActuator()->codebook->label . ' [' . $this->getActuator()->codebook->uri . ']';
      }
    }

    //dpm($this->getActuator());

    $form['actuator_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
          $this->t('Simulation Technique Stem') :
          $this->t('Actuator Stem'),
        '#name' => 'actuator_stem',
        '#default_value' => Utils::fieldToAutocomplete($this->getActuator()->typeUri, $this->getActuator()->actuatorStem->label),
        '#id' => 'actuator_stem',
        '#parents' => ['actuator_stem'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'actuatorstem',
          ], ['query' => ['field_id' => 'actuator_stem']])->toString(),
          'data-field-id' => 'actuator_stem',
          'data-elementtype' => 'actuatorstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
      '#disabled' => TRUE
    ];
    $form['actuator_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.actuator_codebook_autocomplete',
      '#disabled' => TRUE
    ];
    $form['actuator_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getActuator()->hasStatus === VSTOI::CURRENT || $this->getActuator()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getActuator()->hasVersion + 1 : $this->getActuator()->hasVersion,
      '#disabled' => TRUE
    ];
    $form['actuator_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'actuator_isAttributeOf',
        '#default_value' => $this->getActuator()->isAttributeOf,
        '#id' => 'actuator_isAttributeOf',
        '#parents' => ['actuator_isAttributeOf'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'actuatorattribute',
          ], ['query' => ['field_id' => 'actuator_isAttributeOf']])->toString(),
          'data-field-id' => 'actuator_isAttributeOf',
          'data-elementtype' => 'actuatorattribute',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
      '#disabled' => TRUE
    ];
    if ($this->getActuator()->wasDerivedFrom !== NULL) {
      $form['actuator_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      $form['actuator_df_wrapper']['actuator_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getActuator()->wasDerivedFrom,
        '#attributes' => [
          'class' => ['flex-grow-1'],
          'style' => "width: 100%; min-width: 0;",
          'disabled' => 'disabled',
        ],
      ];

      $elementUri = Utils::namespaceUri($this->getActuator()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['actuator_df_wrapper']['actuator_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['actuator_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getActuator()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
    ];
    $form['actuator_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getActuator()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['actuator_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getActuator()->hasReviewNote,
    ];
    $form['actuator_haseditoremail'] = [
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
      // if ($button_name === 'review_reject') {
      //   if(strlen($form_state->getValue('actuator_hasreviewnote')) < 1) {
      //     $form_state->setErrorByName('actuator_hasreviewnote', $this->t('You must enter a Reject Note'));
      //   }
      // }
      if(strlen($form_state->getValue('actuator_stem')) < 1) {
        $form_state->setErrorByName('actuator_stem', $this->t('Please enter a valid actuator stem'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $api = \Drupal::service('rep.api_connector');
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    if ($button_name === 'review_reject' && strlen($form_state->getValue('actuator_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getActuator();

      //APROVE
      if ($button_name !== 'review_reject') {

        $actuatorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
          '"hasActuatorStem":"'.$result->hasActuatorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('actuator_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'"'.
          '"hasWebDocument":"'.$result->hasWebDocument.'",'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->actuatorDel($result->uri);
        $api->actuatorAdd($actuatorJson);

        //IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED
        if ($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') {

          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentActuatorJson = '{'.
            '"uri":"'.$resultParent->uri.'",'.
            '"typeUri":"'.$resultParent->typeUri.'",'.
            '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
            '"hasActuatorStem":"'.$resultParent->hasActuatorStem.'",'.
            '"hasCodebook":"'.$resultParent->hasCodebook.'",'.
            '"hasContent":"'.$resultParent->label.'",'.
            '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'",'.
            '"label":"'.$resultParent->label.'",'.
            '"hasVersion":"'.$resultParent->hasVersion.'",'.
            '"isAttributeOf":"'.$resultParent->isAttributeOf.'",'.
            '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",'.
            '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
            '"hasEditorEmail":"'.$resultParent->hasEditorEmail.'",'.
            '"hasStatus":"'.VSTOI::DEPRECATED.'"'.
            '"hasWebDocument":"'.$resultParent->hasWebDocument.'",'.
          '}';

          // UPDATE BY DELETING AND CREATING
          $api->actuatorDel($resultParent->uri);
          $api->actuatorAdd($parentActuatorJson);
        }

        \Drupal::messenger()->addMessage(t("Actuator has been APPROVED successfully."));
          self::backUrl();
          return;

      // REJECT
      } else {

        //MAIN BODY CODEBOOK
        $actuatorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
          '"hasActuatorStem":"'.$result->hasActuatorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('actuator_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // \Drupal::messenger()->addWarning($actuatorJson);
        // return false;

        // UPDATE BY DELETING AND CREATING
        $api->actuatorDel($result->uri);
        $api->actuatorAdd($actuatorJson);

        \Drupal::messenger()->addWarning(t("Actuator has been REJECTED."));
          self::backUrl();
          return;
      }

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Actuator: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveActuator($actuatorUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($actuatorUri);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      return $obj->body;
    }
    return NULL;
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
