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

class AddComponentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_component_form';
  }

  protected $sourceComponentUri;

  protected $sourceComponent;

  protected $componentStem;

  protected $containerslotUri;

  protected $containerslot;

  protected $componentUri;

  public function setComponentUri() {
    $this->componentUri = Utils::uriGen('component');
  }

  public function getComponentUri() {
    return $this->componentUri;
  }

  public function getSourceComponentUri() {
    return $this->sourceComponentUri;
  }

  public function setSourceComponentUri($uri) {
    return $this->sourceComponentUri = $uri;
  }

  public function getSourceComponent() {
    return $this->sourceComponent;
  }

  public function setSourceComponent($obj) {
    return $this->sourceComponent = $obj;
  }

  public function getComponentStem() {
    return $this->componentStem;
  }

  public function setComponentStem($stem) {
    return $this->componentStem = $stem;
  }

  public function getContainerSlotUri() {
    return $this->containerslotUri;
  }

  public function setContainerSlotUri($attachuri) {
    return $this->containerslotUri = $attachuri;
  }

  public function getContainerSlot() {
    return $this->containerslot;
  }

  public function setContainerSlot($attachobj) {
    return $this->containerslot = $attachobj;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourcecomponenturi = NULL, $containersloturi = NULL) {

    // Does the repo have a social network?
    $socialEnabled = \Drupal::config('rep.settings')->get('social_conf');

    // Check if the component URI already exists in the form state.
    // If not, generate a new URI and store it in the form state.
    if (!$form_state->has('component_uri')) {
      $this->setComponentUri();
      $form_state->set('component_uri', $this->getComponentUri());
    }
    else {
      // Retrieve the persisted URI from form state.
      $this->componentUri = $form_state->get('component_uri');
    }

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE COMPONENT,  IF ANY
    $sourceuri=$sourcecomponenturi;
    if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
      $this->setSourceComponent(NULL);
      $this->setSourceComponentUri('');
    } else {
      $sourceuri_decode=base64_decode($sourceuri);
      $this->setSourceComponentUri($sourceuri_decode);
      $rawresponse = $api->getUri($this->getSourceComponentUri());
      //dpm($rawresponse);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setSourceComponent($obj->body);
        //dpm($this->getComponent());
      } else {
        $this->setSourceComponent(NULL);
        $this->setSourceComponentUri('');
      }
    }
    $disabledDerivationOption = ($this->getSourceComponent() === NULL);

    // HANDLE CONTAINER_SLOT, IF ANY
    $attachuri=$containersloturi;
    if ($attachuri === NULL || $attachuri === 'EMPTY') {
      $this->setContainerSlot(NULL);
      $this->setContainerSlotUri('');
    } else {
      $attachuri_decode=base64_decode($attachuri);
      $this->setContainerSlotUri($attachuri_decode);
      if ($this->getContainerSlotUri() != NULL) {
        $attachrawresponse = $api->getUri($this->getContainerSlotUri());
        $attachobj = json_decode($attachrawresponse);
        if ($attachobj->isSuccessful) {
          $this->setContainerSlot($attachobj->body);
        }
      }
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    if ($this->getSourceComponent() != NULL) {
      $sourceContent = $this->getSourceComponent()->hasContent;
    }

    // $form['component_stem'] = [
    //   '#type' => 'textfield',
    //   '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
    //     $this->t('Simulation Technique Stem') :
    //     $this->t('Component Stem'),
    //   '#autocomplete_route_name' => 'sir.component_stem_autocomplete',
    // ];

    $form['component_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Component Stem'),
        '#name' => 'component_stem',
        '#default_value' => '',
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
      '#autocomplete_route_name' => 'sir.component_codebook_autocomplete',
    ];
    if ($socialEnabled) {
      $form['component_maker'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Maker'),
        // '#required' => TRUE,
        '#autocomplete_route_name'       => 'rep.social_autocomplete',
        '#autocomplete_route_parameters' => [
          'entityType' => 'organization',
        ],
      ];
    }
    $form['component_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['component_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['component_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'component_isAttributeOf',
        '#default_value' => '',
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

    // Add a hidden field to persist the component URI between form rebuilds.
    $form['component_uri'] = [
      '#type' => 'hidden',
      '#value' => $this->componentUri,
    ];

    // Add a select box to choose between URL and Upload.
    $form['component_image_type'] = [
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
    $form['component_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="component_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted component URI for file uploads)
    $modUri = (explode(":/", utils::namespaceUri($this->componentUri)))[1];
    $form['component_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="component_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['component_image_upload_wrapper']['component_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
    ];

    // Add a select box to choose between URL and Upload.
    $form['component_webdocument_type'] = [
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
    $form['component_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="component_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted component URI for file uploads)
    $form['component_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="component_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['component_webdocument_upload_wrapper']['component_webdocument_upload'] = [
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

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    if ($button_name != 'back') {

      if ($form_state->getValue('component_stem') == NULL || $form_state->getValue('component_stem') == '') {
        $form_state->setErrorByName('component_stem', $this->t('Component stem value is empty. Please enter a valid stem.'));
      }

      // if (strlen($form_state->getValue('component_stem')) > 128) {
      //   $form_state->setValue('component_stem', Utils::trimPreserveBracket($form_state->getValue('component_stem'), 127));
      // }
      // $stemUri = Utils::uriFromAutocomplete($form_state->getValue('component_stem'));
      // $this->setComponentStem($api->parseObjectResponse($api->getUri($stemUri),'getUri'));
      // if($this->getComponentStem() == NULL) {
      //   $form_state->setErrorByName('component_stem', $this->t('Value for Component Stem is not valid. Please enter a valid stem.'));
      // }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try {

      $hasCodebook = '';
      if ($form_state->getValue('component_codebook') !== NULL && $form_state->getValue('component_codebook') !== '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('component_codebook'));
      } else {
        $hasCodebook = NULL;
      }

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

      // Get the current user email and generate a new component URI.
      $useremail = \Drupal::currentUser()->getEmail();
      // $newInstrumentUri = Utils::uriGen('component');
      $newComponentUri = $form_state->getValue('component_uri');

      // Determine the chosen document type.
      $doc_type = $form_state->getValue('component_webdocument_type');
      $component_webdocument = '';

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
      $component_image = '';

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

      // CREATE A NEW COMPONENT
      $componentJson = '{"uri":"'.$newComponentUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('component_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::COMPONENT.'",'.
        '"hasComponentStem":"'.Utils::uriFromAutocomplete($form_state->getValue('component_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasVersion":"1",'.
        '"isAttributeOf":"'.Utils::uriFromAutocomplete($form_state->getValue('component_isAttributeOf')).'",'.
        '"hasMakerUri":"' . Utils::uriFromAutocomplete($form_state->getValue('component_maker')) . '",' .
        '"hasWebDocument":"' . $component_webdocument . '",' .
        '"hasImageUri":"' . $component_image . '",' .
        '"hasStatus":"'.VSTOI::DRAFT.'"}';

      $api->componentAdd($componentJson);

      // IF IN THE CONTEXT OF AN EXISTING CONTAINER_SLOT, ATTACH THE NEWLY CREATED COMPONENT TO THE CONTAINER_SLOT
      if ($this->getContainerSlot() != NULL) {
        $api->componentAttach($newComponentUri,$this->getContainerSlotUri());
        \Drupal::messenger()->addMessage(t("Component [" . $newComponentUri ."] has been added and attached to intrument [" . $this->getContainerSlot()->belongsTo . "] successfully."));
        $url = Url::fromRoute('sir.edit_containerslot');
        $url->setRouteParameter('containersloturi', base64_encode($this->getContainerSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      } else {
        // UPLOAD IMAGE TO API
        if ($image_type === 'upload') {
          $fids = $form_state->getValue('component_image_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($newComponentUri, reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
          }
        }
        // UPLOAD DOCUMENT TO API
        if ($doc_type === 'upload') {
          $fids = $form_state->getValue('component_webdocument_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($newComponentUri, reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded Document FAILED to be submited to API."));
          }
        }

        \Drupal::messenger()->addMessage(t("Component has been added successfully."));
        self::backUrl();
        return;
      }
    } catch(\Exception $e) {
      if ($this->getContainerSlot() != NULL) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Component: ".$e->getMessage()));
        $url = Url::fromRoute('sir.edit_containerslot');
        $url->setRouteParameter('containersloturi', base64_encode($this->getContainerSlotUri()));
        $form_state->setRedirectUrl($url);
      } else {
        \Drupal::messenger()->addError(t("An error occurred while adding the Component: ".$e->getMessage()));
        self::backUrl();
        return;
      }
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_component');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
