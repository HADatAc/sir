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

class EditDetectorForm extends FormBase {

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
    return 'edit_detector_form';
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

    // dpm($this->getDetector());

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
    ];
    $form['detector_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
    ];
    $form['detector_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getDetector()->hasStatus === VSTOI::CURRENT || $this->getDetector()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getDetector()->hasVersion + 1 : $this->getDetector()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
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
    ];
    $form['detector_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getDetector()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
    if ($this->getDetector()->hasReviewNote !== NULL && $this->getDetector()->hasSatus !== null) {
      $form['detector_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getDetector()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['detector_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getDetector()->hasEditorEmail,
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

    try{

      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      // GET THE DETECTOR STEM URI
      $rawresponse = $api->getUri(Utils::uriFromAutocomplete($form_state->getValue('detector_stem')));
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      $label = "";
      if ($result->hasContent !== NULL) {
        $label .= $result->hasContent;
      } else {
        $label .= $result->label;
      }

      if ($form_state->getValue('detector_codebook') !== NULL && $form_state->getValue('detector_codebook') != '') {
        $codebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
        $rawresponseCB = $api->getUri($codebook);
        $objCB = json_decode($rawresponseCB);
        $resultCB = $objCB->body;
        $label .= '  -- CB:'.$resultCB->label;
      } else {
        $label = $result->label . '  -- CB:EMPTY';
      }

      $hasCodebook = '';
      if ($form_state->getValue('detector_codebook') != NULL && $form_state->getValue('detector_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
      }

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getDetector()->hasStatus === VSTOI::CURRENT || $this->getDetector()->hasStatus === VSTOI::DEPRECATED) {

        $newDetectorUri = Utils::uriGen('detector');
        $detectorJson = '{"uri":"'.$newDetectorUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
        '"hasDetectorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasWebDocument":"'.$form_state->getValue('detector_webdocument').'",'.
        '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
        '"isAttributeOf":"'.$form_state->getValue('detector_isAttributeOf').'",'.
        '"wasDerivedFrom":"'.$this->getDetector()->uri.'",'.
        '"hasReviewNote":"'.($this->getDetector()->hasSatus !== null ? $this->getDetector()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getDetector()->hasSatus !== null ? $this->getDetector()->hasEditorEmail : '').'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'"}';

        $api->detectorAdd($detectorJson);
        \Drupal::messenger()->addMessage(t("New Version detector has been created successfully."));

      } else {

        $detectorJson = '{"uri":"'.$this->getDetector()->uri.'",'.
          '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
          '"hasDetectorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
          '"hasCodebook":"'.$hasCodebook.'",'.
          '"hasContent":"'.$label.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'",'.
          '"label":"'.$label.'",'.
          '"hasWebDocument":"'.$form_state->getValue('detector_webdocument').'",'.
          '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
          '"isAttributeOf":"'.$form_state->getValue('detector_isAttributeOf').'",'.
          '"wasDerivedFrom":"'.$this->getDetector()->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$this->getDetector()->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$this->getDetector()->hasEditorEmail.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->detectorDel($this->getDetectorUri());
        $api->detectorAdd($detectorJson);
        \Drupal::messenger()->addMessage(t("Detector has been updated successfully."));
      }

      self::backUrl();
      return;

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
