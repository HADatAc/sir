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
    $sourceuri = $sourcedetectorstemuri;
    $this->setSourceDetectorStemUri($sourceuri);
    // if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
    //   $this->setSourceDetectorStem(NULL);
    //   $this->setSourceDetectorStemUri('');
    // } else {
    //   $sourceuri_decode=base64_decode($sourceuri);
    //   $this->setSourceDetectorStemUri($sourceuri_decode);
    //   $rawresponse = $api->getUri($this->getSourceDetectorStemUri());
    //   $obj = json_decode($rawresponse);
    //   if ($obj->isSuccessful) {
    //     $this->setSourceDetectorStem($obj->body);
    //   } else {
    //     $this->setSourceDetectorStem(NULL);
    //     $this->setSourceDetectorStemUri('');
    //   }
    // }
    // $disabledDerivationOption = ($this->getSourceDetectorStem() === NULL);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    if ($sourceuri === 'DERIVED') unset($derivations[Constant::WGB_ORIGINAL]);

    //SELECT ONE

    if ($languages)
      $languages = ['' => $this->t('Select language please')] + $languages;

    if ($derivations)
      $derivations = ['' => $this->t('Select derivation please')] + $derivations;

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
      '#default_value' => 'en',
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
    $form['detectorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Generated By'),
      '#options' => $derivations,
      '#default_value' => Constant::WGB_ORIGINAL,
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
        'id' => 'cancel_button'
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
        $form_state->setErrorByName('detectorstem_content', $this->t('Please enter a valid Name'));
      }
      if(strlen($form_state->getValue('detectorstem_language')) < 1) {
        $form_state->setErrorByName('detectorstem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('detectorstem_was_generated_by')) < 1) {
        $form_state->setErrorByName('detectorstem_was_generated_by', $this->t('Please select a derivation'));
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
    $sourceuri = $this->getSourceDetectorStemUri();

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    $api = \Drupal::service('rep.api_connector');

    try {
      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW DETECTOR
      // #1 CENARIO - ADD DETECTOR NO DERIVED FROM
      if ($sourceuri === 'EMPTY') {
        $newDetectorStemUri = Utils::uriGen('detectorstem');
        $detectorStemJson = '{"uri":"'.$newDetectorStemUri.'",'.
          '"superUri":"'.UTILS::uriFromAutocomplete($form_state->getValue('detectorstem_type')).'",'.
          '"label":"'.$form_state->getValue('detectorstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('detectorstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('detectorstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('detectorstem_version').'",'.
          '"comment":"'.$form_state->getValue('detectorstem_description').'",'.
          '"hasWebDocument":"'.$form_state->getValue('detectorstem_webdocument').'",'.
          '"wasGeneratedBy":"'.$form_state->getValue('detectorstem_was_generated_by').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->detectorStemAdd($detectorStemJson);

      } else {
        // #2 CENARIO - ADD DETECTOR THAT WAS DERIVED FROM
        // DERIVED FROM VALUES
        $parentResult = '';
        $rawresponse = $api->getUri(UTILS::uriFromAutocomplete($form_state->getValue('detectorstem_type')));
        $obj = json_decode($rawresponse);
        if ($obj->isSuccessful) {
          $parentResult = $obj->body;
        }

        //dpm($parentResult);
        /* NOTES:
          IF Derivation is Specialization the element is a CHILD of the Derivation
          IF Derivation is a Refinement the element keeps the same dependency has the previous version element
          IF Translation, must have a differente Language but keeps the same dependency of the previous/new element
        */
        if ($parentResult !== '') {

          $newDetectorStemUri = Utils::uriGen('detectorstem');
          $detectorStemJson = '{"uri":"'.$newDetectorStemUri.'",'.
            '"superUri":"'.($form_state->getValue('detectorstem_was_generated_by') === Constant::WGB_SPECIALIZATION ? UTILS::uriFromAutocomplete($form_state->getValue('detectorstem_type')) : $parentResult->superUri).'",'.
            '"label":"'.$form_state->getValue('detectorstem_content').'",'.
            '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
            '"hasStatus":"'.VSTOI::DRAFT.'",'.
            '"hasContent":"'.$form_state->getValue('detectorstem_content').'",'.
            '"hasLanguage":"'.$form_state->getValue('detectorstem_language').'",'.
            '"hasVersion":"'.$form_state->getValue('detectorstem_version').'",'.
            '"comment":"'.$form_state->getValue('detectorstem_description').'",'.
            '"hasWebDocument":"'.$form_state->getValue('detectorstem_webdocument').'",'.
            '"wasDerivedFrom":"'.UTILS::uriFromAutocomplete($form_state->getValue('detectorstem_type')).'",'.
            '"wasGeneratedBy":"'.$form_state->getValue('detectorstem_was_generated_by').'",'.
            '"hasSIRManagerEmail":"'.$useremail.'"}';

          $api->detectorStemAdd($detectorStemJson);

        } else {
          \Drupal::messenger()->addError(t("An error occurred while getting Derived From element"));
          self::backUrl();
          return;
        }
      }

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
