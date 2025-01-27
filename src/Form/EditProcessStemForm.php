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

class EditProcessStemForm extends FormBase {

  protected $processStemUri;

  protected $processStem  ;

  protected $sourceProcessStem;

  public function getProcessStemUri() {
    return $this->processStemUri;
  }

  public function setProcessStemUri($uri) {
    return $this->processStemUri = $uri;
  }

  public function getProcessStem() {
    return $this->processStem;
  }

  public function setProcessStem($obj) {
    return $this->processStem = $obj;
  }

  public function getSourceProcessStem() {
    return $this->sourceProcessStem;
  }

  public function setSourceProcessStem($obj) {
    return $this->sourceProcessStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_process_stem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $processstemuri = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $uri=$processstemuri;
    $uri_decode=base64_decode($uri);
    $this->setProcessStemUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setProcessStem($this->retrieveProcessStem($this->getProcessStemUri()));
    if ($this->getProcessStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Process Stem."));
      self::backUrl();
      return;
    } else {
      $wasGeneratedBy = $this->getProcessStem()->wasGeneratedBy;
      if ($this->getProcessStem()->wasDerivedFrom != NULL) {
        $this->setSourceProcessStem($this->retrieveProcessStem($this->getProcessStem()->wasDerivedFrom));
        if ($this->getSourceProcessStem() != NULL && $this->getSourceProcessStem()->hasContent != NULL) {
          $sourceContent = $this->getSourceProcessStem()->hasContent;
        }
      }
    }

    //dpm($this->getProcessStem());
    $form['process_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Type'),
        '#name' => 'process_stem_type',
          '#default_value' => $this->getProcessStem()->superUri ?? '',
        '#disabled' => TRUE,
        '#id' => 'process_stem_type',
        '#parents' => ['process_stem_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'processstem',
          ], ['query' => ['field_id' => 'process_stem_type']])->toString(),
          'data-field-id' => 'process_stem_type',
          'data-elementtype' => 'processstem',
          'data-search-value' => $this->getProcessStem()->superUri ?? '',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
      '#disabled' => TRUE,
    ];

    $form['process_stem_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getProcessStem()->hasContent,
    ];
    $form['process_stem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getProcessStem()->hasLanguage,
    ];
    $form['process_stem_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getProcessStem()->hasStatus === VSTOI::CURRENT || $this->getProcessStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getProcessStem()->hasVersion + 1 : $this->getProcessStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['process_stem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getProcessStem()->comment,
    ];
    $form['process_stem_was_derived_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Was Derived From'),
      '#default_value' => $sourceContent,
      '#disabled' => TRUE,
    ];
    $form['process_stem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
    ];
    $form['process_stem_update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['process_stem_cancel_submit'] = [
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
      if(strlen($form_state->getValue('process_stem_content')) < 1) {
        $form_state->setErrorByName('process_stem_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('process_stem_language')) < 1) {
        $form_state->setErrorByName('process_stem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('process_stem_version')) < 1) {
        $form_state->setErrorByName('process_stem_version', $this->t('Please enter a valid version'));
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
      if ($this->getSourceProcessStem() === NULL || $this->getSourceProcessStem()->uri === NULL) {
        $wasDerivedFrom = 'null';
      } else {
        $wasDerivedFrom = $this->getSourceProcessStem()->uri;
      }

      $processJson = '{"uri":"'.$this->getProcessStem()->uri.'",'.
        '"superUri":"'.UTILS::plainUri($form_state->getValue('process_stem_type')).'",'.
        //'"typeUri":"'.VSTOI::DETECTOR_STEM.'",'.
        '"label":"'.$form_state->getValue('process_stem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::PROCESS.'",'.
        '"hasStatus":"'.VSTOI::CURRENT.'",'.
        '"hasContent":"'.$form_state->getValue('process_stem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('process_stem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('process_stem_version').'",'.
        '"comment":"'.$form_state->getValue('process_stem_description').'",'.
        '"wasDerivedFrom":"'.$wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('process_stem_was_generated_by').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->processStemDel($this->getProcessStemUri());
      $updatedProcessStem = $api->processStemAdd($processJson);
      \Drupal::messenger()->addMessage(t("Process Stem has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Process Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveProcessStem($processStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($processStemUri);
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
