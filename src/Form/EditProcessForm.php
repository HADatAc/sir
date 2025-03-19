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

class EditProcessForm extends FormBase {

  protected $processUri;

  protected $process;

  protected $sourceProcess;

  public function getProcessUri() {
    return $this->processUri;
  }

  public function setProcessUri($uri) {
    return $this->processUri = $uri;
  }

  public function getProcess() {
    return $this->process;
  }

  public function setProcess($obj) {
    return $this->process = $obj;
  }

  public function getSourceProcess() {
    return $this->sourceProcess;
  }

  public function setSourceProcess($obj) {
    return $this->sourceProcess = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_process_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $processuri = NULL) {


    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';


    $uri=$processuri;
    $uri_decode=base64_decode($uri);
    $this->setProcessUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    //SELECT ONE
    $languages = ['' => $this->t('Select language please')] + $languages;
    $informants = ['' => $this->t('Select Informant please')] + $informants;

    // Get Process Data

    $api = \Drupal::service('rep.api_connector');
    $uri_decode=base64_decode($processuri);
    $process = $api->parseObjectResponse($api->getUri($uri_decode),'getUri');
    if ($process == NULL) {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Process."));
      self::backUrl();
      return;
    } else {
      $this->setProcess($process);
    }

    $form['process_processstem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Process Stem'),
        '#name' => 'process_processstem',
        '#default_value' => UTILS::fieldToAutocomplete($this->getProcess()->typeUri, $this->getProcess()->typeLabel),
        '#id' => 'process_processstem',
        '#parents' => ['process_processstem'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'processstem',
          ], ['query' => ['field_id' => 'process_processstem']])->toString(),
          'data-field-id' => 'process_processstem',
          'data-elementtype' => 'processstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['process_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getProcess()->label,
      '#required' => true
    ];
    $form['process_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getProcess()->hasLanguage,
    ];
    $form['process_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getProcess()->hasStatus === VSTOI::CURRENT || $this->getProcess()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getProcess()->hasVersion + 1 : $this->getProcess()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['process_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getProcess()->comment,
      '#required' => true
    ];
    $form['process_toptask'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top Task'),
      '#default_value' => (isset($this->getProcess()->hasTopTask) ? UTILS::fieldToAutocomplete($this->getProcess()->hasTopTask, $this->getProcess()->typeLabel) : ''),
      '#autocomplete_route_name' => 'sir.process_task_autocomplete',
    ];
    $form['process_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getProcess()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
    if ($this->getProcess()->hasReviewNote !== NULL && $this->getProcess()->hasSatus !== null) {
      $form['process_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getProcess()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['process_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getProcess()->hasEditorEmail,
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
      '#limit_validation_errors' => [],
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
    $api = \Drupal::service('rep.api_connector');
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name !== 'back') {
      if(strlen($form_state->getValue('process_processstem')) < 1) {
        $form_state->setErrorByName('process_processstem', $this->t('Please enter a valid Process stem'));
      }
    } else {
      self::backUrl();
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

      // GET THE PROCESS STEM URI
      $rawresponse = $api->getUri(Utils::uriFromAutocomplete($form_state->getValue('process_stem')));
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getProcess()->hasStatus === VSTOI::CURRENT || $this->getProcess()->hasStatus === VSTOI::DEPRECATED) {

        $newProcessUri = Utils::uriGen('process');
        $processJSON = '{"uri":"' . $newProcessUri . '",'
          . '"typeUri":"' .Utils::uriFromAutocomplete($form_state->getValue('process_processstem')) . '",'
          . '"hascoTypeUri":"' . VSTOI::PROCESS . '",'
          . '"hasStatus":"' . VSTOI::DRAFT . '",'
          . '"label":"' . $form_state->getValue('process_name') . '",'
          . '"hasLanguage":"' . $form_state->getValue('process_language') . '",'
          . '"hasVersion":"' . $form_state->getValue('process_version') . '",'
          . '"comment":"' . $form_state->getValue('process_description') . '",'
          . '"hasWebDocument":"'. $form_state->getValue('process_webdocument') .'",'
          // . '"hasTopTask":"'. $form_state->getValue('process_toptask') .'",'
          . '"hasSIRManagerEmail":"' . $useremail .'",'
          . '"hasReviewNote":"'.($this->getProcess()->hasSatus !== null ? $this->getProcess()->hasReviewNote : '').'",'
          . '"hasEditorEmail":"'.($this->getProcess()->hasSatus !== null ? $this->getProcess()->hasEditorEmail : '').'"}';

        $api->elementAdd('process',$processJSON);
        \Drupal::messenger()->addMessage(t("New Version Process has been created successfully."));

      } else {

        $processJSON = '{"uri":"'.$this->getProcessUri().'",'.
          '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('process_processstem')).'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'",'.
          '"hasLanguage":"' . $form_state->getValue('process_language') . '",'.
          '"label":"'.$form_state->getValue('process_name').'",'.
          '"hasWebDocument":"'.$form_state->getValue('process_webdocument').'",'.
          '"hasVersion":"'.$form_state->getValue('process_version').'",'.
          '"comment":"' . $form_state->getValue('process_description') . '",'.
          // '"hasTopTask":"'. $form_state->getValue('process_toptask') .'",'.
          '"hasReviewNote":"'.$this->getProcess()->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$this->getProcess()->hasEditorEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('process',$this->getProcessUri());
        $api->elementAdd('process',$processJSON);
        \Drupal::messenger()->addMessage(t("Process has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Process: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveProcess($processUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($processUri);
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
