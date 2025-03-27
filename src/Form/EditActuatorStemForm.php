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
use Drupal\rep\Vocabulary\REPGUI;

class EditActuatorStemForm extends FormBase {

  protected $actuatorStemUri;

  protected $actuatorStem;

  protected $sourceActuatorStem;

  public function getActuatorStemUri() {
    return $this->actuatorStemUri;
  }

  public function setActuatorStemUri($uri) {
    return $this->actuatorStemUri = $uri;
  }

  public function getActuatorStem() {
    return $this->actuatorStem;
  }

  public function setActuatorStem($obj) {
    return $this->actuatorStem = $obj;
  }

  public function getSourceActuatorStem() {
    return $this->sourceActuatorStem;
  }

  public function setSourceActuatorStem($obj) {
    return $this->sourceActuatorStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_actuatorstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $actuatorstemuri = NULL) {
    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_actuatorstem';

    $uri=$actuatorstemuri;
    $uri_decode=base64_decode($uri);
    $this->setActuatorStemUri($uri_decode);

    $this->setActuatorStem($this->retrieveActuatorStem($this->getActuatorStemUri()));
    if ($this->getActuatorStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Actuator."));
      self::backUrl();
      return;
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    // IN CASE ITS A DERIVATION ORIGINAL MUST BE REMOVED ALSO
    if ($this->getActuatorStem()->hasStatus === VSTOI::CURRENT || $this->getActuatorStem()->hasVersion > 1) {
      unset($derivations[Constant::DEFAULT_WAS_GENERATED_BY]);
    }

    $languages = ['' => $this->t('Select one please')] + $languages;
    $derivations = ['' => $this->t('Select one please')] + $derivations;

    // dpm($this->getActuatorStem());
    $form['actuatorstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getActuatorStemUri()).'">'.$this->getActuatorStemUri().'</a>'),
    ];
    if ($this->getActuatorStem()->superUri) {
      $form['actuatorstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="pt-3 col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Parent Type'),
          '#name' => 'actuatorstem_type',
          '#default_value' => $this->getActuatorStem()->superUri ? Utils::fieldToAutocomplete($this->getActuatorStem()->superUri, $this->getActuatorStem()->superClassLabel) : '',
          '#id' => 'actuatorstem_type',
          '#parents' => ['actuatorstem_type'],
          '#attributes' => [
            'class' => ['open-tree-modal'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 800]),
            'data-url' => Url::fromRoute('rep.tree_form', [
              'mode' => 'modal',
              'elementtype' => 'actuatorstem',
            ], ['query' => ['field_id' => 'actuatorstem_type']])->toString(),
            'data-field-id' => 'actuatorstem_type',
            'data-elementtype' => 'actuatorstem',
            'data-search-value' => $this->getActuatorStem()->superUri ?? '',
          ],
        ],
        'bottom' => [
          '#type' => 'markup',
          '#markup' => '</div>',
        ],
      ];
    }

    $form['actuatorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getActuatorStem()->hasContent,
    ];
    $form['actuatorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getActuatorStem()->hasLanguage,
      '#attributes' => [
        'id' => 'actuatorstem_language'
      ]
    ];
    $form['actuatorstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getActuatorStem()->hasStatus === VSTOI::CURRENT || $this->getActuatorStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getActuatorStem()->hasVersion + 1 : $this->getActuatorStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['actuatorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getActuatorStem()->comment,
    ];

    $form['actuatorstem_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getActuatorStem()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];

    if ($this->getActuatorStem()->wasDerivedFrom !== NULL) {
      $form['actuatorstem_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      if ($this->getActuatorStem()->wasDerivedFrom !== NULL) {
        $form['actuatorstem_df_wrapper']['actuatorstem_wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => $this->getActuatorStem()->wasDerivedFrom,
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 0;",
            'disabled' => 'disabled',
          ],
        ];
      }

      $elementUri = Utils::namespaceUri($this->getActuatorStem()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['actuatorstem_df_wrapper']['actuatorstem_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['actuatorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $this->getActuatorStem()->wasGeneratedBy,
      '#attributes' => [
        'id' => 'actuatorstem_was_generated_by'
      ],
      '#disabled' => ($this->getActuatorStem()->wasGeneratedBy === Constant::WGB_ORIGINAL ? true:false)
    ];
    if ($this->getActuatorStem()->hasReviewNote !== NULL && $this->getActuatorStem()->hasStatus !== null) {
      $form['actuatorstem_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getActuatorStem()->hasReviewNote,
        '#disabled' => TRUE
      ];
      $form['actuatorstem_haseditoremail'] = [
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
      if(strlen($form_state->getValue('actuatorstem_content')) < 1) {
        $form_state->setErrorByName('actuatorstem_content', $this->t('Please enter a valid Name'));
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
      if ($this->getActuatorStem()->hasStatus === VSTOI::CURRENT || $this->getActuatorStem()->hasStatus === VSTOI::DEPRECATED) {

        $actuatorStemJson = '{"uri":"'.Utils::uriGen('actuatorstem').'",'.
          '"superUri":"'.Utils::uriFromAutocomplete($this->getActuatorStem()->superUri).'",'.
          '"label":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('actuatorstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('actuatorstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('actuatorstem_version').'",'.
          '"comment":"'.$form_state->getValue('actuatorstem_description').'",'.
          '"wasDerivedFrom":"'.$this->getActuatorStem()->uri.'",'. //Previous Version is the New Derivation Value
          '"wasGeneratedBy":"'.$form_state->getValue('actuatorstem_was_generated_by').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->actuatorStemAdd($actuatorStemJson);
        \Drupal::messenger()->addMessage(t("New Version Actuator Stem has been created successfully."));

      } else {

        $actuatorStemJson = '{"uri":"'.$this->getActuatorStem()->uri.'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($this->getActuatorStem()->superUri).'",'.
        '"label":"'.$form_state->getValue('actuatorstem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
        '"hasStatus":"'.$this->getActuatorStem()->hasStatus.'",'.
        '"hasContent":"'.$form_state->getValue('actuatorstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('actuatorstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('actuatorstem_version').'",'.
        '"comment":"'.$form_state->getValue('actuatorstem_description').'",'.
        '"hasWebDocument":"'.$form_state->getValue('actuatorstem_webdocument').'",'.
        '"wasDerivedFrom":"'.$this->getActuatorStem()->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('actuatorstem_was_generated_by').'",'.
        '"hasReviewNote":"'.($this->getActuatorStem()->hasStatus !== null ? $this->getActuatorStem()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getActuatorStem()->hasStatus !== null ? $this->getActuatorStem()->hasEditorEmail : '').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->actuatorStemDel($this->getActuatorStemUri());
        $api->actuatorStemAdd($actuatorStemJson);
        \Drupal::messenger()->addMessage(t("Actuator Stem has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Actuator Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveActuatorStem($actuatorStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($actuatorStemUri);
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
