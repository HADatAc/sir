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
use Drupal\Core\Render\Markup;

class EditComponentStemForm extends FormBase {

  protected $componentStemUri;

  protected $componentStem;

  protected $sourceComponentStem;

  public function getComponentStemUri() {
    return $this->componentStemUri;
  }

  public function setComponentStemUri($uri) {
    return $this->componentStemUri = $uri;
  }

  public function getComponentStem() {
    return $this->componentStem;
  }

  public function setComponentStem($obj) {
    return $this->componentStem = $obj;
  }

  public function getSourceComponentStem() {
    return $this->sourceComponentStem;
  }

  public function setSourceComponentStem($obj) {
    return $this->sourceComponentStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_componentstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $componentstemuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_componentstem';

    $uri=$componentstemuri;
    $uri_decode=base64_decode($uri);
    $this->setComponentStemUri($uri_decode);

    $this->setComponentStem($this->retrieveComponentStem($this->getComponentStemUri()));
    if ($this->getComponentStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Component."));
      self::backUrl();
      return;
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    // IN CASE ITS A DERIVATION ORIGINAL MUST BE REMOVED ALSO
    if ($this->getComponentStem()->hasStatus === VSTOI::CURRENT || $this->getComponentStem()->hasVersion > 1) {
      unset($derivations[Constant::DEFAULT_WAS_GENERATED_BY]);
    }

    $languages = ['' => $this->t('Select one please')] + $languages;
    $derivations = ['' => $this->t('Select one please')] + $derivations;

    $form['componentstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getComponentStemUri()).'">'.$this->getComponentStemUri().'</a>'),
    ];

    // dpm($this->getComponentStem());

    $form['componentstem_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Parent Type'),
        '#name' => 'componentstem_type',
        '#default_value' => $this->getComponentStem()->superUri ? Utils::fieldToAutocomplete($this->getComponentStem()->superUri, $this->getComponentStem()->superClassLabel) : '',
        '#id' => 'componentstem_type',
        '#parents' => ['componentstem_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'componentstem',
          ], ['query' => ['field_id' => 'componentstem_type']])->toString(),
          'data-field-id' => 'componentstem_type',
          'data-elementtype' => 'componentstem',
          'data-search-value' => $this->getComponentStem()->superUri ?? '',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['componentstem_type']['main'] += [
      '#maxlength' => 999,
    ];

    $form['componentstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getComponentStem()->hasContent,
    ];
    $form['componentstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getComponentStem()->hasLanguage,
      '#attributes' => [
        'id' => 'componentstem_language'
      ]
    ];
    $form['componentstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => isset($this->getComponentStem()->hasVersion) && $this->getComponentStem()->hasVersion !== null
        ? (
            ($this->getComponentStem()->hasStatus === VSTOI::CURRENT || $this->getComponentStem()->hasStatus === VSTOI::DEPRECATED)
              ? $this->getComponentStem()->hasVersion + 1
              : $this->getComponentStem()->hasVersion
          )
        : 1,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['componentstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getComponentStem()->comment,
    ];

    if ($this->getComponentStem()->wasDerivedFrom !== NULL) {
      $form['componentstem_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      if ($this->getComponentStem()->wasDerivedFrom !== NULL) {
        $form['componentstem_df_wrapper']['componentstem_wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => $this->getComponentStem()->wasDerivedFrom,
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 0;",
            'disabled' => 'disabled',
          ],
        ];
      }

      $elementUri = Utils::namespaceUri($this->getComponentStem()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['componentstem_df_wrapper']['componentstem_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['componentstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $this->getComponentStem()->wasGeneratedBy,
      '#attributes' => [
        'id' => 'componentstem_was_generated_by'
      ],
      '#disabled' => ($this->getComponentStem()->wasGeneratedBy === Constant::WGB_ORIGINAL ? true:false)
    ];
    if ($this->getComponentStem()->hasReviewNote !== NULL && $this->getComponentStem()->hasStatus !== null) {
      $form['componentstem_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getComponentStem()->hasReviewNote,
        '#disabled' => TRUE
      ];
      $form['componentstem_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => \Drupal::currentUser()->getEmail(),
        '#attributes' => [
          'disabled' => 'disabled',
        ],
      ];
    }

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current componentstem and its image.
    $componentstem = $this->getComponentStem();
    $componentstem_uri = Utils::namespaceUri($this->getComponentStemUri());
    $componentstem_image = $componentstem->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($componentstem_image) && stripos(trim($componentstem_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($componentstem_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($componentstem_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $componentstem_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['componentstem_information']['componentstem_image_type'] = [
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
    $form['componentstem_information']['componentstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $componentstem_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="componentstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['componentstem_information']['componentstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="componentstem_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($componentstem_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $componentstem_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['componentstem_information']['componentstem_image_upload_wrapper']['componentstem_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Allowed file extensions.
        'file_validate_size' => [2097152], // Maximum file size (in bytes).
      ],
      // Description in red: allowed file types and a warning that choosing a new image will remove the previous one.
      '#description' => Markup::create('<span style="color: red;">Allowed file types: png, jpg, jpeg. Selecting a new image will remove the previous one.</span>'),
    ];

    // **** WEBDOCUMENT ****
    // Retrieve the current web document value.
    $componentstem_webdocument = $componentstem->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($componentstem_webdocument) && stripos(trim($componentstem_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($componentstem_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['componentstem_information']['componentstem_webdocument_type'] = [
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
    $form['componentstem_information']['componentstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $componentstem_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="componentstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['componentstem_information']['componentstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="componentstem_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($componentstem_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $componentstem_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['componentstem_information']['componentstem_webdocument_upload_wrapper']['componentstem_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
        'file_validate_size' => [2097152], // Maximum file size (in bytes).
      ],
      // Description in red: allowed file types and a warning that choosing a new image will remove the previous one.
      '#description' => Markup::create('<span style="color: red;">Allowed file types: pdf, doc, docx, txt, xls, xlsx. Selecting a new document will remove the previous one.</span>'),
    ];

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
      if(strlen($form_state->getValue('componentstem_content')) < 1) {
        $form_state->setErrorByName('componentstem_content', $this->t('Please enter a valid Name'));
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
      if ($this->getComponentStem()->hasStatus === VSTOI::CURRENT || $this->getComponentStem()->hasStatus === VSTOI::DEPRECATED) {

        $componentStemJson = '{"uri":"'.Utils::uriGen('componentstem').'",'.
          '"superUri":"'.Utils::uriFromAutocomplete($this->getComponentStem()->superUri).'",'.
          '"label":"'.$form_state->getValue('componentstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('componentstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('componentstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('componentstem_version').'",'.
          '"comment":"'.$form_state->getValue('componentstem_description').'",'.
          '"wasDerivedFrom":"'.$this->getComponentStem()->uri.'",'. //Previous Version is the New Derivation Value
          '"wasGeneratedBy":"'.$form_state->getValue('componentstem_was_generated_by').'",'.
          '"hasWebDocument":"",'.
          '"hasImageUri":"",' .
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->componentStemAdd($componentStemJson);
        \Drupal::messenger()->addMessage(t("New Version Component Stem has been created successfully."));

      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('componentstem_webdocument_type');
        $componentstem_webdocument = $this->getComponentStem()->hasWebDocument;

        // If user selected URL, use the textfield value.
        if ($doc_type === 'url') {
          $componentstem_webdocument = $form_state->getValue('componentstem_webdocument_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($doc_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('componentstem_webdocument_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'componentstem', 1);
              // Now get the filename from the file entity.
              $componentstem_webdocument = $file->getFilename();
            }
          }
        }

        // Determine the chosen image type.
        $image_type = $form_state->getValue('componentstem_image_type');
        $componentstem_image = $this->getComponentStem()->hasImageUri;

        // If user selected URL, use the textfield value.
        if ($image_type === 'url') {
          $componentstem_image = $form_state->getValue('componentstem_image_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($image_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('componentstem_image_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'componentstem', 1);
              // Now get the filename from the file entity.
              $componentstem_image = $file->getFilename();
            }
          }
        }

        $componentStemJson = '{"uri":"'.$this->getComponentStem()->uri.'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('componentstem_type')).'",'.
        '"label":"'.$form_state->getValue('componentstem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::COMPONENT_STEM.'",'.
        '"hasStatus":"'.$this->getComponentStem()->hasStatus.'",'.
        '"hasContent":"'.$form_state->getValue('componentstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('componentstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('componentstem_version').'",'.
        '"comment":"'.$form_state->getValue('componentstem_description').'",'.
        '"hasWebDocument":"' . $componentstem_webdocument . '",' .
        '"hasImageUri":"' . $componentstem_image . '",' .
        '"wasDerivedFrom":"'.$this->getComponentStem()->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('componentstem_was_generated_by').'",'.
        '"hasReviewNote":"'.($this->getComponentStem()->hasStatus !== null ? $this->getComponentStem()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getComponentStem()->hasStatus !== null ? $this->getComponentStem()->hasEditorEmail : '').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->componentStemDel($this->getComponentStemUri());
        $api->componentStemAdd($componentStemJson);

        // UPLOAD IMAGE TO API
        if ($image_type === 'upload' && $componentstem_image !== $this->getComponentStem()->hasImageUri) {
          $fids = $form_state->getValue('componentstem_image_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getComponentStemUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
          }
        }

        // UPLOAD DOCUMENT TO API
        if ($doc_type === 'upload' && $componentstem_webdocument !== $this->getComponentStem()->hasWebDocument) {
          $fids = $form_state->getValue('componentstem_webdocument_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getComponentStemUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded WebDocument FAILED to be submited to API."));
          }
        }

        \Drupal::messenger()->addMessage(t("Component Stem has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Component Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveComponentStem($componentStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($componentStemUri);
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
