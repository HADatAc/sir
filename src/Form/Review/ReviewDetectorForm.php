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

class ReviewDetectorForm extends FormBase {

  protected $detectorUri;

  protected $detector;

  protected $sourceDetector;

  public function getDetectorUri() {
    return $this->detectorUri;
  }

  public function setDetectorUri($uri) {
    return $this->detectorUri = $uri;
  }

  public function getDetector() {
    return $this->detector;
  }

  public function setDetector($obj) {
    return $this->detector = $obj;
  }

  public function getSourceDetector() {
    return $this->sourceDetector;
  }

  public function setSourceDetector($obj) {
    return $this->sourceDetector = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_detector_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $detectoruri = NULL) {


    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';


    $uri=$detectoruri;
    $uri_decode=base64_decode($uri);
    $this->setDetectorUri($uri_decode);

    $sourceContent = '';
    $stemLabel = '';
    $codebookLabel = '';
    $this->setDetector($this->retrieveDetector($this->getDetectorUri()));
    if ($this->getDetector() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Detector."));
      self::backUrl();
      return;
    } else {
      if ($this->getDetector()->detectorStem != NULL) {
        $stemLabel = $this->getDetector()->detectorStem->hasContent . ' [' . $this->getDetector()->detectorStem->uri . ']';
      }
      if ($this->getDetector()->codebook != NULL) {
        $codebookLabel = $this->getDetector()->codebook->label . ' [' . $this->getDetector()->codebook->uri . ']';
      }
    }

    //dpm($this->getDetector());

    $form['detector_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
          $this->t('Simulation Technique Stem') :
          $this->t('Detector Stem'),
        '#name' => 'detector_stem',
        '#default_value' => Utils::fieldToAutocomplete($this->getDetector()->typeUri, $this->getDetector()->detectorStem->label),
        '#id' => 'detector_stem',
        '#parents' => ['detector_stem'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorstem',
          ], ['query' => ['field_id' => 'detector_stem']])->toString(),
          'data-field-id' => 'detector_stem',
          'data-elementtype' => 'detectorstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
      '#disabled' => TRUE
    ];
    $form['detector_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
      '#disabled' => TRUE
    ];
    $form['detector_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getDetector()->hasStatus === VSTOI::CURRENT || $this->getDetector()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getDetector()->hasVersion + 1 : $this->getDetector()->hasVersion,
      '#disabled' => TRUE
    ];
    $form['detector_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'detector_isAttributeOf',
        '#default_value' => $this->getDetector()->isAttributeOf,
        '#id' => 'detector_isAttributeOf',
        '#parents' => ['detector_isAttributeOf'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorattribute',
          ], ['query' => ['field_id' => 'detector_isAttributeOf']])->toString(),
          'data-field-id' => 'detector_isAttributeOf',
          'data-elementtype' => 'detectorattribute',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
      '#disabled' => TRUE
    ];
    if ($this->getDetector()->wasDerivedFrom !== NULL) {
      $form['detector_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      $form['detector_df_wrapper']['detector_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getDetector()->wasDerivedFrom,
        '#attributes' => [
          'class' => ['flex-grow-1'],
          'style' => "width: 100%; min-width: 0;",
          'disabled' => 'disabled',
        ],
      ];

      $elementUri = Utils::namespaceUri($this->getDetector()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['detector_df_wrapper']['detector_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }

    $form['detector_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getDetector()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['detector_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getDetector()->hasReviewNote,
    ];
    $form['detector_haseditoremail'] = [
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

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('detector_stem')) < 1) {
        $form_state->setErrorByName('detector_stem', $this->t('Please enter a valid detector stem'));
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('detector_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getDetector();

      //APROVE
      if ($button_name !== 'review_reject') {

        $detectorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
          '"hasDetectorStem":"'.$result->hasDetectorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detector_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->detectorDel($result->uri);
        $api->detectorAdd($detectorJson);

        //IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED
        if ($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') {

          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentDetectorJson = '{'.
            '"uri":"'.$resultParent->uri.'",'.
            '"typeUri":"'.$resultParent->typeUri.'",'.
            '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
            '"hasDetectorStem":"'.$resultParent->hasDetectorStem.'",'.
            '"hasCodebook":"'.$resultParent->hasCodebook.'",'.
            '"hasContent":"'.$resultParent->label.'",'.
            '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'",'.
            '"label":"'.$resultParent->label.'",'.
            '"hasVersion":"'.$resultParent->hasVersion.'",'.
            '"isAttributeOf":"'.$resultParent->isAttributeOf.'",'.
            '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",'.
            '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
            '"hasEditorEmail":"'.$resultParent->hasEditorEmail.'",'.
            '"hasStatus":"'.VSTOI::DEPRECATED.'"'.
          '}';

          // UPDATE BY DELETING AND CREATING
          $api->detectorDel($resultParent->uri);
          $api->detectorAdd($parentDetectorJson);
        }

        \Drupal::messenger()->addMessage(t("Detector has been APPROVED successfully."));
          self::backUrl();
          return;

      // REJECT
      } else {

        //MAIN BODY CODEBOOK
        $detectorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
          '"hasDetectorStem":"'.$result->hasDetectorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detector_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // \Drupal::messenger()->addWarning($detectorJson);
        // return false;

        // UPDATE BY DELETING AND CREATING
        $api->detectorDel($result->uri);
        $api->detectorAdd($detectorJson);

        \Drupal::messenger()->addWarning(t("Detector has been REJECTED."));
          self::backUrl();
          return;
      }

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Detector: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveDetector($detectorUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($detectorUri);
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
