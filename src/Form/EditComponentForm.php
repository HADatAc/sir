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

class EditComponentForm extends FormBase {

  protected $componentUri;

  protected $component;

  protected $sourceComponent;

  public function getComponentUri() {
    return $this->componentUri;
  }

  public function setComponentUri($uri) {
    return $this->componentUri = $uri;
  }

  public function getComponent() {
    return $this->component;
  }

  public function setComponent($obj) {
    return $this->component = $obj;
  }

  public function getSourceComponent() {
    return $this->sourceComponent;
  }

  public function setSourceComponent($obj) {
    return $this->sourceComponent = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_component_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $componenturi = NULL) {

    // Does the repo have a social network?
    $socialEnabled = \Drupal::config('rep.settings')->get('social_conf');

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';


    $uri=$componenturi;
    $uri_decode=base64_decode($uri);
    $this->setComponentUri($uri_decode);

    $sourceContent = '';
    $stemLabel = '';
    $codebookLabel = '';
    $this->setComponent($this->retrieveComponent($this->getComponentUri()));
    if ($this->getComponent() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Component."));
      self::backUrl();
      return;
    } else {
      if ($this->getComponent()->componentStem != NULL) {
        $stemLabel = $this->getComponent()->componentStem->hasContent . ' [' . $this->getComponent()->componentStem->uri . ']';
      }
      if ($this->getComponent()->codebook != NULL) {
        $codebookLabel = $this->getComponent()->codebook->label . ' [' . $this->getComponent()->codebook->uri . ']';
      }
    }

    // dpm($this->getComponent());

    $form['component_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getComponentUri()).'">'.$this->getComponentUri().'</a>'),
    ];

    $form['component_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
          $this->t('Simulation Technique Stem') :
          $this->t('Component Stem'),
        '#name' => 'component_stem',
        '#default_value' => Utils::fieldToAutocomplete($this->getComponent()->typeUri, $this->getComponent()->componentStem->label),
        '#id' => 'component_stem',
        '#parents' => ['component_stem'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'componentstem',
          ], ['query' => ['field_id' => 'component_stem']])->toString(),
          'data-field-id' => 'component_stem',
          'data-elementtype' => 'componentstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['component_stem']['main'] += [
      '#maxlength' => 999,
    ];

    $form['component_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.component_codebook_autocomplete',
    ];
    if ($socialEnabled) {
      $api = \Drupal::service('rep.api_connector');
      $makerUri = '';
      if (isset($this->getComponent()->hasMakerUri) && $this->getComponent()->hasMakerUri !== null) {
        $makerUri = $api->parseObjectResponse($api->getUri($this->getComponent()->hasMakerUri), 'getUri');
      }
      $form['component_maker'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Maker'),
        '#default_value' => isset($this->getComponent()->hasMakerUri) ?
                              Utils::fieldToAutocomplete($this->getComponent()->hasMakerUri, $makerUri->label) : '',
        // '#required' => TRUE,
        '#autocomplete_route_name'       => 'rep.social_autocomplete',
        '#autocomplete_route_parameters' => [
          'entityType' => 'organization',
        ],
      ];
    }
    $form['component_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => isset($this->getComponent()->hasVersion) && $this->getComponent()->hasVersion !== null
        ? (
            ($this->getComponent()->hasStatus === VSTOI::CURRENT || $this->getComponent()->hasStatus === VSTOI::DEPRECATED)
              ? $this->getComponent()->hasVersion + 1
              : $this->getComponent()->hasVersion
          )
        : 1,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    if (isset($this->getComponent()->isAttributeOf)) {
      $api = \Drupal::service('rep.api_connector');
      $attributOf = $api->parseObjectResponse($api->getUri($this->getComponent()->isAttributeOf),'getUri');
    }
    $form['component_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'component_isAttributeOf',
        '#default_value' => (isset($attributOf) ? $attributOf->label . ' [' . $this->getComponent()->isAttributeOf . ']' : ''),
        '#id' => 'component_isAttributeOf',
        '#parents' => ['component_isAttributeOf'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'componentattribute',
          ], ['query' => ['field_id' => 'component_isAttributeOf']])->toString(),
          'data-field-id' => 'component_isAttributeOf',
          'data-elementtype' => 'componentattribute',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current component and its image.
    $component = $this->getComponent();
    $component_uri = Utils::namespaceUri($this->getComponentUri());
    $component_image = $component->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($component_image) && stripos(trim($component_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($component_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($component_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $component_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['component_information']['component_image_type'] = [
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
    $form['component_information']['component_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $component_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="component_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['component_information']['component_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="component_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($component_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $component_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['component_information']['component_image_upload_wrapper']['component_image_upload'] = [
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
    $component_webdocument = $component->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($component_webdocument) && stripos(trim($component_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($component_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['component_information']['component_webdocument_type'] = [
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
    $form['component_information']['component_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $component_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="component_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['component_information']['component_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="component_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($component_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $component_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['component_information']['component_webdocument_upload_wrapper']['component_webdocument_upload'] = [
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

    if ($this->getComponent()->hasReviewNote !== NULL && $this->getComponent()->hasSatus !== null) {
      $form['component_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getComponent()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['component_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getComponent()->hasEditorEmail,
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
      if(strlen($form_state->getValue('component_stem')) < 1) {
        $form_state->setErrorByName('component_stem', $this->t('Please enter a valid component stem'));
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

      // GET THE COMPONENT STEM URI
      $rawresponse = $api->getUri(Utils::uriFromAutocomplete($form_state->getValue('component_stem')));
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      $label = "";
      if ($result->hasContent !== NULL) {
        $label .= $result->hasContent;
      } else {
        $label .= $result->label;
      }

      if ($form_state->getValue('component_codebook') !== NULL && $form_state->getValue('component_codebook') != '') {
        $codebook = Utils::uriFromAutocomplete($form_state->getValue('component_codebook'));
        $rawresponseCB = $api->getUri($codebook);
        $objCB = json_decode($rawresponseCB);
        $resultCB = $objCB->body;
        $label .= '  -- CB:'.$resultCB->label;
      } else {
        $label = $result->label . '  -- CB:EMPTY';
      }

      $hasCodebook = '';
      if ($form_state->getValue('component_codebook') != NULL && $form_state->getValue('component_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('component_codebook'));
      }

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getComponent()->hasStatus === VSTOI::CURRENT || $this->getComponent()->hasStatus === VSTOI::DEPRECATED) {

        $newComponentUri = Utils::uriGen('component');
        $componentJson = '{"uri":"'.$newComponentUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('component_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::COMPONENT.'",'.
        '"hasComponentStem":"'.Utils::uriFromAutocomplete($form_state->getValue('component_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasWebDocument":"",'.
        '"hasImageUri":"",' .
        '"hasVersion":"'.$form_state->getValue('component_version').'",'.
        '"isAttributeOf":"'.$form_state->getValue('component_isAttributeOf').'",'.
        '"wasDerivedFrom":"'.$this->getComponent()->uri.'",'.
        '"hasReviewNote":"'.($this->getComponent()->hasSatus !== null ? $this->getComponent()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getComponent()->hasSatus !== null ? $this->getComponent()->hasEditorEmail : '').'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'"}';

        $api->elementAdd('component', $componentJson);
        \Drupal::messenger()->addMessage(t("New Version component has been created successfully."));

      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('component_webdocument_type');
        $component_webdocument = $this->getComponent()->hasWebDocument;

        // If user selected URL, use the textfield value.
        if ($doc_type === 'url') {
          $component_webdocument = $form_state->getValue('component_webdocument_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($doc_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('component_webdocument_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'component', 1);
              // Now get the filename from the file entity.
              $component_webdocument = $file->getFilename();
            }
          }
        }

        // Determine the chosen image type.
        $image_type = $form_state->getValue('component_image_type');
        $component_image = $this->getComponent()->hasImageUri;

        // If user selected URL, use the textfield value.
        if ($image_type === 'url') {
          $component_image = $form_state->getValue('component_image_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($image_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('component_image_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'component', 1);
              // Now get the filename from the file entity.
              $component_image = $file->getFilename();
            }
          }
        }

        $componentJson = '{"uri":"'.$this->getComponent()->uri.'",'.
          '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('component_stem')).'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT.'",'.
          '"hasComponentStem":"'.Utils::uriFromAutocomplete($form_state->getValue('component_stem')).'",'.
          '"hasCodebook":"'.$hasCodebook.'",'.
          '"hasContent":"'.$label.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'",'.
          '"label":"'.$label.'",'.
          '"hasWebDocument":"' . $component_webdocument . '",' .
          '"hasImageUri":"' . $component_image . '",' .
          '"hasVersion":"'.$form_state->getValue('component_version').'",'.
          '"isAttributeOf":"'.$form_state->getValue('component_isAttributeOf').'",'.
          '"wasDerivedFrom":"'.$this->getComponent()->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$this->getComponent()->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$this->getComponent()->hasEditorEmail.'",'.
          '"hasMakerUri":"'.Utils::uriFromAutocomplete($form_state->getValue('component_maker')).'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->elementDel('component', $this->getComponentUri());
        $api->elementAdd('component', $componentJson);

        // UPLOAD IMAGE TO API
        if ($image_type === 'upload' && $component_image !== $this->getComponent()->hasImageUri) {
          $fids = $form_state->getValue('component_image_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getComponentUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
          }
        }

        // UPLOAD DOCUMENT TO API
        if ($doc_type === 'upload' && $component_webdocument !== $this->getComponent()->hasWebDocument) {
          $fids = $form_state->getValue('component_webdocument_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getComponentUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded WebDocument FAILED to be submited to API."));
          }
        }

        \Drupal::messenger()->addMessage(t("Component has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Component: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveComponent($componentUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($componentUri);
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
