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
use Drupal\file\Entity\File;

class AddActuatorStemForm extends FormBase {

  protected $actuatorstemUri;

  public function setInstrumenUri() {
    $this->actuatorstemUri = Utils::uriGen('actuatorstem');
  }

  public function getInstrumenUri() {
    return $this->actuatorstemUri;
  }

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

    // Check if the actuatorstem URI already exists in the form state.
    // If not, generate a new URI and store it in the form state.
    if (!$form_state->has('actuatorstem_uri')) {
      $this->setInstrumenUri();
      $form_state->set('actuatorstem_uri', $this->getInstrumenUri());
    }
    else {
      // Retrieve the persisted URI from form state.
      $this->actuatorstemUri = $form_state->get('actuatorstem_uri');
    }

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

    // Add a hidden field to persist the actuatorstem URI between form rebuilds.
    $form['actuatorstem_uri'] = [
      '#type' => 'hidden',
      '#value' => $this->actuatorstemUri,
    ];

    // Add a select box to choose between URL and Upload.
    $form['actuatorstem_image_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Type'),
      '#options' => [
        '' => $this->t('Select Image Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => '',
    ];

    // The textfield for entering a URL.
    // It is only visible when the select box value is 'url'.
    $form['actuatorstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted actuatorstem URI for file uploads)
    $modUri = (explode(":/", utils::namespaceUri($this->actuatorstemUri)))[1];
    $form['actuatorstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['actuatorstem_image_upload_wrapper']['actuatorstem_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
    ];

    // Add a select box to choose between URL and Upload.
    $form['actuatorstem_webdocument_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Web Document Type'),
      '#options' => [
        '' => $this->t('Select Document Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => '',
    ];

    // The textfield for entering a URL.
    // It is only visible when the select box value is 'url'.
    $form['actuatorstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted actuatorstem URI for file uploads)
    $form['actuatorstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['actuatorstem_webdocument_upload_wrapper']['actuatorstem_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
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

      // $newActuatorStemUri = Utils::uriGen('actuatorstem');
      $newActuatorStemUri = $form_state->getValue('actuatorstem_uri');

      // Determine the chosen document type.
      $doc_type = $form_state->getValue('actuatorstem_webdocument_type');
      $actuatorstem_webdocument = '';

      // If user selected URL, use the textfield value.
      if ($doc_type === 'url') {
        $actuatorstem_webdocument = $form_state->getValue('actuatorstem_webdocument_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($doc_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('actuatorstem_webdocument_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'actuatorstem', 1);
            // Now get the filename from the file entity.
            $actuatorstem_webdocument = $file->getFilename();
          }
        }
      }

      // Determine the chosen image type.
      $image_type = $form_state->getValue('actuatorstem_image_type');
      $actuatorstem_image = '';

      // If user selected URL, use the textfield value.
      if ($image_type === 'url') {
        $actuatorstem_image = $form_state->getValue('actuatorstem_image_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($image_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('actuatorstem_image_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'actuatorstem', 1);
            // Now get the filename from the file entity.
            $actuatorstem_image = $file->getFilename();
          }
        }
      }

      // CREATE A NEW ACTUATOR
      // #1 CENARIO - ADD ACTUATOR NO DERIVED FROM
      if ($sourceuri === 'EMPTY') {
        $actuatorStemJson = '{"uri":"'.$newActuatorStemUri.'",'.
          '"superUri":"'.UTILS::uriFromAutocomplete($form_state->getValue('actuatorstem_type')).'",'.
          '"label":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('actuatorstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('actuatorstem_version').'",'.
          '"comment":"'.$form_state->getValue('actuatorstem_description').'",'.
          '"hasWebDocument":"' . $actuatorstem_webdocument . '",' .
          '"hasImageUri":"' . $actuatorstem_image . '",' .
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
