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
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setProcess($this->retrieveProcess($this->getProcessUri()));
    if ($this->getProcess() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Process."));
      self::backUrl();
      return;
    } else {
      $wasGeneratedBy = $this->getProcess()->wasGeneratedBy;
      if ($this->getProcess()->wasDerivedFrom != NULL) {
        $this->setSourceProcess($this->retrieveProcess($this->getProcess()->wasDerivedFrom));
        if ($this->getSourceProcess() != NULL && $this->getSourceProcess()->hasContent != NULL) {
          $sourceContent = $this->getSourceProcess()->hasContent;
        }
      }
    }

    //dpm($this->getProcess());
    $form['process_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Type'),
        '#name' => 'process_type',
          '#default_value' => $this->getProcess()->superUri ? UTILS::namespaceUri($this->getProcess()->superUri) : '',
        '#disabled' => TRUE,
        '#id' => 'process_type',
        '#parents' => ['process_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'process',
          ], ['query' => ['field_id' => 'process_type']])->toString(),
          'data-field-id' => 'process_type',
          'data-elementtype' => 'process',
          'data-search-value' => $this->getProcess()->superUri ?? '',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    $form['process_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getProcess()->hasContent,
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
      '#default_value' => $this->getProcess()->hasVersion,
      '#disabled' => TRUE,
    ];
    $form['process_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getProcess()->comment,
    ];
    $form['process_was_derived_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Was Derived From'),
      '#default_value' => $sourceContent,
      '#disabled' => TRUE,
    ];
    $form['process_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
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
      if(strlen($form_state->getValue('process_content')) < 1) {
        $form_state->setErrorByName('process_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('process_language')) < 1) {
        $form_state->setErrorByName('process_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('process_version')) < 1) {
        $form_state->setErrorByName('process_version', $this->t('Please enter a valid version'));
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

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{

      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $wasDerivedFrom = '';
      if ($this->getSourceProcess() === NULL || $this->getSourceProcess()->uri === NULL) {
        $wasDerivedFrom = 'null';
      } else {
        $wasDerivedFrom = $this->getSourceProcess()->uri;
      }

      $processJson = '{"uri":"'.$this->getProcess()->uri.'",'.
        '"superUri":"'.UTILS::plainUri($form_state->getValue('process_type')).'",'.
        //'"typeUri":"'.VSTOI::DETECTOR_STEM.'",'.
        '"label":"'.$form_state->getValue('process_content').'",'.
        '"hascoTypeUri":"'.VSTOI::PROCESS.'",'.
        '"hasStatus":"'.VSTOI::CURRENT.'",'.
        '"hasContent":"'.$form_state->getValue('process_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('process_language').'",'.
        '"hasVersion":"'.$form_state->getValue('process_version').'",'.
        '"comment":"'.$form_state->getValue('process_description').'",'.
        '"wasDerivedFrom":"'.$wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('process_was_generated_by').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->processDel($this->getProcessUri());
      $updatedProcess = $api->processAdd($processJson);
      \Drupal::messenger()->addMessage(t("Process has been updated successfully."));
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
