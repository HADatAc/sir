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

class EditAnnotationStemForm extends FormBase {

  protected $annotationStemUri;

  protected $annotationStem;

  protected $sourceAnnotationStem;

  public function getAnnotationStemUri() {
    return $this->annotationStemUri;
  }

  public function setAnnotationStemUri($uri) {
    return $this->annotationStemUri = $uri;
  }

  public function getAnnotationStem() {
    return $this->annotationStem;
  }

  public function setAnnotationStem($obj) {
    return $this->annotationStem = $obj;
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
  public function getFormId() {
    return 'edit_annotationstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $annotationstemuri = NULL) {
    $uri=$annotationstemuri;
    $uri_decode=base64_decode($uri);
    $this->setAnnotationStemUri($uri_decode);


    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setAnnotationStem($this->retrieveAnnotationStem($this->getAnnotationStemUri()));
    if ($this->getAnnotationStem() == NULL) {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Annotation Stem."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('annotationstem'));
    } else {
      $wasGeneratedBy = $this->getAnnotationStem()->wasGeneratedBy;
      if ($this->getAnnotationStem()->wasDerivedFrom != NULL) {
        $this->setSourceAnnotationStem($this->retrieveAnnotationStem($this->getAnnotationStem()->wasDerivedFrom));
        if ($this->getSourceAnnotationStem() != NULL && $this->getSourceAnnotationStem()->hasContent != NULL) {
          $sourceContent = $this->getSourceAnnotationStem()->hasContent;
        }
      }
    }

    //dpm($this->getAnnotation());

    $form['annotationstem_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getAnnotationStem()->hasContent,
    ];
    $form['annotationstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getAnnotationStem()->hasLanguage,
    ];
    $form['annotationstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getAnnotationStem()->hasVersion,
    ];
    $form['annotationstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getAnnotationStem()->comment,
    ];
    $form['annotationstem_was_derived_from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Was Derived From'),
      '#default_value' => $sourceContent,
      '#disabled' => TRUE,
    ];
    $form['annotationstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
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

    try{

      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $wasDerivedFrom = '';
      if ($this->getSourceAnnotationStem() === NULL || $this->getSourceAnnotationStem()->uri === NULL) {
        $wasDerivedFrom = 'null';
      } else {
        $wasDerivedFrom = $this->getSourceAnnotationStem()->uri;
      }

      $annotationStemJson = '{"uri":"'.$this->getAnnotationStem()->uri.'",'.
        '"typeUri":"'.VSTOI::ANNOTATION_STEM.'",'.
        '"hascoTypeUri":"'.VSTOI::ANNOTATION_STEM.'",'.
        '"hasStatus":"'.$this->getAnnotationStem()->hasStatus.'",'.
        '"hasContent":"'.$form_state->getValue('annotationstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('annotationstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('annotationstem_version').'",'.
        '"comment":"'.$form_state->getValue('annotationstem_description').'",'.
        '"wasDerivedFrom":"'.$wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('annotationstem_was_generated_by').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->annotationStemDel($this->getAnnotationStemUri());
      $updatedAnnotationStem = $api->annotationStemAdd($annotationStemJson);
      \Drupal::messenger()->addMessage(t("Annotation Stem has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Annotation Stem: ".$e->getMessage()));
      self::backUrl();
      return;    }
  }

  public function retrieveAnnotationStem($annotationStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($annotationStemUri);
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
