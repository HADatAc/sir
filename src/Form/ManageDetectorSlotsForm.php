<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sir\Utils;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageDetectorSlotsForm extends FormBase {

  protected $instrumentUri;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_detectorslots_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

    # GET CONTENT
    $uri=$instrumenturi ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setInstrumentUri($uri_decode);

    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    // RETRIEVE INSTRUMENT BY URI
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawinstrument = $fusekiAPIservice->getUri($this->getInstrumentUri());
    $objinstrument = json_decode($rawinstrument);
    $instrument = NULL;
    if ($objinstrument->isSuccessful) {
      $instrument = $objinstrument->body;
    }

    // RETRIEVE DETECTOR_SLOTS BY INSTRUMENT
    $detectorslot_list = $fusekiAPIservice->detectorslotList($this->getInstrumentUri());
    $obj = json_decode($detectorslot_list);
    $detectorslots = [];
    if ($obj->isSuccessful) {
      $detectorslots = $obj->body;
    }

    if (sizeof($detectorslots) <= 0) {
      return new RedirectResponse(Url::fromRoute('sir.add_detectorslots', ['instrumenturi' => base64_encode($this->getInstrumentUri())])->toString());
    }

    #dpm($detectors);

    # BUILD HEADER

    $header = [
      'detectorslot_priority' => t('Priority'),
      'detectorslot_content' => t("Item Stem's Content"),
      'detectorslot_codebook' => t("Item's Codebook"),
      'detectorslot_detector' => t("Item's URI"),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($detectorslots as $detectorslot) {
      $detector = NULL;
      $content = "";
      $codebook = "";
      if ($detectorslot->hasDetector != null) {
        $rawdetector = $fusekiAPIservice->getUri($detectorslot->hasDetector);
        $objdetector = json_decode($rawdetector);
        if ($objdetector->isSuccessful) {
          $detector = $objdetector->body;
          if (isset($detector->detectorStem->hasContent)) {
            $content = $detector->detectorStem->hasContent;
          }
          if (isset($detector->codebook->label)) {
            $codebook = $detector->codebook->label;
          } 
        }
      }
      $detectorUriStr = "";
      if ($detectorslot->hasDetector != NULL && $detectorslot->hasDetector != '') {
        $detectorUriStr = Utils::namespaceUri($detectorslot->hasDetector);
      }
      $output[$detectorslot->uri] = [
        'detectorslot_priority' => $detectorslot->hasPriority,     
        'detectorslot_content' => $content,     
        'detectorslot_codebook' => $codebook,
        'detectorslot_detector' => $detectorUriStr     
      ];
    }

    # PUT FORM TOGETHER

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>DetectorSlots of Instrument <font color="DarkGreen">' . $instrument->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>DetectorSlots maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['edit_selected_detectorslot'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected DetectorSlot'),
      '#name' => 'edit_detectorslot',
    ];
    $form['delete_detectorslots'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete All DetectorSlots'),
      '#name' => 'delete_detectorslots',
    ];
    $form['detectorslot_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
      //'#ajax' => [
      //  'callback' => '::detectorslotAjaxCallback', 
      //  'disable-refocus' => FALSE, 
      //  'event' => 'change',
      //  'wrapper' => 'edit-output', 
      //  'progress' => [
      //    'type' => 'throbber',
      //    'message' => $this->t('Verifying entry...'),
      //  ],
      //]    
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
  }

  public function detectorslotAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('detectorslot_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }
  }

  /**
   * {@inheritdoc}
   */   
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];
  
    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('detectorslot_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // EDIT DETECTOR_SLOT
    if ($button_name === 'edit_detectorslot') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact detectorslot to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one detectorslot to edit. No more than one detectorslot can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_detectorslot');
        $url->setRouteParameter('detectorsloturi', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE DETECTOR_SLOTS
    if ($button_name === 'delete_detectorslots') {
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->detectorslotDel($this->getInstrumentUri());
    
      \Drupal::messenger()->addMessage(t("DetectorSlots has been deleted successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }  
  }
  
}