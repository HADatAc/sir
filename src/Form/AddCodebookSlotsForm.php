<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;

class AddCodebookSlotsForm extends FormBase {

  protected $codebookUri;

  public function getCodebookUri() {
    return $this->codebookUri;
  }

  public function setCodebookUri($uri) {
    return $this->codebookUri = $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_codebook_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebookuri = NULL) {
    $uri=$codebookuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setCodebookUri($uri_decode);

    $form['slot_instrument'] = [
      '#type' => 'textfield',
      '#title' => t('Codebook URI'),
      '#value' => $this->getCodebookUri(),
      '#disabled' => TRUE,
    ];
    $form['slot_total_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specify the total number of slots for this codebook'),
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
      if(strlen($form_state->getValue('slot_total_number')) < 1) {
        $form_state->setErrorByName('slot_total_number', $this->t('Please specify a number of codebook slots greater than zero.'));
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

    try {
      $api = \Drupal::service('rep.api_connector');
      $api->codebookSlotAdd($this->getCodebookUri(),$form_state->getValue('slot_total_number'));

      \Drupal::messenger()->addMessage(t("Codebook Slots has been added successfully."));
      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getCodebookUri()));
      $form_state->setRedirectUrl($url);

    } catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while adding the Codebook slots: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.manage_codebook_slots');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
