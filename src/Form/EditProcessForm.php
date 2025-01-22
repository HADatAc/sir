<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;

class EditProcessForm extends FormBase {

  protected $processUri;

  protected $process;

  public function getProcessUri() {
    return $this->processUri;
  }

  public function setProcessUri($uri) {
    return $this->processUri = $uri;
  }

  public function getProcess() {
    return $this->process;
  }

  public function setProcess($pr) {
    return $this->process = $pr;
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
    $uri=$processuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setProcessUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getProcessUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setProcess($obj->body);
      #dpm($this->getProcess());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Process."));
      self::backUrl();
      return;
    }

    // $form['process_type'] = [
    //   'top' => [
    //     '#type' => 'markup',
    //     '#markup' => '<div class="pt-3 col border border-white">',
    //   ],
    //   'main' => [
    //     '#type' => 'textfield',
    //     '#title' => $this->t('Parent Type'),
    //     '#name' => 'process_type',
    //     '#default_value' => $this->getProcess()->typeUri ? UTILS::namespaceUri($this->getProcess()->typeUri) : '',
    //     '#id' => 'process_type',
    //     '#parents' => ['process_type'],
    //     '#disabled' => TRUE,
    //     '#attributes' => [
    //       'class' => ['open-tree-modal'],
    //       'data-dialog-type' => 'modal',
    //       'data-dialog-options' => json_encode(['width' => 800]),
    //       'data-url' => Url::fromRoute('rep.tree_form', [
    //         'mode' => 'modal',
    //         'elementtype' => 'process',
    //       ], ['query' => ['field_id' => 'process_type']])->toString(),
    //       'data-field-id' => 'process_type',
    //       'data-elementtype' => 'process',
    //       'autocomplete' => 'off',
    //       'data-search-value' => $this->getProcess()->typeUri ?? '',
    //     ],
    //   ],
    //   'bottom' => [
    //     '#type' => 'markup',
    //     '#markup' => '</div>',
    //   ],
    // ];
    $form['process_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getProcess()->label,
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('process_name')) < 1) {
        $form_state->setErrorByName('process_name', $this->t('Please enter a valid name'));
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $processJson = '{"uri":"'. $this->getProcess()->uri .'",'.
        '"typeUri":"'.VSTOI::PROCESS.'",'.
        '"hascoTypeUri":"'.VSTOI::PROCESS.'",'.
        '"hasStatus":"'.$this->getProcess()->hasStatus.'",'.
        '"label":"'.$form_state->getValue('process_name').'",'.
        '"hasLanguage":"'.$form_state->getValue('process_language').'",'.
        '"hasVersion":"'.$form_state->getValue('process_version').'",'.
        '"comment":"'.$form_state->getValue('process_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->processDel($this->getProcess()->uri);
      $api->processAdd($processJson);

      \Drupal::messenger()->addMessage(t("Process has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating Process: ".$e->getMessage()));
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
