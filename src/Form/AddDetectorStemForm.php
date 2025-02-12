<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddDetectorStemForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_detectorstem_form';
  }

  protected $sourceDetectorStemUri;

  protected $sourceDetectorStem;

  public function getSourceDetectorStemUri() {
    return $this->sourceDetectorStemUri;
  }

  public function setSourceDetectorStemUri($uri) {
    return $this->sourceDetectorStemUri = $uri;
  }

  public function getSourceDetectorStem() {
    return $this->sourceDetectorStem;
  }

  public function setSourceDetectorStem($obj) {
    return $this->sourceDetectorStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourcedetectorstemuri = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_detectorstem';

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE DETECTOR STEM,  IF ANY
    $sourceuri=$sourcedetectorstemuri;
    if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
      $this->setSourceDetectorStem(NULL);
      $this->setSourceDetectorStemUri('');
    } else {
      $sourceuri_decode=base64_decode($sourceuri);
      $this->setSourceDetectorStemUri($sourceuri_decode);
      $rawresponse = $api->getUri($this->getSourceDetectorStemUri());
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setSourceDetectorStem($obj->body);
      } else {
        $this->setSourceDetectorStem(NULL);
        $this->setSourceDetectorStemUri('');
      }
    }
    $disabledDerivationOption = ($this->getSourceDetectorStem() === NULL);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    //Removed has decided on 10/fev/2025
    unset($derivations['http://hadatac.org/ont/vstoi#Generalization']);

    if ($sourceuri === 'DERIVED') unset($derivations['http://hadatac.org/ont/vstoi#Original']);

    //SELECT ONE
    $languages = ['' => $this->t('Select one please')] + $languages;
    $derivations = ['' => $this->t('Select one please')] + $derivations;

    $sourceContent = '';
    if ($this->getSourceDetectorStem() != NULL) {
      $sourceContent = Utils::fieldToAutocomplete($this->getSourceDetectorStem()->uri,$this->getSourceDetectorStem()->hasContent);
    }

    $form['detectorstem_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $sourceuri === 'EMPTY' ? $this->t('Parent Type') : $this->t('Derive From'),
        '#name' => 'detectorstem_type',
        '#default_value' => '',
        '#id' => 'detectorstem_type',
        '#parents' => ['detectorstem_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorstem',
          ], ['query' => ['field_id' => 'detectorstem_type']])->toString(),
          'data-field-id' => 'detectorstem_type',
          'data-elementtype' => 'detectorstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['detectorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['detectorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => '',
      '#attributes' => [
        'id' => 'detectorstem_language'
      ]
    ];
    $form['detectorstem_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['detectorstem_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['detectorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    $form['detectorstem_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];

    // if ($sourceuri !== 'EMPTY') {
    //   $form['detectorstem_was_derived_from'] = [
    //     '#type' => 'textfield',
    //     '#title' => $this->t('Was Derived From'),
    //     '#default_value' => $sourceContent,
    //     '#disabled' => TRUE,
    //   ];
    // }

    $form['detectorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Generated By'),
      '#options' => $derivations,
      '#default_value' => Constant::DEFAULT_WAS_GENERATED_BY,
      '#disabled' => $sourceuri === 'EMPTY' ? true:false,
      '#attributes' => [
        'id' => 'detectorstem_was_generated_by'
      ]
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
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
      if(strlen($form_state->getValue('detectorstem_content')) < 1) {
        $form_state->setErrorByName('detectorstem_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('detectorstem_language')) < 1) {
        $form_state->setErrorByName('detectorstem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('detectorstem_version')) < 1) {
        $form_state->setErrorByName('detectorstem_version', $this->t('Please enter a valid version'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try {

      $wasDerivedFrom = '';
      if ($this->getSourceDetectorStemUri() === NULL) {
        $wasDerivedFrom = 'null';
      } else {
        $wasDerivedFrom = $this->getSourceDetectorStemUri();
      }
      $wasGeneratedBy = $form_state->getValue('detectorstem_was_generated_by');

      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW DETECTOR
      $newDetectorStemUri = Utils::uriGen('detectorstem');
      $detectorStemJson = '{"uri":"'.$newDetectorStemUri.'",'.
        '"superUri":"'.UTILS::uriFromAutocomplete($form_state->getValue('detectorstem_type')).'",'.
        '"label":"'.$form_state->getValue('detectorstem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"hasContent":"'.$form_state->getValue('detectorstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('detectorstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('detectorstem_version').'",'.
        '"hasWebDocument":"'.$form_state->getValue('detectorstem_webdocument').'",'.
        '"comment":"'.$form_state->getValue('detectorstem_description').'",'.
        '"wasDerivedFrom":"'.$wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$wasGeneratedBy.'",'.
        '"hasReviewNote":"'.$this->getSourceDetectorStem()->hasReviewNote.'",'.
        '"hasEditorEmail":"'.$this->getSourceDetectorStem()->hasEditorEmail.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';
      $api = \Drupal::service('rep.api_connector');
      $api->detectorStemAdd($detectorStemJson);
      \Drupal::messenger()->addMessage(t("Added a new Detector Stem with URI: ".$newDetectorStemUri));
      self::backUrl();
      return;

    } catch(\Exception $e) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Detector Stem: ".$e->getMessage()));
        self::backUrl();
        return;
      }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_detectorstem');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
