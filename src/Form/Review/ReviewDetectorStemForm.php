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

class ReviewDetectorStemForm extends FormBase {

  protected $detectorStemUri;

  protected $detectorStem;

  protected $sourceDetectorStem;

  public function getDetectorStemUri() {
    return $this->detectorStemUri;
  }

  public function setDetectorStemUri($uri) {
    return $this->detectorStemUri = $uri;
  }

  public function getDetectorStem() {
    return $this->detectorStem;
  }

  public function setDetectorStem($obj) {
    return $this->detectorStem = $obj;
  }

  public function getSourceDetectorStem() {
    return $this->sourceDetectorStem;
  }

  public function setSourceDetectorStem($obj) {
    return $this->sourceDetectorStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_detectorstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $detectorstemuri = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $uri=$detectorstemuri;
    $uri_decode=base64_decode($uri);
    $this->setDetectorStemUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setDetectorStem($this->retrieveDetectorStem($this->getDetectorStemUri()));
    if ($this->getDetectorStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Detector Stem."));
      self::backUrl();
      return;
    } else {
      $wasGeneratedBy = $this->getDetectorStem()->wasGeneratedBy;
      if ($this->getDetectorStem()->wasDerivedFrom != NULL) {
        $this->setSourceDetectorStem($this->retrieveDetectorStem($this->getDetectorStem()->wasDerivedFrom));
        if ($this->getSourceDetectorStem() != NULL && $this->getSourceDetectorStem()->hasContent != NULL) {
          $sourceContent = Utils::fieldToAutocomplete($this->getSourceDetectorStem()->uri,$this->getSourceDetectorStem()->hasContent);
        }
      }
    }

    //dpm($this->getDetector());
    $form['detectorstem_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Type'),
        '#name' => 'detectorstem_type',
        '#default_value' => $this->getDetectorStem()->superUri ? Utils::fieldToAutocomplete($this->getDetectorStem()->superUri, $this->getDetectorStem()->superClassLabel) : '',
        '#disabled' => TRUE,
        '#id' => 'detectorstem_type',
        '#parents' => ['detectorstem_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorstem',
          ], ['query' => ['field_id' => 'detectorstem_type']])->toString(),
          'data-field-id' => 'detectorstem_type',
          'data-elementtype' => 'detectorstem',
          'data-search-value' => $this->getDetectorStem()->superUri ?? '',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['detectorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getDetectorStem()->hasContent,
      '#disabled' => TRUE,

    ];
    $form['detectorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getDetectorStem()->hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['detectorstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getDetectorStem()->hasVersion,
      '#disabled' => TRUE,
      '#default_value' =>
        ($this->getDetectorStem()->hasStatus === VSTOI::CURRENT || $this->getDetectorStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getDetectorStem()->hasVersion + 1 : $this->getDetectorStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['detectorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getDetectorStem()->comment,
      '#disabled' => TRUE,
    ];
    if ($this->getDetectorStem()->wasDerivedFrom !== NULL) {
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getDetectorStem()->wasDerivedFrom);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $result = $obj->body;

        $form['detectorstem__df_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
            'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
          ],
        ];

        $form['detectorstem__df_wrapper']['detectorstem__wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => Utils::fieldToAutocomplete($this->getDetectorStem()->wasDerivedFrom, $result->label),
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 1045px;",
            'disabled' => 'disabled',
          ],
        ];

        $elementUri = Utils::namespaceUri($this->getDetectorStem()->wasDerivedFrom);
        $elementUriEncoded = base64_encode($elementUri);
        $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

        $form['detectorstem__df_wrapper']['detectorstem__wasderivedfrom_button'] = [
          '#type' => 'markup',
          '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
        ];
      }
    }

    $form['detectorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
      '#disabled' => TRUE,
    ];

    $form['detectorstem_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getDetectorStem()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['detectorstem_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getDetectorStem()->hasReviewNote,
    ];
    $form['detectorstem_haseditoremail'] = [
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
    //     if(strlen($form_state->getValue('detector_hasreviewnote')) < 1) {
    //       $form_state->setErrorByName('detector_hasreviewnote', $this->t('You must enter a Reject Note'));
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('detectorstem_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getDetectorStem();

      //APROVE
      if ($button_name !== 'review_reject') {

        $detectorStemJson = '{"uri":"'.$this->getDetectorStem()->uri.'",'.
          '"superUri":"'.$this->getDetectorStem()->superUri.'",'.
          '"label":"'.$this->getDetectorStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasContent":"'.$this->getDetectorStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getDetectorStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getDetectorStem()->hasVersion.'",'.
          '"comment":"'.$this->getDetectorStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getDetectorStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getDetectorStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detectorstem_hasreviewnote').'",'.
          '"hasWebDocument":"'.$form_state->getValue('detectorstem_webdocument').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getDetectorStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->detectorStemDel($this->getDetectorStemUri());
        $api->detectorStemAdd($detectorStemJson);

        // IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED, but in this case version must be also greater than 1, because
        // Detector Stems can start to be like a derivation element by itself
        if (($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') && $result->hasVersion > 1) {
          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentDetectorStemJson = '{"uri":"'.$resultParent->uri.'",'.
          (!empty($resultParent->superUri) ? '"superUri":"'.$resultParent->superUri.'",' : '').
          '"label":"'.$resultParent->label.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
          '"hasContent":"'.$resultParent->hasContent.'",'.
          '"hasLanguage":"'.$resultParent->hasLanguage.'",'.
          '"hasVersion":"'.$resultParent->hasVersion.'",'.
          '"comment":"'.$resultParent->comment.'",'.
          (!empty($resultParent->wasDerivedFrom) ? '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",' : '').
          '"wasGeneratedBy":"'.$resultParent->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
          '"hasWebDocument":"'.$resultParent->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'"}';

          // UPDATE BY DELETING AND CREATING
          $api->detectorStemDel($resultParent->uri);
          $api->detectorStemAdd($parentDetectorStemJson);
        }

        \Drupal::messenger()->addMessage(t("Detector Stem has been updated successfully."));
      // REJECT
      } else {

        $detectorStemJson = '{"uri":"'.$this->getDetectorStem()->uri.'",'.
          '"superUri":"'.$this->getDetectorStem()->superUri.'",'.
          '"label":"'.$this->getDetectorStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$this->getDetectorStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getDetectorStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getDetectorStem()->hasVersion.'",'.
          '"comment":"'.$this->getDetectorStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getDetectorStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getDetectorStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detectorstem_hasreviewnote').'",'.
          '"hasWebDocument":"'.$form_state->getValue('detectorstem_webdocument').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getDetectorStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->detectorStemDel($this->getDetectorStemUri());
        $api->detectorStemAdd($detectorStemJson);
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Detector Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveDetectorStem($detectorStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($detectorStemUri);
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
