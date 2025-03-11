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

class AddProcessStemForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_processstem_form';
  }

  protected $sourceProcessStemUri;

  protected $sourceProcessStem;

  public function getSourceProcessStemUri() {
    return $this->sourceProcessStemUri;
  }

  public function setSourceProcessStemUri($uri) {
    return $this->sourceProcessStemUri = $uri;
  }

  public function getSourceProcessStem() {
    return $this->sourceProcessStem;
  }

  public function setSourceProcessStem($obj) {
    return $this->sourceProcessStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourceprocessstemuri = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_processstem';

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE PROCESS STEM,  IF ANY
    $sourceuri = $sourceprocessstemuri;
    $this->setSourceProcessStemUri($sourceuri);
    // if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
    //   $this->setSourceProcessStem(NULL);
    //   $this->setSourceProcessStemUri('');
    // } else {
    //   $sourceuri_decode=base64_decode($sourceuri);
    //   $this->setSourceProcessStemUri($sourceuri_decode);
    //   $rawresponse = $api->getUri($this->getSourceProcessStemUri());
    //   $obj = json_decode($rawresponse);
    //   if ($obj->isSuccessful) {
    //     $this->setSourceProcessStem($obj->body);
    //   } else {
    //     $this->setSourceProcessStem(NULL);
    //     $this->setSourceProcessStemUri('');
    //   }
    // }
    // $disabledDerivationOption = ($this->getSourceProcessStem() === NULL);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    if ($sourceuri === 'DERIVED') unset($derivations[Constant::WGB_ORIGINAL]);

    //SELECT ONE
    $languages = ['' => $this->t('Select language please')] + $languages;
    $derivations = ['' => $this->t('Select derivation please')] + $derivations;

    $sourceContent = '';
    if ($this->getSourceProcessStem() != NULL) {
      $sourceContent = Utils::fieldToAutocomplete($this->getSourceProcessStem()->uri,$this->getSourceProcessStem()->hasContent);
    }

    $form['processstem_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $sourceuri === 'EMPTY' ? $this->t('Parent Type') : $this->t('Derive From'),
        '#name' => 'processstem_type',
        '#default_value' => '',
        '#id' => 'processstem_type',
        '#parents' => ['processstem_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'processstem',
          ], ['query' => ['field_id' => 'processstem_type']])->toString(),
          'data-field-id' => 'processstem_type',
          'data-elementtype' => 'processstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['processstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['processstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
      '#attributes' => [
        'id' => 'processstem_language'
      ]
    ];
    $form['processstem_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['processstem_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['processstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['processstem_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
    // if ($sourceuri !== 'EMPTY') {
    //   $form['processstem_was_derived_from'] = [
    //     '#type' => 'textfield',
    //     '#title' => $this->t('Was Derived From'),
    //     '#default_value' => $sourceContent,
    //     '#disabled' => TRUE,
    //   ];
    $form['processstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Generated By'),
      '#options' => $derivations,
      '#default_value' => Constant::WGB_ORIGINAL,
      '#disabled' => $sourceuri === 'EMPTY' ? true:false,
      '#attributes' => [
        'id' => 'processstem_was_generated_by'
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
      if(strlen($form_state->getValue('processstem_content')) < 1) {
        $form_state->setErrorByName('processstem_content', $this->t('Please enter a valid Name'));
      }
      if(strlen($form_state->getValue('processstem_language')) < 1) {
        $form_state->setErrorByName('processstem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('processstem_was_generated_by')) < 1) {
        $form_state->setErrorByName('processstem_was_generated_by', $this->t('Please select a derivation'));
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
    $sourceuri = $this->getSourceProcessStemUri();

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    $api = \Drupal::service('rep.api_connector');

    try {
      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW PROCESS
      // #1 CENARIO - ADD PROCESS NO DERIVED FROM
      if ($sourceuri === 'EMPTY') {
        $newProcessStemUri = Utils::uriGen('processstem');
        $detectorStemJson = '{"uri":"'.$newProcessStemUri.'",'.
          '"superUri":"'.UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')).'",'.
          '"label":"'.$form_state->getValue('processstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
          '"comment":"'.$form_state->getValue('processstem_description').'",'.
          '"hasWebDocument":"'.$form_state->getValue('processstem_webdocument').'",'.
          '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->detectorStemAdd($detectorStemJson);

      } else {
        // #2 CENARIO - ADD PROCESS THAT WAS DERIVED FROM
        // DERIVED FROM VALUES
        $parentResult = '';
        $rawresponse = $api->getUri(UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')));
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

          $newProcessStemUri = Utils::uriGen('processstem');
          $detectorStemJson = '{"uri":"'.$newProcessStemUri.'",'.
            '"superUri":"'.($form_state->getValue('processstem_was_generated_by') === Constant::WGB_SPECIALIZATION ? UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')) : $parentResult->superUri).'",'.
            '"label":"'.$form_state->getValue('processstem_content').'",'.
            '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
            '"hasStatus":"'.VSTOI::DRAFT.'",'.
            '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
            '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
            '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
            '"comment":"'.$form_state->getValue('processstem_description').'",'.
            '"hasWebDocument":"'.$form_state->getValue('processstem_webdocument').'",'.
            '"wasDerivedFrom":"'.UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')).'",'.
            '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
            '"hasSIRManagerEmail":"'.$useremail.'"}';

          $api->detectorStemAdd($detectorStemJson);

        } else {
          \Drupal::messenger()->addError(t("An error occurred while getting Derived From element"));
          self::backUrl();
          return;
        }
      }

      \Drupal::messenger()->addMessage(t("Added a new Process Stem with URI: ".$newProcessStemUri));
      self::backUrl();
      return;

    } catch(\Exception $e) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Process Stem: ".$e->getMessage()));
        self::backUrl();
        return;
      }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_processstem');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
