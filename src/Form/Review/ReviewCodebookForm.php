<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class ReviewCodebookForm extends FormBase {

  protected $codebookUri;

  protected $codebook;

  public function getCodebookUri() {
    return $this->codebookUri;
  }

  public function setCodebookUri($uri) {
    return $this->codebookUri = $uri;
  }

  public function getCodebook() {
    return $this->codebook;
  }

  public function setCodebook($cb) {
    return $this->codebook = $cb;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_codebook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebookuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    $uri=$codebookuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setCodebookUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getCodebookUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setCodebook($obj->body);
      // dpm($this->getCodebook());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Codebook."));
      self::backUrl();
      return;
    }

    $form['information'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-publication',
    ];

    $form['codebook_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Codebook Form'),
      '#group' => 'information',
    ];

    $form['codebook_information']['codebook_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a class="pt-3" target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getCodebookUri()).'">'.$this->getCodebookUri().'</a>'),
    ];

    $form['codebook_information']['codebook_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getCodebook()->label,
      '#disabled' => TRUE,
    ];
    $form['codebook_information']['codebook_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getCodebook()->hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['codebook_information']['codebook_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getCodebook()->hasVersion,
      '#disabled' => TRUE,
    ];
    $form['codebook_information']['codebook_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getCodebook()->comment,
      '#disabled' => TRUE,
    ];

    if ($this->getCodebook()->wasDerivedFrom !== null && $this->getCodebook()->wasDerivedFrom !== '') {

      // Campo de texto desativado que ocupa todo o espaço disponível
      $form['codebook_information']['codebook_wasderivedfrom_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'gap-2'], // Flexbox para alinhar na mesma linha
          'style' => 'width: 100%;',
        ],
      ];

      // Campo de texto
      $form['codebook_information']['codebook_wasderivedfrom_wrapper']['codebook_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getCodebook()->wasDerivedFrom,
        '#attributes' => [
          'class' => ['flex-grow-1'], // Expande ao máximo dentro do flex container
          'style' => "min-width: 0;", // Evita problemas de responsividade
          'disabled' => 'disabled',
        ],
      ];

      // Construção da URL
      $elementUri = Utils::namespaceUri($this->getCodebook()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      // Botão para abrir nova janela
      $form['codebook_information']['codebook_wasderivedfrom_wrapper']['codebook_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];

    }

    $form['codebook_information']['codebook_hasSIRManagerEmail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getCodebook()->hasSIRManagerEmail,
      '#disabled' => TRUE,
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current codebook and its image.
    $codebook = $this->getCodebook();
    $codebook_uri = Utils::namespaceUri($this->getCodebookUri());
    $codebook_image = $codebook->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($codebook_image) && stripos(trim($codebook_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($codebook_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($codebook_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $codebook_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['codebook_information']['codebook_information']['codebook_image_type'] = [
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
    $form['codebook_information']['codebook_information']['codebook_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $codebook_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="codebook_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['codebook_information']['codebook_information']['codebook_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="codebook_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($codebook_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $codebook_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['codebook_information']['codebook_information']['codebook_image_upload_wrapper']['codebook_image_upload'] = [
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
    $codebook_webdocument = $codebook->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($codebook_webdocument) && stripos(trim($codebook_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($codebook_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['codebook_information']['codebook_information']['codebook_webdocument_type'] = [
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
    $form['codebook_information']['codebook_information']['codebook_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $codebook_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="codebook_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['codebook_information']['codebook_information']['codebook_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="codebook_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($codebook_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $codebook_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['codebook_information']['codebook_information']['codebook_webdocument_upload_wrapper']['codebook_webdocument_upload'] = [
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

    // RESPONSE OPTIONS TAB

    $form['responseoption_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Response Options'),
      '#group' => 'information',
    ];

    /*****************************/
    /* RETRIEVE RESPONSE OPTIONS */
    /*****************************/
    $slot_list = $api->codebookSlotList($this->getCodebook()->uri);
    $obj = json_decode($slot_list);
    $slots = [];
    if ($obj->isSuccessful) {
      $slots = $obj->body;
    }

    # BUILD HEADER

    $header = [
      'slot_priority' => t('Priority'),
      'slot_content' => t("Response Option's Content"),
      'slot_response_option' => t("Response Option's URI"),
      'slot_response_status' => t("Status"),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($slots as $slot) {
      $content = "";
      if ($slot->hasResponseOption != null) {
        $rawresponseoption = $api->getUri($slot->hasResponseOption);
        $objresponseoption = json_decode($rawresponseoption);
        if ($objresponseoption->isSuccessful) {
          $responseoption = $objresponseoption->body;
          if (isset($responseoption->hasContent)) {
            $content = $responseoption->hasContent;
          }
        }
      }
      $responseOptionUriStr = "";
      if ($slot->hasResponseOption != NULL && $slot->hasResponseOption != '') {
        $responseOptionUriStr = Utils::namespaceUri($slot->hasResponseOption);
      }
      $output[$slot->uri] = [
        'slot_priority' => $slot->hasPriority,
        'slot_content' => $content,
        'slot_response_option' => $responseOptionUriStr,
        'slot_response_status' => parse_url($slot->responseOption->hasStatus, PHP_URL_FRAGMENT),
        '#disabled' => TRUE
      ];
    }

    # PUT FORM TOGETHER

    $form['responseoption_information']['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Response Option Slots</h4>'),
      '#attributes' => [
        'class' => ['mt-5 mb-1'],
      ],
    ];

    $form['responseoption_information']['slot_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No response option slots found'),
    ];

    // REVIEW NOTES TAB
    $form['codebook_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getCodebook()->hasReviewNote,
    ];

    $form['codebook_haseditoremail'] = [
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
      '#title' => t('<br><br>'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
    ];


    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('codebook_name')) < 1) {
        $form_state->setErrorByName('codebook_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('codebook_language')) < 1) {
        $form_state->setErrorByName('codebook_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('codebook_version')) < 1) {
        $form_state->setErrorByName('codebook_version', $this->t('Please enter a valid version'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    //REJECT? MOTIVE BLANK?
    if ($button_name === 'review_reject' && strlen($form_state->getValue('codebook_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getCodebook();

      //APROVE
      if ($button_name !== 'review_reject') {

        //MAIN BODY CODEBOOK
        $codebookJSON = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.VSTOI::CODEBOOK.'",'.
          '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
          '"label":"' . $result->label . '",' .
          '"comment":"'.$result->comment.'",' .
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasLanguage":"'.$result->hasLanguage.'",' .
          '"hasVersion":"'.$result->hasVersion.'",' .
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'",'.
          '"hasReviewNote": "'. $form_state->getValue('codebook_hasreviewnote') .'",'.
          '"hasEditorEmail": "'. $useremail .'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('codebook', $result->uri);
        $api->elementAdd('codebook', $codebookJSON);

        //Change Status OF R.O. to Current, only change if they are Draft and Owned by the Submiter for Review
        //LOOP TO ASSIGN RO TO CB
        $slot_list = $api->codebookSlotList($result->uri);
        $obj = json_decode($slot_list);
        $slots = [];
        if ($obj->isSuccessful) {
          $slots = $obj->body;
        }
        $count = 1;
        foreach ($slots as $slot) {
          //GET RO->URI AND ATTACH TO SLOT
          if ($result->codebookSlots[$count-1]->hasPriority === $slot->hasPriority) {
            $roURI = $result->codebookSlots[$count-1]->responseOption->uri;
          }
          $api->responseOptionAttachStatus($roURI,$slot->uri, VSTOI::CURRENT); //Change to Current the Status if Draft
          $count++;
        }


        //IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED
        if ($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') {

          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          //MAIN BODY PARENT CODEBOOK
          $parentCodeBookJSON = '{'.
            '"uri":"'.$resultParent->uri.'",'.
            '"typeUri":"'.VSTOI::CODEBOOK.'",'.
            '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
            '"label":"' . $resultParent->label . '",' .
            '"comment":"'.$resultParent->comment.'",' .
            '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
            '"hasLanguage":"'.$resultParent->hasLanguage.'",' .
            '"hasVersion":"'.$resultParent->hasVersion.'",' .
            '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",'.
            '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'",'.
            '"hasReviewNote": "'. $resultParent->hasReviewNote .'",'.
            '"hasEditorEmail": "'. $resultParent->hasEditorEmail .'",'.
            '"hasImageUri":"'.$resultParent->hasImageUri.'",'.
            '"hasWebDocument":"'.$resultParent->hasWebDocument.'"'.
          '}';

          // UPDATE BY DELETING AND CREATING
          $api->elementDel('codebook', $resultParent->uri);
          $api->elementAdd('codebook', $parentCodeBookJSON);

        }

        \Drupal::messenger()->addMessage(t("Codebook has been APPROVED successfully."));

      // REJECT
      } else {

        //MAIN BODY CODEBOOK
        $codebookJSON = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.VSTOI::CODEBOOK.'",'.
          '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
          '"label":"' . $result->label . '",' .
          '"comment":"'.$result->comment.'",' .
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasLanguage":"'.$result->hasLanguage.'",' .
          '"hasVersion":"'.$result->hasVersion.'",' .
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"hasReviewNote": "'. $form_state->getValue('codebook_hasreviewnote') .'",'.
          '"hasEditorEmail": "'. $useremail .'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'"'.
        '}';

        $api = \Drupal::service('rep.api_connector');
        $api->elementDel('codebook', $result->uri);
        $api->elementAdd('codebook', $codebookJSON);

        \Drupal::messenger()->addWarning(t("Codebook has been REJECTED."));

      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating Codebook: ".$e->getMessage()));
      self::backUrl();
      return;
    }
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
