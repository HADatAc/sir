<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;

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
      #dpm($this->getCodebook());
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
        'class' => ['btn', 'btn-success', 'aprove-button'],
      ],
    ];
    $form['review_reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#name' => 'review_reject',
      '#attributes' => [
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
          '"hasReviewNote": "'. $form_state->getValue('codebook_hasreviewnote') .'",'.
          '"hasEditorEmail": "'. $useremail .'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->codebookDel($result->uri);
        $api->codebookAdd($codebookJSON);

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
            '"hasEditorEmail": "'. $resultParent->hasEditorEmail .'"'.
          '}';

          // UPDATE BY DELETING AND CREATING
          $api->codebookDel($resultParent->uri);
          $api->codebookAdd($parentCodeBookJSON);

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
          '"hasEditorEmail": "'. $useremail .'"'.
        '}';

        $api = \Drupal::service('rep.api_connector');
        $api->codebookDel($result->uri);
        $api->codebookAdd($codebookJSON);

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
