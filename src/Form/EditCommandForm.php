<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class EditCommandForm extends FormBase {

  protected $commandUri;

  protected $command;

  public function getCommandUri() {
    return $this->commandUri;
  }

  public function setCommandUri($uri) {
    return $this->commandUri = $uri;
  }

  public function getCommand() {
    return $this->command;
  }

  public function setCommand($obj) {
    return $this->command = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_command_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $commanduri = NULL) {
    if ($commanduri == NULL || $commanduri == '') {
      \Drupal::messenger()->addError(t('No command uri has been provided'));
      $this->backUrl();
      return [];
    }

    $uri_decode = base64_decode($commanduri);
    $this->setCommandUri($uri_decode);
    $this->setCommand($this->retrieveCommand($this->getCommandUri()));

    if ($this->getCommand() == NULL) {
      \Drupal::messenger()->addMessage(t('Failed to retrieve Command.'));
      $this->backUrl();
      return [];
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $form['command_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => html_entity_decode((string) ($this->getCommand()->hasContent ?? '')),
    ];

    $form['command_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => (string) ($this->getCommand()->hasLanguage ?? 'en'),
    ];

    $version = (string) ($this->getCommand()->hasVersion ?? '1');
    $form['command_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $version,
      '#disabled' => TRUE,
    ];

    $form['command_version_hidden'] = [
      '#type' => 'hidden',
      '#value' => $version,
    ];

    $form['command_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => (string) ($this->getCommand()->comment ?? ''),
    ];

    $form['command_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => (string) ($this->getCommand()->hasWebDocument ?? ''),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
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

      $payload = [
        'uri' => $this->getCommand()->uri,
        'typeUri' => $this->getCommand()->typeUri ?? VSTOI::COMMAND,
        'hascoTypeUri' => VSTOI::COMMAND,
        'hasStatus' => $this->getCommand()->hasStatus ?? VSTOI::DRAFT,
        'hasContent' => htmlentities((string) $form_state->getValue('command_content')),
        'hasLanguage' => (string) $form_state->getValue('command_language'),
        'hasVersion' => (string) $form_state->getValue('command_version_hidden'),
        'comment' => (string) $form_state->getValue('command_description'),
        'hasWebDocument' => (string) $form_state->getValue('command_webdocument'),
        'hasReviewNote' => (string) ($this->getCommand()->hasReviewNote ?? ''),
        'hasImageUri' => (string) ($this->getCommand()->hasImageUri ?? ''),
        'hasEditorEmail' => $useremail,
        'hasSIRManagerEmail' => (string) ($this->getCommand()->hasSIRManagerEmail ?? $useremail),
      ];

      $commandJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

      $api = \Drupal::service('rep.api_connector');
      $api->elementDel('command', $this->getCommandUri());
      $apiResponse = $api->elementAdd('command', $commandJson);
      $parsed = $api->parseObjectResponse($apiResponse, 'elementAdd');
      if ($parsed === NULL) {
        $this->backUrl();
        return;
      }

      \Drupal::messenger()->addMessage(t('Command has been updated successfully.'));
      $this->backUrl();
      return;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage(t('An error occurred while updating the Command: ' . $e->getMessage()));
      $this->backUrl();
      return;
    }
  }

  protected function retrieveCommand($commandUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($commandUri);
    $obj = json_decode($rawresponse);
    if ($obj && $obj->isSuccessful) {
      return $obj->body;
    }
    return NULL;
  }

  protected function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, \Drupal::request()->getRequestUri());
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
