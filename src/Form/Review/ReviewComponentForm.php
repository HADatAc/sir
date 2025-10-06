<?php

namespace Drupal\sir\Form\Review;

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

class ReviewComponentForm extends FormBase {

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
    return 'review_component_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $componenturi = NULL) {

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

    //dpm($this->getComponent());

    $form['component_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ],
    ];

    $form['component_wrapper']['component_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getComponentUri()).'">'.$this->getComponentUri().'</a>'),
    ];

    $form['component_wrapper']['component_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="col border border-white">',
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
      '#disabled' => TRUE
    ];
    $form['component_wrapper']['component_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.component_codebook_autocomplete',
      '#disabled' => TRUE
    ];
    $form['component_wrapper']['component_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getComponent()->hasStatus === VSTOI::CURRENT || $this->getComponent()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getComponent()->hasVersion + 1 : $this->getComponent()->hasVersion,
      '#disabled' => TRUE
    ];
    $form['component_wrapper']['component_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'component_isAttributeOf',
        '#default_value' => $this->getComponent()->isAttributeOf,
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
      '#disabled' => TRUE
    ];
    if ($this->getComponent()->wasDerivedFrom !== NULL) {
      $form['component_wrapper']['component_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'],
          'style' => "width: 100%; gap: 10px;",
        ],
      ];

      $form['component_wrapper']['component_df_wrapper']['component_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getComponent()->wasDerivedFrom,
        '#attributes' => [
          'class' => ['flex-grow-1'],
          'style' => "width: 100%; min-width: 0;",
          'disabled' => 'disabled',
        ],
      ];

      $elementUri = Utils::namespaceUri($this->getComponent()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['component_wrapper']['component_df_wrapper']['component_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['component_wrapper']['component_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getComponent()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
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
    $form['component_wrapper']['component_information']['component_image_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Type'),
      '#options' => [
        '' => $this->t('Select Image Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#disabled' => TRUE,
      '#default_value' => $image_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['component_wrapper']['component_information']['component_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $component_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="component_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['component_wrapper']['component_information']['component_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['component_wrapper']['component_information']['component_image_upload_wrapper']['component_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [2097152],
      ],
      '#disabled' => TRUE,
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_image_fid ? [$existing_image_fid] : NULL,
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
    $form['component_wrapper']['component_information']['component_webdocument_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Web Document Type'),
      '#options' => [
        '' => $this->t('Select Document Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#disabled' => TRUE,
      '#default_value' => $webdocument_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['component_wrapper']['component_information']['component_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $component_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="component_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['component_wrapper']['component_information']['component_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['component_wrapper']['component_information']['component_webdocument_upload_wrapper']['component_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
      ],
      '#disabled' => TRUE,
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_fid ? [$existing_fid] : NULL,
    ];

    $form['component_wrapper']['component_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getComponent()->hasReviewNote,
    ];
    $form['component_wrapper']['component_haseditoremail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reviewer Email'),
      '#default_value' => \Drupal::currentUser()->getEmail(),
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['review_approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Approve'),
      '#name' => 'review_approve',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to Approve?")){return false;}',
        'class' => ['btn', 'btn-success', 'aprove-button'],
      ],
    ];
    $form['review_reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#name' => 'review_reject',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to Reject?")){return false;}',
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br>'),
    ];
    $form['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
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
      // if ($button_name === 'review_reject') {
      //   if(strlen($form_state->getValue('component_hasreviewnote')) < 1) {
      //     $form_state->setErrorByName('component_hasreviewnote', $this->t('You must enter a Reject Note'));
      //   }
      // }
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('component_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getComponent();

      //APROVE
      if ($button_name !== 'review_reject') {

        $componentJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT.'",'.
          '"hasComponentStem":"'.$result->hasComponentStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('component_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('component', $result->uri);
        $api->elementAdd('component', $componentJson);

        //IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED
        if ($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') {

          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentComponentJson = '{'.
            '"uri":"'.$resultParent->uri.'",'.
            '"typeUri":"'.$resultParent->typeUri.'",'.
            '"hascoTypeUri":"'.VSTOI::COMPONENT.'",'.
            '"hasComponentStem":"'.$resultParent->hasComponentStem.'",'.
            '"hasCodebook":"'.$resultParent->hasCodebook.'",'.
            '"hasContent":"'.$resultParent->label.'",'.
            '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'",'.
            '"label":"'.$resultParent->label.'",'.
            '"hasVersion":"'.$resultParent->hasVersion.'",'.
            '"isAttributeOf":"'.$resultParent->isAttributeOf.'",'.
            '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",'.
            '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
            '"hasEditorEmail":"'.$resultParent->hasEditorEmail.'",'.
            '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
            '"hasImageUri":"'.$resultParent->hasImageUri.'",'.
            '"hasWebDocument":"'.$resultParent->hasWebDocument.'"'.
          '}';

          // UPDATE BY DELETING AND CREATING
          $api->elementDel('component', $resultParent->uri);
          $api->elementAdd('component', $parentComponentJson);
        }

        \Drupal::messenger()->addMessage(t("Component has been APPROVED successfully."));
          self::backUrl();
          return;

      // REJECT
      } else {

        //MAIN BODY CODEBOOK
        $componentJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT.'",'.
          '"hasComponentStem":"'.$result->hasComponentStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('component_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // \Drupal::messenger()->addWarning($componentJson);
        // return false;

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('component', $result->uri);
        $api->elementAdd('component', $componentJson);

        \Drupal::messenger()->addWarning(t("Component has been REJECTED."));
          self::backUrl();
          return;
      }

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
