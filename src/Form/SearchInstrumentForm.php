<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use BorderCloud\SPARQL\SparqlClient;

class SearchInstrumentForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_instrument_form';
  }

    /**
     * {@inheritdoc}
     */

     protected function getEditableConfigNames() {
      return [
          static::CONFIGNAME,
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    

    
    $form['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];
    $form['instrument_language'] = [
        '#type' => 'select',
        '#title' => $this->t('Language'),
        '#options' => [
          'en' => $this->t('English'),
          'pt' => $this->t('Portuguese'),
        ],
      ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if(strlen($form_state->getValue('instrument_name')) < 1) {
      $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name for the Questionnaire'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    try{
     

        $config = $this->config(static::CONFIGNAME);      
        $sir_home = $config->get("sir_home");
      
        $endpoint = $config->get("api_url")."/sir/query";

        $sc = new SparqlClient();

        $sc->setEndpointRead($endpoint);
        $sc->setMethodHTTPRead("GET");
        
        $q = "
        SELECT ?iname ?ilabel ?idesc ?s
WHERE {
  ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual> .
  ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://hadatac.org/ont/hasco/Questionnaire> .
  ?s <http://www.w3.org/2000/01/rdf-schema#label> ?iname .
  ?s <http://hadatac.org/ont/hasco/hasShortName> ?ilabel .
  ?s <http://www.w3.org/2000/01/rdf-schema#comment> ?idesc .
}
LIMIT 250
        ";

        $rows = $sc->query($q, 'rows');
        $err = $sc->getErrors();
      
        $size = sizeof($rows["result"]["rows"]);
       
        if($size > 0)
        {
            echo "<table class='table' border='1'>";
            foreach ($rows["result"]["rows"] as $row) {
                echo "<tr>";
                echo "<th>".$row['ilabel']."</th>";
                echo "<td>".$row['iname']."</td></tr>";
            }
            echo "<table>";
        }
        else
        {
            return[
                '#type' => 'markup',
                '#markup' => $this->t("No questionnaire registered yet")
                ];
        }


    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the data."));
    }
   
  

  }
}