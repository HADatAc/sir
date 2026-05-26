<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddCommandForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_command_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $form['command_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
    ];

    $form['command_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];

    $form['command_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];

    $form['command_version_hidden'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];

    $form['command_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    $form['command_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
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
      if (strlen((string) $form_state->getValue('command_content')) < 1) {
        $form_state->setErrorByName('command_content', $this->t('Please enter a valid content'));
      }
      if (strlen((string) $form_state->getValue('command_language')) < 1) {
        $form_state->setErrorByName('command_language', $this->t('Please enter a valid language'));
      }
      if (strlen((string) $form_state->getValue('command_version_hidden')) < 1) {
        $form_state->setErrorByName('command_version', $this->t('Please enter a valid version'));
      }
      if (strlen((string) $form_state->getValue('command_description')) < 1) {
        $form_state->setErrorByName('command_description', $this->t('Please enter a valid description'));
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
      $this->backUrl();
      return;
    }

    try {
      $useremail = \Drupal::currentUser()->getEmail();
      $newCommandUri = Utils::uriGen('command');

      $payload = [
        'uri' => $newCommandUri,
        'typeUri' => VSTOI::COMMAND,
        'hascoTypeUri' => VSTOI::COMMAND,
        'hasStatus' => VSTOI::DRAFT,
        'hasContent' => htmlentities((string) $form_state->getValue('command_content')),
        'hasLanguage' => (string) $form_state->getValue('command_language'),
        'hasVersion' => (string) $form_state->getValue('command_version_hidden'),
        'comment' => (string) $form_state->getValue('command_description'),
        'hasWebDocument' => (string) $form_state->getValue('command_webdocument'),
        'hasSIRManagerEmail' => $useremail,
      ];

      $commandJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

      $api = \Drupal::service('rep.api_connector');
      $apiResponse = $api->elementAdd('command', $commandJson);
      $parsed = $api->parseObjectResponse($apiResponse, 'elementAdd');
      if ($parsed === NULL) {
        $this->backUrl();
        return;
      }

      \Drupal::messenger()->addMessage(t('Added a new Command with URI: ' . $newCommandUri));
      $this->backUrl();
      return;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('An error occurred while adding the Command: ' . $e->getMessage()));
      $this->backUrl();
      return;
    }
  }

  protected function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_command');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
