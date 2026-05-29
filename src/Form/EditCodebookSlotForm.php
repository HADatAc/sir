<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;

class EditCodebookSlotForm extends FormBase {

  protected $codebookSlotUri;

  protected $codebookSlot;

  public function getCodebookSlotUri() {
    return $this->codebookSlotUri;
  }

  public function setCodebookSlotUri($uri) {
    return $this->codebookSlotUri = $uri;
  }

  public function getCodebookSlot() {
    return $this->codebookSlot;
  }

  public function setCodebookSlot($obj) {
    return $this->codebookSlot = $obj;
  }

  /**
   * Derive Codebook URI from a Codebook Slot URI.
   */
  private function deriveCodebookUriFromSlotUri(string $slotUri): string {
    $slotUri = trim($slotUri);
    if ($slotUri === '') {
      return '';
    }

    return preg_replace('~/CBS/[^/]+$~', '', $slotUri);
  }

  /**
   * Derive priority from a Codebook Slot URI (e.g. .../CBS/1 => 1).
   */
  private function derivePriorityFromSlotUri(string $slotUri): string {
    if (preg_match('~/CBS/([^/]+)$~', $slotUri, $matches) === 1) {
      return trim((string) $matches[1]);
    }

    return '';
  }

  /**
   * Resolve a codebook URI for redirect flows.
   */
  private function resolveCodebookUri(): string {
    if ($this->getCodebookSlot() != NULL && isset($this->getCodebookSlot()->belongsTo)) {
      $belongsTo = trim((string) $this->getCodebookSlot()->belongsTo);
      if ($belongsTo !== '') {
        return $belongsTo;
      }
    }

    return $this->deriveCodebookUriFromSlotUri((string) $this->getCodebookSlotUri());
  }

  /**
   * Build the safest redirect URL back to codebook context.
   */
  private function buildBackUrl(): Url {
    $codebookUri = $this->resolveCodebookUri();
    if ($codebookUri !== '') {
      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($codebookUri));
      return $url;
    }

    // Fallback to generic codebook list if no slot context is available.
    return Url::fromRoute('sir.select_element', [
      'elementtype' => 'codebook',
      'page' => '1',
      'pagesize' => '9',
    ]);
  }

  /**
   * Resolve a priority value for submit flow.
   *
   * The priority field is display-only in this form, so it may not be posted.
   */
  private function resolvePriority(FormStateInterface $form_state): string {
    $priority = trim((string) $form_state->getValue('codebook_slot_priority_value', ''));

    if ($priority === '' && $this->getCodebookSlot() != NULL && isset($this->getCodebookSlot()->hasPriority)) {
      $priority = trim((string) $this->getCodebookSlot()->hasPriority);
    }

    if ($priority === '') {
      $priority = $this->derivePriorityFromSlotUri((string) $this->getCodebookSlotUri());
    }

    // Keep a valid fallback so submit does not fail on disabled input.
    return $priority !== '' ? $priority : '1';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_codebookslot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebooksloturi = NULL) {
    $uri=$codebooksloturi;
    $uri_decode=base64_decode($uri);
    $this->setCodebookSlotUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getCodebookSlotUri());
    $obj = is_string($rawresponse) ? json_decode($rawresponse) : NULL;

    $content = "";
    $priority = $this->derivePriorityFromSlotUri((string) $this->getCodebookSlotUri());
    if (is_object($obj) && !empty($obj->isSuccessful) && isset($obj->body) && is_object($obj->body)) {
      $this->setCodebookSlot($obj->body);
      if (isset($this->getCodebookSlot()->hasPriority) && trim((string) $this->getCodebookSlot()->hasPriority) !== '') {
        $priority = (string) $this->getCodebookSlot()->hasPriority;
      }
      if ($this->getCodebookSlot()->responseOption != NULL) {
        $ro = $this->getCodebookSlot()->responseOption;
        $roText = is_object($ro) ? (string) (($ro->hasContent ?? '') !== '' ? ($ro->hasContent ?? '') : ($ro->label ?? '')) : '';
        $content = $roText . ' [' . $this->getCodebookSlot()->hasResponseOption . ']';
      }
    } else {
      \Drupal::messenger()->addWarning(t('Failed to retrieve Response Option Slot details. Using URI fallback values.'));
    }

    $form['codebook_slot_uri'] = [
      '#type' => 'textfield',
      '#title' => t('ResponseOption Slot URI'),
      '#value' => $this->getCodebookSlotUri(),
      '#disabled' => TRUE,
    ];
    $form['codebook_slot_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
      '#default_value' => $priority,
      '#disabled' => TRUE,
    ];
    $form['codebook_slot_priority_value'] = [
      '#type' => 'hidden',
      '#value' => $priority,
    ];
    $form['codebook_slot_response_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Response Option"),
      '#default_value' => $content,
      '#autocomplete_route_name' => 'sir.codebookslot_response_option_autocomplete',
    ];
    $form['primary_actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'flex-wrap', 'gap-2', 'mb-3'],
      ],
    ];
    $form['primary_actions']['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['primary_actions']['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];

    $form['secondary_actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'flex-wrap', 'gap-2', 'mt-2'],
      ],
    ];
    $form['secondary_actions']['new_responseoption_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Response Option'),
      '#name' => 'new_response_option',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];
    $form['secondary_actions']['reset_responseoption_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Response Option Slot'),
      '#name' => 'reset_response_option',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'reset-button'],
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
      $form_state->setValue('codebook_slot_priority_value', $this->resolvePriority($form_state));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // RETRIEVE TRIGGERING ELEMENT
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    if ($button_name === 'back') {
      $url = $this->buildBackUrl();
      $form_state->setRedirectUrl($url);
      return;
    }

    if ($button_name === 'new_response_option') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_response_option');
      $url = Url::fromRoute('sir.add_response_option');
      $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
      $form_state->setRedirectUrl($url);
      return;
    }

    if ($button_name === 'reset_response_option') {
      // RESET responseOption
      if ($this->getCodebookSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->codebookSlotReset($this->getCodebookSlotUri());
      }

      $url = $this->buildBackUrl();
      $form_state->setRedirectUrl($url);
      return;
    }

    try {
      // UPDATE responseOption
      if ($this->getCodebookSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->responseOptionAttach(Utils::uriFromAutocomplete($form_state->getValue('codebook_slot_response_option')),$this->getCodebookSlotUri());
      }

      \Drupal::messenger()->addMessage(t("Response Option Slot has been updated successfully."));
      $url = $this->buildBackUrl();
      $form_state->setRedirectUrl($url);

    } catch(\Exception $e) {
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Response Option Slot: ".$e->getMessage()));
      $url = $this->buildBackUrl();
      $form_state->setRedirectUrl($url);
    }

  }

}
