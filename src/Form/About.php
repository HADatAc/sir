<?php

/**
 * @file
 * Contains the settings for admninistering the SIR Module
 */

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\URL;
use Drupal\sir\Utils;
use Drupal\sir\ListKeywordLanguagePage;
use Drupal\sir\Entity\Tables;

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
            '#type' => 'item',
            '#title' => '<h3>About this website</h3>' . 
                'This is an instance of the <a href="http://hadatac.org/sir/">Semantic Instrument Repository (SIR)</a> environment ' . 
                'developed by <a href="http://hadatac.org/">HADatAc.org</a> community.<br>',
        ];
        $form['sir_content1'] = [
            '#type' => 'item',
            '#title' => 'This repository currently hosts a knowledge graph about the following:<br>',
        ];
        $totals = '<ul>';
        $totals .= '<li> ' . About::total('instrument') . ' <a href="'.Utils::selectBackUrl('instrument')->toString().'">instrument(s)</a></li>';
        $totals .=  '<li> ' . About::total('detector') . ' <a href="'.Utils::selectBackUrl('detector')->toString().'">detector(s)</a></li>';
        $totals .=  '<li> ' . About::total('experience') . ' <a href="'.Utils::selectBackUrl('experience')->toString().'">experience(s)</a></li>';
        $totals .=  '<li> ' . About::total('responseoption') . ' <a href="'.Utils::selectBackUrl('responseoption')->toString().'">response option(s)</a></li>';
        $totals .= '</ul>';
        $form['sir_content_totals'] = [
            '#type' => 'item',
            '#title' => $totals,
        ];
        $form['sir_content2'] = [
            '#type' => 'item',
            '#title' => 'In this instance, the knowledge graph is based on content coming from the following ontologies:<br>',
        ];
        $ontologies = '<ul>';
        $tables = new Tables;
        $namespaces = $tables->getNamespaces();
        foreach ($namespaces as $abbrev => $ns) {
            $ontologies .= '<li><a href="'. $ns .'">'. $ns . '</a> ('. $abbrev . ')</li>';
        }
        $ontologies .= '</ul>';
        $form['sir_ontologies_totals'] = [
            '#type' => 'item',
            '#title' => $ontologies,
        ];
        $form['sir_newline1'] = [
            '#type' => 'item',
            '#title' => '<br><br>',
        ];
        $form['back'] = [
            '#type' => 'submit',
            '#value' => $this->t('Back'),
            '#name' => 'back',
        ];
        $form['sir_newline2'] = [
            '#type' => 'item',
            '#title' => '<br><br><br>',
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
        $url = Url::fromRoute('sir.index');
        $form_state->setRedirectUrl($url);
        return;
      } 

    }

    public static function total($elementtype) {
        return ListKeywordLanguagePage::total($elementtype, NULL, NULL);
    }

}