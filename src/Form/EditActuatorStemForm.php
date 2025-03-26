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
use Drupal\file\Entity\File;

class EditActuatorStemForm extends FormBase {

  protected $actuatorStemUri;

  protected $actuatorStem;

  protected $sourceActuatorStem;

  public function getActuatorStemUri() {
    return $this->actuatorStemUri;
  }

  public function setActuatorStemUri($uri) {
    return $this->actuatorStemUri = $uri;
  }

  public function getActuatorStem() {
    return $this->actuatorStem;
  }

  public function setActuatorStem($obj) {
    return $this->actuatorStem = $obj;
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
  public function getFormId() {
    return 'edit_actuatorstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $actuatorstemuri = NULL) {
    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_actuatorstem';

    $uri=$actuatorstemuri;
    $uri_decode=base64_decode($uri);
    $this->setActuatorStemUri($uri_decode);

    $this->setActuatorStem($this->retrieveActuatorStem($this->getActuatorStemUri()));
    if ($this->getActuatorStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Actuator."));
      self::backUrl();
      return;
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    // IN CASE ITS A DERIVATION ORIGINAL MUST BE REMOVED ALSO
    if ($this->getActuatorStem()->hasStatus === VSTOI::CURRENT || $this->getActuatorStem()->hasVersion > 1) {
      unset($derivations[Constant::DEFAULT_WAS_GENERATED_BY]);
    }

    $languages = ['' => $this->t('Select one please')] + $languages;
    $derivations = ['' => $this->t('Select one please')] + $derivations;

    // dpm($this->getActuatorStem());
    $form['actuatorstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getActuatorStemUri()).'">'.$this->getActuatorStemUri().'</a>'),
    ];
    if ($this->getActuatorStem()->superUri) {
      $form['actuatorstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="pt-3 col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Parent Type'),
          '#name' => 'actuatorstem_type',
          '#default_value' => $this->getActuatorStem()->superUri ? Utils::fieldToAutocomplete($this->getActuatorStem()->superUri, $this->getActuatorStem()->superClassLabel) : '',
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
            'data-search-value' => $this->getActuatorStem()->superUri ?? '',
          ],
        ],
        'bottom' => [
          '#type' => 'markup',
          '#markup' => '</div>',
        ],
      ];
    }

    $form['actuatorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getActuatorStem()->hasContent,
    ];
    $form['actuatorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getActuatorStem()->hasLanguage,
      '#attributes' => [
        'id' => 'actuatorstem_language'
      ]
    ];
    $form['actuatorstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getActuatorStem()->hasStatus === VSTOI::CURRENT || $this->getActuatorStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getActuatorStem()->hasVersion + 1 : $this->getActuatorStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['actuatorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getActuatorStem()->comment,
    ];

    if ($this->getActuatorStem()->wasDerivedFrom !== NULL) {
      $form['actuatorstem_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      if ($this->getActuatorStem()->wasDerivedFrom !== NULL) {
        $form['actuatorstem_df_wrapper']['actuatorstem_wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => $this->getActuatorStem()->wasDerivedFrom,
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 0;",
            'disabled' => 'disabled',
          ],
        ];
      }

      $elementUri = Utils::namespaceUri($this->getActuatorStem()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['actuatorstem_df_wrapper']['actuatorstem_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['actuatorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $this->getActuatorStem()->wasGeneratedBy,
      '#attributes' => [
        'id' => 'actuatorstem_was_generated_by'
      ],
      '#disabled' => ($this->getActuatorStem()->wasGeneratedBy === Constant::WGB_ORIGINAL ? true:false)
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current actuatorstem and its image.
    $actuatorstem = $this->getActuatorStem();
    $actuatorstem_uri = Utils::namespaceUri($this->getActuatorStemUri());
    $actuatorstem_image = $actuatorstem->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($actuatorstem_image) && stripos(trim($actuatorstem_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($actuatorstem_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($actuatorstem_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $actuatorstem_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['actuatorstem_information']['actuatorstem_image_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Type'),
      '#options' => [
        '' => $this->t('Select Image Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => $image_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['actuatorstem_information']['actuatorstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($image_type === 'url') ? $actuatorstem_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuatorstem_information']['actuatorstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($actuatorstem_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $actuatorstem_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['actuatorstem_information']['actuatorstem_image_upload_wrapper']['actuatorstem_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [2097152],
      ],
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_image_fid ? [$existing_image_fid] : NULL,
    ];

    // **** WEBDOCUMENT ****
    // Retrieve the current web document value.
    $actuatorstem_webdocument = $actuatorstem->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($actuatorstem_webdocument) && stripos(trim($actuatorstem_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($actuatorstem_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['actuatorstem_information']['actuatorstem_webdocument_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Web Document Type'),
      '#options' => [
        '' => $this->t('Select Document Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => $webdocument_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['actuatorstem_information']['actuatorstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $actuatorstem_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuatorstem_information']['actuatorstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($actuatorstem_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $actuatorstem_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['actuatorstem_information']['actuatorstem_webdocument_upload_wrapper']['actuatorstem_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
      ],
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_fid ? [$existing_fid] : NULL,
    ];

    if ($this->getActuatorStem()->hasReviewNote !== NULL && $this->getActuatorStem()->hasStatus !== null) {
      $form['actuatorstem_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getActuatorStem()->hasReviewNote,
        '#disabled' => TRUE
      ];
      $form['actuatorstem_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => \Drupal::currentUser()->getEmail(),
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

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getActuatorStem()->hasStatus === VSTOI::CURRENT || $this->getActuatorStem()->hasStatus === VSTOI::DEPRECATED) {

        $actuatorStemJson = '{"uri":"'.Utils::uriGen('actuatorstem').'",'.
          '"superUri":"'.Utils::uriFromAutocomplete($this->getActuatorStem()->superUri).'",'.
          '"label":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('actuatorstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('actuatorstem_version').'",'.
          '"comment":"'.$form_state->getValue('actuatorstem_description').'",'.
          '"wasDerivedFrom":"'.$this->getActuatorStem()->uri.'",'. //Previous Version is the New Derivation Value
          '"wasGeneratedBy":"'.$form_state->getValue('actuatorstem_was_generated_by').'",'.~
          '"hasWebDocument":"",'.
          '"hasImageUri":"",' .
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->actuatorStemAdd($actuatorStemJson);
        \Drupal::messenger()->addMessage(t("New Version Actuator Stem has been created successfully."));

      } else {

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

        $actuatorStemJson = '{"uri":"'.$this->getActuatorStem()->uri.'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($this->getActuatorStem()->superUri).'",'.
        '"label":"'.$form_state->getValue('actuatorstem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
        '"hasStatus":"'.$this->getActuatorStem()->hasStatus.'",'.
        '"hasContent":"'.$form_state->getValue('actuatorstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('actuatorstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('actuatorstem_version').'",'.
        '"comment":"'.$form_state->getValue('actuatorstem_description').'",'.
        '"hasWebDocument":"' . $actuatorstem_webdocument . '",' .
        '"hasImageUri":"' . $actuatorstem_image . '",' .
        '"wasDerivedFrom":"'.$this->getActuatorStem()->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('actuatorstem_was_generated_by').'",'.
        '"hasReviewNote":"'.($this->getActuatorStem()->hasStatus !== null ? $this->getActuatorStem()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getActuatorStem()->hasStatus !== null ? $this->getActuatorStem()->hasEditorEmail : '').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->actuatorStemDel($this->getActuatorStemUri());
        $api->actuatorStemAdd($actuatorStemJson);
        \Drupal::messenger()->addMessage(t("Actuator Stem has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Actuator Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveActuatorStem($actuatorStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($actuatorStemUri);
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
