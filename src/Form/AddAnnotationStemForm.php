<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddAnnotationStemForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_annotationstem_form';
  }

  protected $sourceAnnotationStemUri;

  protected $sourceAnnotationStem;

  public function getSourceAnnotationStemUri() {
    return $this->sourceAnnotationStemUri;
  }

  public function setSourceAnnotationStemUri($uri) {
    return $this->sourceAnnotationStemUri = $uri;
  }

  public function getSourceAnnotationStem() {
    return $this->sourceAnnotationStem;
  }

  public function setSourceAnnotationStem($obj) {
    return $this->sourceAnnotationStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourceannotationstemuri = NULL) {

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE ANNOTATION STEM,  IF ANY
    $sourceuri=$sourceannotationstemuri;
    if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
      $this->setSourceAnnotationStem(NULL);
      $this->setSourceAnnotationStemUri('');
    } else {
      $sourceuri_decode=base64_decode($sourceuri);
      $this->setSourceAnnotationStemUri($sourceuri_decode);
      $rawresponse = $api->getUri($this->getSourceAnnotationStemUri());
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setSourceAnnotationStem($obj->body);
      } else {
        $this->setSourceAnnotationStem(NULL);
        $this->setSourceAnnotationStemUri('');
      }
    }
    $disabledDerivationOption = ($this->getSourceAnnotationStem() === NULL);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    if ($this->getSourceAnnotationStem() != NULL) {
      $sourceContent = $this->getSourceAnnotationStem()->hasContent;
    }

    $form['annotationstem_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
    ];
    $form['annotationstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['annotationstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['annotationstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['annotationstem_was_derived_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Was Derived From'),
      '#default_value' => $sourceContent,
      '#disabled' => TRUE,
    ];
    $form['annotationstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Generated By'),
      '#options' => $derivations,
      '#default_value' => Constant::DEFAULT_WAS_GENERATED_BY,
      '#disabled' => $disabledDerivationOption,
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('annotationstem_content')) < 1) {
        $form_state->setErrorByName('annotationstem_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('annotationstem_language')) < 1) {
        $form_state->setErrorByName('annotationstem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('annotationstem_version')) < 1) {
        $form_state->setErrorByName('annotationstem_version', $this->t('Please enter a valid version'));
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

    try {

      $wasDerivedFrom = '';
      if ($this->getSourceAnnotationStemUri() === NULL) {
        $wasDerivedFrom = 'null';
      } else {
        $wasDerivedFrom = $this->getSourceAnnotationStemUri();
      }
      $wasGeneratedBy = $form_state->getValue('annotationstem_was_generated_by');

      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW ANNOTATION
      $newAnnotationStemUri = Utils::uriGen('annotationstem');
      $annotationStemJson = '{"uri":"'.$newAnnotationStemUri.'",'.
        '"typeUri":"'.VSTOI::ANNOTATION_STEM.'",'.
        '"hascoTypeUri":"'.VSTOI::ANNOTATION_STEM.'",'.
        '"hasContent":"'.$form_state->getValue('annotationstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('annotationstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('annotationstem_version').'",'.
        '"comment":"'.$form_state->getValue('annotationstem_description').'",'.
        '"wasDerivedFrom":"'.$wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$wasGeneratedBy.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';
      $api = \Drupal::service('rep.api_connector');
      $message = $api->annotationStemAdd($annotationStemJson);
      \Drupal::messenger()->addMessage(t("Added a new Annotation Stem with URI: ".$newAnnotationStemUri));
      self::backUrl();
      return;

    } catch(\Exception $e) {
        \Drupal::messenger()->addMessage(t("An error occurred while adding the Annotation Stem: ".$e->getMessage()));
        self::backUrl();
        return;
      }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_annotationstem');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
