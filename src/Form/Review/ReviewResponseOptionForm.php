<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\sir\Entity\ResponseOption;
use Drupal\rep\Vocabulary\REPGUI;

class ReviewResponseOptionForm extends FormBase {

  protected $responseOptionUri;

  protected $responseOption;

  public function getResponseOptionUri() {
    return $this->responseOptionUri;
  }

  public function setResponseOptionUri($uri) {
    return $this->responseOptionUri = $uri;
  }

  public function getResponseOption() {
    return $this->responseOption;
  }

  public function setResponseOption($respOption) {
    return $this->responseOption = $respOption;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_responseoption_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $responseoptionuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    $uri=$responseoptionuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setResponseOptionUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getResponseOptionUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setResponseOption($obj->body);
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Response Option."));
      self::backUrl();
      return;
    }

    $form['responseoption_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ],
    ];

    $form['responseoption_wrapper']['responseoption_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getResponseOptionUri()).'">'.$this->getResponseOptionUri().'</a>'),
    ];

    $form['responseoption_wrapper']['responseoption_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getResponseOption()->hasContent,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_wrapper']['responseoption_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getResponseOption()->hasLanguage,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_wrapper']['responseoption_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getResponseOption()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_wrapper']['responseoption_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getResponseOption()->comment,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    if ($this->getResponseOption()->wasDerivedFrom !== NULL) {
      $form['responseoption_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      // Campo de texto desativado que ocupa todo o espaço disponível
      $form['responseoption_df_wrapper']['responseoption_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getResponseOption()->wasDerivedFrom,
        '#attributes' => [
          'class' => ['flex-grow-1'], // Expande ao máximo dentro do flex container
          'style' => "width: 100%; min-width: 0;", // Corrige problemas de flexbox em alguns browsers
          'disabled' => 'disabled',
        ],
      ];

      // Construção da URL
      $elementUri = Utils::namespaceUri($this->getResponseOption()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      // Botão para abrir nova janela - agora corrigido
      $form['responseoption_df_wrapper']['responseoption_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }

    $form['responseoption_wrapper']['responseoption_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getResponseOption()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current responseoption and its image.
    $responseoption = $this->getResponseOption();
    $responseoption_uri = Utils::namespaceUri($this->getResponseOptionUri());
    $responseoption_image = $responseoption->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($responseoption_image) && stripos(trim($responseoption_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($responseoption_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($responseoption_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $responseoption_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['responseoption_wrapper']['responseoption_information']['responseoption_image_type'] = [
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
    $form['responseoption_wrapper']['responseoption_information']['responseoption_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $responseoption_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="responseoption_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['responseoption_wrapper']['responseoption_information']['responseoption_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="responseoption_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($responseoption_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $responseoption_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['responseoption_wrapper']['responseoption_information']['responseoption_image_upload_wrapper']['responseoption_image_upload'] = [
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
    $responseoption_webdocument = $responseoption->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($responseoption_webdocument) && stripos(trim($responseoption_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($responseoption_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['responseoption_wrapper']['responseoption_information']['responseoption_webdocument_type'] = [
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
    $form['responseoption_wrapper']['responseoption_information']['responseoption_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $responseoption_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="responseoption_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['responseoption_wrapper']['responseoption_information']['responseoption_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="responseoption_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($responseoption_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $responseoption_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['responseoption_wrapper']['responseoption_information']['responseoption_webdocument_upload_wrapper']['responseoption_webdocument_upload'] = [
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

    $form['responseoption_wrapper']['responseoption_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getResponseOption()->hasReviewNote,
    ];
    $form['responseoption_wrapper']['responseoption_haseditoremail'] = [
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
      if(strlen($form_state->getValue('responseoption_content')) < 1) {
        $form_state->setErrorByName('responseoption_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('responseoption_language')) < 1) {
        $form_state->setErrorByName('responseoption_language', $this->t('Please enter a valid language'));
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('responseoption_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    try{
      $api = \Drupal::service('rep.api_connector');
      // APPROVE
      if ($button_name === 'review_approve') {

        // IF wasDerivedFrom not NULL
        if ($this->getResponseOption()->wasDerivedFrom !== NULL) {

          // PARENT TO DEPRECATED
          ResponseOption::cloneResponseOption($this->getResponseOption()->wasDerivedFrom, VSTOI::DEPRECATED);

          // THIS TO CURRENT
          ResponseOption::cloneResponseOption($this->getResponseOption()->uri, VSTOI::CURRENT);

        } else {

          // THIS TO CURRENT
          ResponseOption::cloneResponseOption($this->getResponseOption()->uri, VSTOI::CURRENT);

        }

        \Drupal::messenger()->addMessage(t("Response Option has been Approved."));
        self::backUrl();
        return;

      } else {

      // REJECT
        // RETURN HAS DRAFT WITH NOTES
        ResponseOption::cloneResponseOption($this->getResponseOption()->uri, VSTOI::DRAFT, $form_state->getValue('responseoption_hasreviewnote'), \Drupal::currentUser()->getEmail());

        \Drupal::messenger()->addMessage(t("Response Option has been Rejected."));
        self::backUrl();
        return;

      }


    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while approving/rejecting the Response Option: ".$e->getMessage()));
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
