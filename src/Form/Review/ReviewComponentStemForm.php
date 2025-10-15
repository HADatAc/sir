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

class ReviewComponentStemForm extends FormBase {

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
    return 'review_componentstem_form';
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

    $uri=$componentstemuri;
    $uri_decode=base64_decode($uri);
    $this->setComponentStemUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setComponentStem($this->retrieveComponentStem($this->getComponentStemUri()));
    if ($this->getComponentStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Component Stem."));
      self::backUrl();
      return;
    } else {
      $wasGeneratedBy = $this->getComponentStem()->wasGeneratedBy;
      if ($this->getComponentStem()->wasDerivedFrom != NULL) {
        $this->setSourceComponentStem($this->retrieveComponentStem($this->getComponentStem()->wasDerivedFrom));
        if ($this->getSourceComponentStem() != NULL && $this->getSourceComponentStem()->hasContent != NULL) {
          $sourceContent = Utils::fieldToAutocomplete($this->getSourceComponentStem()->uri,$this->getSourceComponentStem()->hasContent);
        }
      }
    }

    //dpm($this->getComponent());

    $form['componentstem_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ],
    ];

    $form['componentstem_wrapper']['componentstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getComponentStemUri()).'">'.$this->getComponentStemUri().'</a>'),
    ];

    if ($this->getComponentStem()->superUri) {
      $form['componentstem_wrapper']['componentstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Type'),
          '#name' => 'componentstem_type',
          '#default_value' => $this->getComponentStem()->superUri ? Utils::fieldToAutocomplete($this->getComponentStem()->superUri, $this->getComponentStem()->superClassLabel) : '',
          '#disabled' => TRUE,
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
    }
    $form['componentstem_wrapper']['componentstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getComponentStem()->hasContent,
      '#disabled' => TRUE,
    ];
    $form['componentstem_wrapper']['componentstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getComponentStem()->hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['componentstem_wrapper']['componentstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getComponentStem()->hasVersion,
      '#default_value' =>
        ($this->getComponentStem()->hasStatus === VSTOI::CURRENT || $this->getComponentStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getComponentStem()->hasVersion + 1 : $this->getComponentStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['componentstem_wrapper']['componentstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getComponentStem()->comment,
      '#disabled' => TRUE,
    ];

    if ($this->getComponentStem()->wasDerivedFrom !== NULL) {
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getComponentStem()->wasDerivedFrom);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $result = $obj->body;

        $form['componentstem_wrapper']['componentstem__df_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
            'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
          ],
        ];

        $form['componentstem_wrapper']['componentstem__df_wrapper']['componentstem__wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => Utils::fieldToAutocomplete($this->getComponentStem()->wasDerivedFrom, $result->label),
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 1045px;",
            'disabled' => 'disabled',
          ],
        ];

        $elementUri = Utils::namespaceUri($this->getComponentStem()->wasDerivedFrom);
        $elementUriEncoded = base64_encode($elementUri);
        $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

        $form['componentstem__df_wrapper']['componentstem__wasderivedfrom_button'] = [
          '#type' => 'markup',
          '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
        ];
      }
    }

    $form['componentstem_wrapper']['componentstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
      '#disabled' => TRUE,
    ];

    $form['componentstem_wrapper']['componentstem_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getComponentStem()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current component and its image.
    $component = $this->getComponentStem();
    $componentstem_uri = Utils::namespaceUri($this->getComponentStemUri());
    $componentstem_image = $component->hasImageUri ?? '';

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
    $form['componentstem_wrapper']['componentstem_information']['componentstem_image_type'] = [
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
    $form['componentstem_wrapper']['componentstem_information']['componentstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $componentstem_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="componentstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['componentstem_wrapper']['componentstem_information']['componentstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['componentstem_wrapper']['componentstem_information']['componentstem_image_upload_wrapper']['componentstem_image_upload'] = [
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
    $componentstem_webdocument = $component->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($componentstem_webdocument) && stripos(trim($componentstem_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($componentstem_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['componentstem_wrapper']['componentstem_information']['componentstem_webdocument_type'] = [
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
    $form['componentstem_wrapper']['componentstem_information']['componentstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $componentstem_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="componentstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['componentstem_wrapper']['componentstem_information']['componentstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['componentstem_wrapper']['componentstem_information']['componentstem_webdocument_upload_wrapper']['componentstem_webdocument_upload'] = [
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

    $form['componentstem_wrapper']['componentstem_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getComponentStem()->hasReviewNote,
    ];
    $form['componentstem_wrapper']['componentstem_haseditoremail'] = [
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

    // if ($button_name != 'back') {
    //   if ($button_name === 'review_reject') {
    //     if(strlen($form_state->getValue('componentstem_hasreviewnote')) < 1) {
    //       $form_state->setErrorByName('componentstem_hasreviewnote', $this->t('You must enter a Reject Note'));
    //     }
    //   }
    // }
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('componentstem_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getComponentStem();

      //APROVE
      if ($button_name !== 'review_reject') {

        $componentStemJson = '{"uri":"'.$this->getComponentStem()->uri.'",'.
          '"superUri":"'.$this->getComponentStem()->superUri.'",'.
          '"label":"'.$this->getComponentStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT_STEM.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasContent":"'.$this->getComponentStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getComponentStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getComponentStem()->hasVersion.'",'.
          '"comment":"'.$this->getComponentStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getComponentStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getComponentStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('componentstem_hasreviewnote').'",'.
          '"hasImageUri":"'.$this->getComponentStem()->hasImageUri.'",'.
          '"hasWebDocument":"'.$this->getComponentStem()->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getComponentStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('componentstem', $this->getComponentStemUri());
        $api->elementAdd('componentstem', $componentStemJson);

        // IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED, but in this case version must be also greater than 1, because
        // Component Stems can start to be like a derivation element by itself
        if (($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') && $result->hasVersion > 1) {
          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentComponentStemJson = '{"uri":"'.$resultParent->uri.'",'.
          (!empty($resultParent->superUri) ? '"superUri":"'.$resultParent->superUri.'",' : '').
          '"label":"'.$resultParent->label.'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT_STEM.'",'.
          '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
          '"hasContent":"'.$resultParent->hasContent.'",'.
          '"hasLanguage":"'.$resultParent->hasLanguage.'",'.
          '"hasVersion":"'.$resultParent->hasVersion.'",'.
          '"comment":"'.$resultParent->comment.'",'.
          (!empty($resultParent->wasDerivedFrom) ? '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",' : '').
          '"wasGeneratedBy":"'.$resultParent->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
          '"hasImageUri":"'.$resultParent->hasImageUri.'",'.
          '"hasWebDocument":"'.$resultParent->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'"}';

          // UPDATE BY DELETING AND CREATING
          $api->elementDel('componentstem', $resultParent->uri);
          $api->elementAdd('componentstem', $parentComponentStemJson);
        }

        \Drupal::messenger()->addMessage(t("Component Stem has been updated successfully."));
      // REJECT
      } else {

        $componentStemJson = '{"uri":"'.$this->getComponentStem()->uri.'",'.
          '"superUri":"'.$this->getComponentStem()->superUri.'",'.
          '"label":"'.$this->getComponentStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$this->getComponentStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getComponentStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getComponentStem()->hasVersion.'",'.
          '"comment":"'.$this->getComponentStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getComponentStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getComponentStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('componentstem_hasreviewnote').'",'.
          '"hasImageUri":"'.$this->getComponentStem()->hasImageUri.'",'.
          '"hasWebDocument":"'.$this->getComponentStem()->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getComponentStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('componentstem', $this->getComponentStemUri());
        $api->elementAdd('componentstem', $componentStemJson);
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
