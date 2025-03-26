<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class ReviewProcessStemForm extends FormBase {

  protected $processStemUri;

  protected $processStem;

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
    return 'review_processstem_form';
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
          $sourceContent = Utils::fieldToAutocomplete($this->getSourceProcessStem()->uri,$this->getSourceProcessStem()->hasContent);
        }
      }
    }

    //dpm($this->getProcess());
    if ($this->getProcessStem()->superUri) {
      $form['processstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="pt-3 col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Type'),
          '#name' => 'processstem_type',
          '#default_value' => $this->getProcessStem()->superUri ? Utils::fieldToAutocomplete($this->getProcessStem()->superUri, $this->getProcessStem()->superClassLabel) : '',
          '#disabled' => TRUE,
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
      '#disabled' => TRUE,
    ];
    $form['processstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getProcessStem()->hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['processstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getProcessStem()->hasVersion,
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
      '#disabled' => TRUE,
    ];
    $form['processstem_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getProcessStem()->hasWebDocument,
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
    ];
    if ($this->getProcessStem()->wasDerivedFrom !== NULL) {
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getProcessStem()->wasDerivedFrom);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $result = $obj->body;

        $form['processstem__df_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
            'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
          ],
        ];

        $form['processstem__df_wrapper']['processstem__wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => Utils::fieldToAutocomplete($this->getProcessStem()->wasDerivedFrom, $result->label),
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 1045px;",
            'disabled' => 'disabled',
          ],
        ];

        $elementUri = Utils::namespaceUri($this->getProcessStem()->wasDerivedFrom);
        $elementUriEncoded = base64_encode($elementUri);
        $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

        $form['processstem__df_wrapper']['processstem__wasderivedfrom_button'] = [
          '#type' => 'markup',
          '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
        ];
      }
    }

    $form['processstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
      '#disabled' => TRUE,
    ];

    $form['processstem_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getProcessStem()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['processstem_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getProcessStem()->hasReviewNote,
    ];
    $form['processstem_haseditoremail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reviewer Email'),
      '#default_value' => \Drupal::currentUser()->getEmail(),
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    $form['review_approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Approve'),
      '#name' => 'review_approve',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to Approve?")){return false;}',
        'class' => ['btn', 'btn-success', 'aprove-button'],
      ],
    ];
    $form['review_reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#name' => 'review_reject',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to Reject?")){return false;}',
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br>'),
    ];
    $form['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
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

    // if ($button_name != 'back') {
    //   if ($button_name === 'review_reject') {
    //     if(strlen($form_state->getValue('process_hasreviewnote')) < 1) {
    //       $form_state->setErrorByName('process_hasreviewnote', $this->t('You must enter a Reject Note'));
    //     }
    //   }
    // }
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('processstem_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getProcessStem();

      //APROVE
      if ($button_name !== 'review_reject') {

        $processStemJson = '{"uri":"'.$this->getProcessStem()->uri.'",'.
          '"superUri":"'.$this->getProcessStem()->superUri.'",'.
          '"label":"'.$this->getProcessStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasContent":"'.$this->getProcessStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getProcessStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getProcessStem()->hasVersion.'",'.
          '"comment":"'.$this->getProcessStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getProcessStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getProcessStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('processstem_hasreviewnote').'",'.
          '"hasWebDocument":"'.$form_state->getValue('processstem_webdocument').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getProcessStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->processStemDel($this->getProcessStemUri());
        $api->processStemAdd($processStemJson);

        // IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED, but in this case version must be also greater than 1, because
        // Process Stems can start to be like a derivation element by itself
        if (($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') && $result->hasVersion > 1) {
          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentProcessStemJson = '{"uri":"'.$resultParent->uri.'",'.
          (!empty($resultParent->superUri) ? '"superUri":"'.$resultParent->superUri.'",' : '').
          '"label":"'.$resultParent->label.'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
          '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
          '"hasContent":"'.$resultParent->hasContent.'",'.
          '"hasLanguage":"'.$resultParent->hasLanguage.'",'.
          '"hasVersion":"'.$resultParent->hasVersion.'",'.
          '"comment":"'.$resultParent->comment.'",'.
          (!empty($resultParent->wasDerivedFrom) ? '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",' : '').
          '"wasGeneratedBy":"'.$resultParent->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
          '"hasWebDocument":"'.$resultParent->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'"}';

          // UPDATE BY DELETING AND CREATING
          $api->processStemDel($resultParent->uri);
          $api->processStemAdd($parentProcessStemJson);
        }

        \Drupal::messenger()->addMessage(t("Process Stem has been updated successfully."));
      // REJECT
      } else {

        $processStemJson = '{"uri":"'.$this->getProcessStem()->uri.'",'.
          '"superUri":"'.$this->getProcessStem()->superUri.'",'.
          '"label":"'.$this->getProcessStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$this->getProcessStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getProcessStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getProcessStem()->hasVersion.'",'.
          '"comment":"'.$this->getProcessStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getProcessStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getProcessStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('processstem_hasreviewnote').'",'.
          '"hasWebDocument":"'.$form_state->getValue('processstem_webdocument').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getProcessStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->processStemDel($this->getProcessStemUri());
        $api->processStemAdd($processStemJson);
      }

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
