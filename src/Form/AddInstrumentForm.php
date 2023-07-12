<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sir\Constant;
use Drupal\sir\Utils;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

class AddInstrumentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    $form['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
    ];
    $form['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => Constant::DEFAULT_INFORMANT,
    ];
    $form['instrument_instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
    ];
    $form['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => Constant::DEFAULT_LANGUAGE,
    ];
    $form['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['instrument_date_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Field (Optional)'),
    ];
    $form['instrument_subject_id_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject ID Field (Optional)'),
    ];
    $form['instrument_subject_relationship_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject Relationship Field (Optional)'),
    ];
    $form['instrument_page_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page Number (Optional)'),
    ];
    $form['instrument_copyright_notice'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Copyright notice (Optional)'),
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
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
      if(strlen($form_state->getValue('instrument_name')) < 1) {
        $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
        $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid abbreviation'));
      }
      if(strlen($form_state->getValue('instrument_language')) < 1) {
        $form_state->setErrorByName('instrument_language', $this->t('Please enter a valid language'));
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
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
      return;
    } 

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      $newInstrumentUri = Utils::uriGen('instrument');
      $instrumentJson = '{"uri":"'.$newInstrumentUri.'",'.
        '"typeUri":"'.VSTOI::QUESTIONNAIRE.'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasInstruction":"'.$form_state->getValue('instrument_instructions').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"comment":"'.$form_state->getValue('instrument_description').'",'.
        '"hasDateField":"'.$form_state->getValue('instrument_date_field').'",'.
        '"hasSubjectIDField":"'.$form_state->getValue('instrument_subject_id_field').'",'.
        '"hasSubjectRelationshipField":"'.$form_state->getValue('instrument_subject_relationship_field').'",'.
        '"hasPageNumber":"'.$form_state->getValue('instrument_page_number').'",'.
        '"hasCopyrightNotice":"'.$form_state->getValue('instrument_copyright_notice').'",'.
        '"hasSIRMaintainerEmail":"'.$useremail.'"}';

      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->instrumentAdd($instrumentJson);    
      \Drupal::messenger()->addMessage(t("Instruction has been added successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding instrument: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }

  }

}