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

class AddActuatorStemForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_actuatorstem_form';
  }

  protected $sourceActuatorStemUri;

  protected $sourceActuatorStem;

  public function getSourceActuatorStemUri() {
    return $this->sourceActuatorStemUri;
  }

  public function setSourceActuatorStemUri($uri) {
    return $this->sourceActuatorStemUri = $uri;
  }

  public function getSourceActuatorStem() {
    return $this->sourceActuatorStem;
  }

  public function setSourceActuatorStem($obj) {
    return $this->sourceActuatorStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourceactuatorstemuri = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_actuatorstem';

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE ACTUATOR STEM,  IF ANY
    $sourceuri = $sourceactuatorstemuri;
    $this->setSourceActuatorStemUri($sourceuri);
    // if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
    //   $this->setSourceActuatorStem(NULL);
    //   $this->setSourceActuatorStemUri('');
    // } else {
    //   $sourceuri_decode=base64_decode($sourceuri);
    //   $this->setSourceActuatorStemUri($sourceuri_decode);
    //   $rawresponse = $api->getUri($this->getSourceActuatorStemUri());
    //   $obj = json_decode($rawresponse);
    //   if ($obj->isSuccessful) {
    //     $this->setSourceActuatorStem($obj->body);
    //   } else {
    //     $this->setSourceActuatorStem(NULL);
    //     $this->setSourceActuatorStemUri('');
    //   }
    // }
    // $disabledDerivationOption = ($this->getSourceActuatorStem() === NULL);

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
    if ($this->getSourceActuatorStem() != NULL) {
      $sourceContent = Utils::fieldToAutocomplete($this->getSourceActuatorStem()->uri,$this->getSourceActuatorStem()->hasContent);
    }

    $form['actuatorstem_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $sourceuri === 'EMPTY' ? $this->t('Parent Type') : $this->t('Derive From'),
        '#name' => 'actuatorstem_type',
        '#default_value' => '',
        '#id' => 'actuatorstem_type',
        '#parents' => ['actuatorstem_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'actuatorstem',
          ], ['query' => ['field_id' => 'actuatorstem_type']])->toString(),
          'data-field-id' => 'actuatorstem_type',
          'data-elementtype' => 'actuatorstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['actuatorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['actuatorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
      '#attributes' => [
        'id' => 'actuatorstem_language'
      ]
    ];
    $form['actuatorstem_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['actuatorstem_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['actuatorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['actuatorstem_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
    // if ($sourceuri !== 'EMPTY') {
    //   $form['actuatorstem_was_derived_from'] = [
    //     '#type' => 'textfield',
    //     '#title' => $this->t('Was Derived From'),
    //     '#default_value' => $sourceContent,
    //     '#disabled' => TRUE,
    //   ];
    $form['actuatorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Generated By'),
      '#options' => $derivations,
      '#default_value' => Constant::WGB_ORIGINAL,
      '#disabled' => $sourceuri === 'EMPTY' ? true:false,
      '#attributes' => [
        'id' => 'actuatorstem_was_generated_by'
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
      if(strlen($form_state->getValue('actuatorstem_content')) < 1) {
        $form_state->setErrorByName('actuatorstem_content', $this->t('Please enter a valid Name'));
      }
      if(strlen($form_state->getValue('actuatorstem_language')) < 1) {
        $form_state->setErrorByName('actuatorstem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('actuatorstem_was_generated_by')) < 1) {
        $form_state->setErrorByName('actuatorstem_was_generated_by', $this->t('Please select a derivation'));
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
    $sourceuri = $this->getSourceActuatorStemUri();

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    $api = \Drupal::service('rep.api_connector');

    try {
      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW ACTUATOR
      // #1 CENARIO - ADD ACTUATOR NO DERIVED FROM
      if ($sourceuri === 'EMPTY') {
        $newActuatorStemUri = Utils::uriGen('actuatorstem');
        $actuatorStemJson = '{"uri":"'.$newActuatorStemUri.'",'.
          '"superUri":"'.UTILS::uriFromAutocomplete($form_state->getValue('actuatorstem_type')).'",'.
          '"label":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('actuatorstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('actuatorstem_version').'",'.
          '"comment":"'.$form_state->getValue('actuatorstem_description').'",'.
          '"hasWebDocument":"'.$form_state->getValue('actuatorstem_webdocument').'",'.
          '"wasGeneratedBy":"'.$form_state->getValue('actuatorstem_was_generated_by').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->actuatorStemAdd($actuatorStemJson);

      } else {
        // #2 CENARIO - ADD ACTUATOR THAT WAS DERIVED FROM
        // DERIVED FROM VALUES
        $parentResult = '';
        $rawresponse = $api->getUri(UTILS::uriFromAutocomplete($form_state->getValue('actuatorstem_type')));
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

          $newActuatorStemUri = Utils::uriGen('actuatorstem');
          $actuatorStemJson = '{"uri":"'.$newActuatorStemUri.'",'.
            '"superUri":"'.($form_state->getValue('actuatorstem_was_generated_by') === Constant::WGB_SPECIALIZATION ? UTILS::uriFromAutocomplete($form_state->getValue('actuatorstem_type')) : $parentResult->superUri).'",'.
            '"label":"'.$form_state->getValue('actuatorstem_content').'",'.
            '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
            '"hasStatus":"'.VSTOI::DRAFT.'",'.
            '"hasContent":"'.$form_state->getValue('actuatorstem_content').'",'.
            '"hasLanguage":"'.$form_state->getValue('actuatorstem_language').'",'.
            '"hasVersion":"'.$form_state->getValue('actuatorstem_version').'",'.
            '"comment":"'.$form_state->getValue('actuatorstem_description').'",'.
            '"hasWebDocument":"'.$form_state->getValue('actuatorstem_webdocument').'",'.
            '"wasDerivedFrom":"'.UTILS::uriFromAutocomplete($form_state->getValue('actuatorstem_type')).'",'.
            '"wasGeneratedBy":"'.$form_state->getValue('actuatorstem_was_generated_by').'",'.
            '"hasSIRManagerEmail":"'.$useremail.'"}';

          $api->actuatorStemAdd($actuatorStemJson);

        } else {
          \Drupal::messenger()->addError(t("An error occurred while getting Derived From element"));
          self::backUrl();
          return;
        }
      }

      \Drupal::messenger()->addMessage(t("Added a new Actuator Stem with URI: ".$newActuatorStemUri));
      self::backUrl();
      return;

    } catch(\Exception $e) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Actuator Stem: ".$e->getMessage()));
        self::backUrl();
        return;
      }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_actuatorstem');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
