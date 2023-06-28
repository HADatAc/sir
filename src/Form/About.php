<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\URL;

class About extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return "sir_about";
        
    }


     /**
     * {@inheritdoc}
     */

     public function buildForm(array $form, FormStateInterface $form_state){

        $form['sir_home'] = [
            '#type' => 'label',
            '#title' => 'SIR environment debeloped by HADatAc.org.',
        ];
        $form['sir_newlines'] = [
            '#type' => 'label',
            '#title' => '<br><br><br>',
        ];
        $form['back'] = [
            '#type' => 'submit',
            '#value' => $this->t('Back'),
            '#name' => 'back',
        ];
      
        return $form;

     }
     
    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state) {
      $submitted_values = $form_state->cleanValues()->getValues();
      $triggering_element = $form_state->getTriggeringElement();
      $button_name = $triggering_element['#name'];

      if ($button_name === 'back') {
        $url = Url::fromRoute('sir.manage_instruments');
        $form_state->setRedirectUrl($url);
        return;
      } 

    }
}