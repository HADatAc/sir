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

class EditInstrumentForm extends FormBase {

  protected $instrumentUri;

  protected $instrument;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri;
  }

  public function getInstrument() {
    return $this->instrument;
  }

  public function setInstrument($instrument) {
    return $this->instrument = $instrument;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $uri_decode=base64_decode($instrumenturi);
    $this->setInstrumentUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getInstrumentUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setInstrument($obj->body);
      //dpm($this->getInstrument());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Instrument."));
      self::backUrl();
      return;
    }

    $hasInformant = Constant::DEFAULT_INFORMANT;
    if ($this->getInstrument()->hasInformant != NULL && $this->getInstrument()->hasInformant != '') {
      $hasInformant = $this->getInstrument()->hasInformant;
    }

    $hasLanguage = Constant::DEFAULT_LANGUAGE;
    if ($this->getInstrument()->hasLanguage != NULL && $this->getInstrument()->hasLanguage != '') {
      $hasLanguage = $this->getInstrument()->hasLanguage;
    }

    $form['instrument_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Parent Type'),
        '#name' => 'instrument_type',
        '#default_value' => Utils::fieldToAutocomplete($this->getInstrument()->superUri, $this->getInstrument()->superClassLabel),
        '#id' => 'instrument_type',
        '#parents' => ['instrument_type'],
        // '#disabled' => TRUE,
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'instrument',
          ], ['query' => ['field_id' => 'instrument_type']])->toString(),
          'data-field-id' => 'instrument_type',
          'data-elementtype' => 'instrument',
          'data-search-value' => $this->getInstrument()->superUri ?? '',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    $form['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getInstrument()->label,
    ];
    $form['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
      '#default_value' => $this->getInstrument()->hasShortName,
    ];
    $form['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => $hasInformant,
    ];
    $form['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $hasLanguage,
    ];
    $form['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getInstrument()->hasStatus === VSTOI::CURRENT || $this->getInstrument()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getInstrument()->hasVersion + 1 : $this->getInstrument()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getInstrument()->comment,
    ];
    $form['instrument_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getInstrument()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
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
      if(strlen($form_state->getValue('instrument_name')) < 1) {
        $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
        $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid abbreviation'));
      }
      if(strlen($form_state->getValue('instrument_language')) < 1) {
        $form_state->setErrorByName('instrument_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('instrument_version')) < 1) {
        $form_state->setErrorByName('instrument_version', $this->t('Please enter a valid version'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    $api = \Drupal::service('rep.api_connector');

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();


      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getInstrument()->hasStatus === VSTOI::CURRENT || $this->getInstrument()->hasStatus === VSTOI::DEPRECATED) {

        $instrumentJson = '{"uri":"'.Utils::uriGen('instrument').'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instrument_type')).'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"hasWebDocument":"'.$form_state->getValue('instrument_webdocument').'",'.
        '"comment":"'.$form_state->getValue('instrument_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        // ADD NEW INSTRUMENT VERSION
        $api->elementAdd('instrument', $instrumentJson);
        \Drupal::messenger()->addMessage(t("New Version instrument has been created successfully."));
      } else {

        // MUST PAY ATENTION TO CONTANINER ANS NEXT/PREVIOUS/ETC...

        $instrumentJson = '{"uri":"'.$this->getInstrumentUri().'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instrument_type')).'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"hasWebDocument":"'.$form_state->getValue('instrument_webdocument').'",'.
        '"comment":"'.$form_state->getValue('instrument_description').'",'.

        '"hasFirst":"'.$this->getInstrument()->hasFirst.'",'.
        '"belongsTo":"'.$this->getInstrument()->belongsTo.'",'.
        '"hasNext":"'.$this->getInstrument()->hasNext.'",'.
        '"hasPrevious":"'.$this->getInstrument()->hasPrevious.'",'.
        // '"hasPriority":"'.$this->getInstrument()->hasPriority.'",'.
        '"annotations":"'.$this->getInstrument()->annotations.'",'.

        '"hasReviewNote":"'.$this->getInstrument()->hasReviewNote.'",'.
        '"hasEditorEmail":"'.$this->getInstrument()->hasEditorEmail.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        // dpm($instrumentJson);
        // return false;

        // UPDATE BY DELETING AND CREATING CURRENT INSTRUMENT
        $api->elementDel('instrument', $this->getInstrumentUri());
        $api->elementAdd('instrument', $instrumentJson);
        \Drupal::messenger()->addMessage(t("instrument has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Instrument: ".$e->getMessage()));
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
