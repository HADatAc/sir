<?php

namespace Drupal\sir\Form;

use Abraham\TwitterOAuth\Util;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddProcessForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_process_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    //SELECT ONE
    if ($languages)
      $languages = ['' => $this->t('Select language please')] + $languages;
    if ($informants)
      $informants = ['' => $this->t('Select Informant please')] + $informants;

    $form['process_processstem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Process Stem'),
        '#name' => 'process_processstem',
        '#default_value' => '',
        '#id' => 'process_processstem',
        '#parents' => ['process_processstem'],
        '#required' => true,
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
      '#default_value' => '',
      '#required' => true,
    ];
    $form['process_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['process_version_hid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => 1,
      '#disabled' => true
    ];
    $form['process_version'] = [
      '#type' => 'hidden',
      '#value' => $version ?? 1,
    ];
    $form['process_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => '',
      '#required' => true,
    ];
    $form['process_toptask'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Top Task'),
      '#autocomplete_route_name' => 'sir.process_task_autocomplete',
    ];
    $form['process_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => '',
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    dpm($button_name) ;
    if ($button_name !== "back") {
      if(empty($form_state->getValue('process_processstem'))) {
        $form_state->setErrorByName('process_processstem', $this->t('Please select a valid Process Stem'));
      }
      if(strlen($form_state->getValue('process_name')) < 1) {
        $form_state->setErrorByName('process_name', $this->t('Please enter a valid Name'));
      }
      if(strlen($form_state->getValue('process_language')) < 1) {
        $form_state->setErrorByName('process_language', $this->t('Please enter a valid Language'));
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
      $useremail = \Drupal::currentUser()->getEmail();
      // Prepare data to be sent to the external service
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
        . '"hasSIRManagerEmail":"' . $useremail . '"}';

      $api->elementAdd('process',$processJSON);
      \Drupal::messenger()->addMessage(t("Process has been added successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding process: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_process');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
