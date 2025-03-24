<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\REPGUI;

class EditActuatorForm extends FormBase {

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
    return 'edit_actuator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $actuatoruri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

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

    // dpm($this->getActuator());
    $form['actuator_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getActuatorUri()).'">'.$this->getActuatorUri().'</a>'),
    ];
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
    ];
    $form['actuator_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.actuator_codebook_autocomplete',
    ];
    $form['actuator_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getActuator()->hasStatus === VSTOI::CURRENT || $this->getActuator()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getActuator()->hasVersion + 1 : $this->getActuator()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    if (isset($this->getActuator()->isAttributeOf)) {
      $api = \Drupal::service('rep.api_connector');
      $attributOf = $api->parseObjectResponse($api->getUri($this->getActuator()->isAttributeOf),'getUri');
    }
    $form['actuator_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'actuator_isAttributeOf',
        '#default_value' => (isset($attributOf) ? $attributOf->label . ' [' . $this->getActuator()->isAttributeOf . ']' : ''),
        '#id' => 'actuator_isAttributeOf',
        '#parents' => ['actuator_isAttributeOf'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorattribute',
          ], ['query' => ['field_id' => 'actuator_isAttributeOf']])->toString(),
          'data-field-id' => 'actuator_isAttributeOf',
          'data-elementtype' => 'detectorattribute',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['actuator_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getActuator()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
    if ($this->getActuator()->hasReviewNote !== NULL && $this->getActuator()->hasSatus !== null) {
      $form['actuator_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getActuator()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['actuator_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getActuator()->hasEditorEmail,
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

    try{

      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      // GET THE DETECTOR STEM URI
      $rawresponse = $api->getUri(Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')));
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      $label = "";
      if ($result->hasContent !== NULL) {
        $label .= $result->hasContent;
      } else {
        $label .= $result->label;
      }

      if ($form_state->getValue('actuator_codebook') !== NULL && $form_state->getValue('actuator_codebook') != '') {
        $codebook = Utils::uriFromAutocomplete($form_state->getValue('actuator_codebook'));
        $rawresponseCB = $api->getUri($codebook);
        $objCB = json_decode($rawresponseCB);
        $resultCB = $objCB->body;
        $label .= '  -- CB:'.$resultCB->label;
      } else {
        $label = $result->label . '  -- CB:EMPTY';
      }

      $hasCodebook = '';
      if ($form_state->getValue('actuator_codebook') != NULL && $form_state->getValue('actuator_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('actuator_codebook'));
      }

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getActuator()->hasStatus === VSTOI::CURRENT || $this->getActuator()->hasStatus === VSTOI::DEPRECATED) {

        $newActuatorUri = Utils::uriGen('actuator');
        $actuatorJson = '{"uri":"'.$newActuatorUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
        '"hasActuatorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasWebDocument":"'.$form_state->getValue('actuator_webdocument').'",'.
        '"hasVersion":"'.$form_state->getValue('actuator_version').'",'.
        '"isAttributeOf":"'.$form_state->getValue('actuator_isAttributeOf').'",'.
        '"wasDerivedFrom":"'.$this->getActuator()->uri.'",'.
        '"hasReviewNote":"'.($this->getActuator()->hasSatus !== null ? $this->getActuator()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getActuator()->hasSatus !== null ? $this->getActuator()->hasEditorEmail : '').'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'"}';

        $api->elementAdd('actuator',$actuatorJson);
        \Drupal::messenger()->addMessage(t("New Version actuator has been created successfully."));

      } else {

        $actuatorJson = '{"uri":"'.$this->getActuator()->uri.'",'.
          '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
          '"hasActuatorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
          '"hasCodebook":"'.$hasCodebook.'",'.
          '"hasContent":"'.$label.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'",'.
          '"label":"'.$label.'",'.
          '"hasWebDocument":"'.$form_state->getValue('actuator_webdocument').'",'.
          '"hasVersion":"'.$form_state->getValue('actuator_version').'",'.
          '"isAttributeOf":"'.$form_state->getValue('actuator_isAttributeOf').'",'.
          '"wasDerivedFrom":"'.$this->getActuator()->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$this->getActuator()->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$this->getActuator()->hasEditorEmail.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->elementDel('actuator',$this->getActuatorUri());
        $api->elementAdd('actuator',$actuatorJson);
        \Drupal::messenger()->addMessage(t("Actuator has been updated successfully."));
      }

      self::backUrl();
      return;

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
