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

  protected $detectorStemUri;

  protected $detectorStem;

  protected $sourceProcessStem;

  public function getProcessStemUri() {
    return $this->detectorStemUri;
  }

  public function setProcessStemUri($uri) {
    return $this->detectorStemUri = $uri;
  }

  public function getProcessStem() {
    return $this->detectorStem;
  }

  public function setProcessStem($obj) {
    return $this->detectorStem = $obj;
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
    return 'edit_processstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $processstemuri = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_processstem';

    $uri=$processstemuri;
    $uri_decode=base64_decode($uri);
    $this->setProcessStemUri($uri_decode);

    $this->setProcessStem($this->retrieveProcessStem($this->getProcessStemUri()));
    if ($this->getProcessStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Process."));
      self::backUrl();
      return;
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    // IN CASE ITS A DERIVATION ORIGINAL MUST BE REMOVED ALSO
    if ($this->getProcessStem()->hasStatus === VSTOI::CURRENT || $this->getProcessStem()->hasVersion > 1) {
      unset($derivations[Constant::DEFAULT_WAS_GENERATED_BY]);
    }

    $languages = ['' => $this->t('Select one please')] + $languages;
    $derivations = ['' => $this->t('Select one please')] + $derivations;

    // dpm($this->getProcessStem());
    if ($this->getProcessStem()->superUri) {
      $form['processstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="pt-3 col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Parent Type'),
          '#name' => 'processstem_type',
          '#default_value' => $this->getProcessStem()->superUri ? Utils::fieldToAutocomplete($this->getProcessStem()->superUri, $this->getProcessStem()->superClassLabel) : '',
          '#id' => 'processstem_type',
          '#parents' => ['processstem_type'],
          '#attributes' => [
            'class' => ['open-tree-modal'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 800]),
            'data-url' => Url::fromRoute('rep.tree_form', [
              'mode' => 'modal',
              'elementtype' => 'processstem',
            ], ['query' => ['field_id' => 'processstem_type']])->toString(),
            'data-field-id' => 'processstem_type',
            'data-elementtype' => 'processstem',
            'data-search-value' => $this->getProcessStem()->superUri ?? '',
          ],
        ],
        'bottom' => [
          '#type' => 'markup',
          '#markup' => '</div>',
        ],
      ];
    }

    $form['processstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getProcessStem()->hasContent,
    ];
    $form['processstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getProcessStem()->hasLanguage,
      '#attributes' => [
        'id' => 'processstem_language'
      ]
    ];
    $form['processstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getProcessStem()->hasStatus === VSTOI::CURRENT || $this->getProcessStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getProcessStem()->hasVersion + 1 : $this->getProcessStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['processstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getProcessStem()->comment,
    ];

    $form['processstem_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getProcessStem()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];

    if ($this->getProcessStem()->wasDerivedFrom !== NULL) {
      $form['processstem_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      if ($this->getProcessStem()->wasDerivedFrom !== NULL) {
        $form['processstem_df_wrapper']['processstem_wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => $this->getProcessStem()->wasDerivedFrom,
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 0;",
            'disabled' => 'disabled',
          ],
        ];
      }

      $elementUri = Utils::namespaceUri($this->getProcessStem()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['processstem_df_wrapper']['processstem_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['processstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $this->getProcessStem()->wasGeneratedBy,
      '#attributes' => [
        'id' => 'processstem_was_generated_by'
      ],
      '#disabled' => ($this->getProcessStem()->wasGeneratedBy === Constant::WGB_ORIGINAL ? true:false)
    ];
    if ($this->getProcessStem()->hasReviewNote !== NULL && $this->getProcessStem()->hasStatus !== null) {
      $form['processstem_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getProcessStem()->hasReviewNote,
        '#disabled' => TRUE
      ];
      $form['processstem_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => \Drupal::currentUser()->getEmail(),
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
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
        'id' => 'cancel_button'
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
      if(strlen($form_state->getValue('processstem_content')) < 1) {
        $form_state->setErrorByName('processstem_content', $this->t('Please enter a valid Name'));
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

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getProcessStem()->hasStatus === VSTOI::CURRENT || $this->getProcessStem()->hasStatus === VSTOI::DEPRECATED) {

        $detectorStemJson = '{"uri":"'.Utils::uriGen('processstem').'",'.
          '"superUri":"'.Utils::uriFromAutocomplete($this->getProcessStem()->superUri).'",'.
          '"label":"'.$form_state->getValue('processstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
          '"comment":"'.$form_state->getValue('processstem_description').'",'.
          '"wasDerivedFrom":"'.$this->getProcessStem()->uri.'",'. //Previous Version is the New Derivation Value
          '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->detectorStemAdd($detectorStemJson);
        \Drupal::messenger()->addMessage(t("New Version Process Stem has been created successfully."));

      } else {

        $detectorStemJson = '{"uri":"'.$this->getProcessStem()->uri.'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($this->getProcessStem()->superUri).'",'.
        '"label":"'.$form_state->getValue('processstem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
        '"hasStatus":"'.$this->getProcessStem()->hasStatus.'",'.
        '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
        '"comment":"'.$form_state->getValue('processstem_description').'",'.
        '"hasWebDocument":"'.$form_state->getValue('processstem_webdocument').'",'.
        '"wasDerivedFrom":"'.$this->getProcessStem()->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
        '"hasReviewNote":"'.($this->getProcessStem()->hasStatus !== null ? $this->getProcessStem()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getProcessStem()->hasStatus !== null ? $this->getProcessStem()->hasEditorEmail : '').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->detectorStemDel($this->getProcessStemUri());
        $api->detectorStemAdd($detectorStemJson);
        \Drupal::messenger()->addMessage(t("Process Stem has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Process Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveProcessStem($detectorStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($detectorStemUri);
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
