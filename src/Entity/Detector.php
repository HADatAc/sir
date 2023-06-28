<?php

namespace Drupal\sir\Entity;

use Drupal\sir\Vocabulary\VSTOI;
use Kint\Kint;

class Detector {
  
    private string $uri = "";

    private string $typeUri = "";

    private string $hascoTypeUri = "";

    private string $label = "";

    private string $comment = "";

    private string $hasStatus = "";

    private string $serialNumber = "";

    private string $image = "";

    private string $isInstrumentAttachment = "";

    private string $hasContent = "";

    private string $hasPriority = "";

    private string $hasLanguage = "";

    private string $hasVersion = "";

    private string $hasSIRMaintainerEmail = "";

    private string $hasExperience = "";

  private function __construct() {
  }

  private function reset() {
    $this->uri = '';
    $this->typeUri = '';
    $this->hascoTypeUri = '';
    $this->label = '';
    $this->comment = '';
    $this->hasStatus = '';
    $this->serialNumber = '';
    $this->image = '';
    $this->isInstrumentAttachment = '';
    $this->hasContent = '';
    $this->hasPriority = '';
    $this->hasLanguage = '';
    $this->hasVersion = '';
    $this->hasSIRMaintainerEmail = '';
    $this->hasExperience = '';
  }

  private function assignInstance($instance_temp) {
    if (property_exists($instance_temp, 'uri')) {
      $this->uri = $instance_temp->getUri();
    }
    if (property_exists($instance_temp, 'typeUri')) {
      $this->typeUri = $instance_temp->getTypeUri();
    }
    if (property_exists($instance_temp, 'hascoTypeUri')) {
        $this->hascoTypeUri = $instance_temp->getHascoTypeUri();
    }
    if (property_exists($instance_temp, 'label')) {
      $this->label = $instance_temp->getLabel();
    }
    if (property_exists($instance_temp, 'comment')) {
      $this->comment = $instance_temp->getComment();
    }
    if (property_exists($instance_temp, 'hasStatus')) {
      $this->hasStatus = $instance_temp->getStatus();
    }
    if (property_exists($instance_temp, 'serialNumber')) {
      $this->serialNumber = $instance_temp->getSerialNumber();
    }
    if (property_exists($instance_temp, 'image')) {
      $this->image = $instance_temp->getImage();
    }
    if (property_exists($instance_temp, 'isInstrumentAttachment')) {
      $this->isInstrumentAttachment = $instance_temp->getIstInstrumentAttachment();
    }
    if (property_exists($instance_temp, 'hasContent')) {
      $this->hasContent = $instance_temp->getHasContent();
    }
    if (property_exists($instance_temp, 'hasPriority')) {
      $this->hasPriority = $instance_temp->getHasPriority();
    }
    if (property_exists($instance_temp, 'hasLanguage')) {
        $this->hasLanguage = $instance_temp->getHasLanguage();
    }
    if (property_exists($instance_temp, 'hasVersion')) {
        $this->hasVersion = $instance_temp->getHasVersion();
    }
    if (property_exists($instance_temp, 'hasSIRMaintainerEmail')) {
        $this->hasSIRMaintainerEmail = $instance_temp->getHasSIRMaintainerEmail();
    }
    if (property_exists($instance_temp, 'hasExperience')) {
        $this->hasExperience = $instance_temp->getHasExperience();
    }
  }

  public function getUri() {
    return $this->uri;
  }

  public function getTypeUri() {
    return $this->typeUri;
  }
  
  public function getLabel() {
    return $this->label;
  }
  
  public function getComment() {
    return $this->comment;
  }

  public function fromFormStateToArray($form_state, $uri, $instrument, $maintainerEmail) {
    $dataArray = [
        'uri' => $uri,
        'typeUri' => VSTOI::DETECTOR,
        'hascoTypeUri' => VSTOI::DETECTOR,
        'isInstrumentAttachment' => $instrument,
        'hasPriority' => $form_state->getValue('detector_priority'),
        'hasContent' => $form_state->getValue('detector_content'),
        'hasExperience' => $form_state->getValue('detector_experience'),
        'hasLanguage' => $form_state->getValue('detector_language'),
        'hasVersion' => $form_state->getValue('detector_version'),
        'comment' => $form_state->getValue('detector_description'),
        'hasSIRMaintainerEmail' => $maintainerEmail, 
    ];
    return $dataArray;
  }
  
  public function fromFormStateToJSON($form_state, $uri, $instrument, $maintainerEmail) {
    $dataJSON = '{"uri":"'.$uri.'",'.
        '"typeUri":"http://hadatac.org/ont/vstoi#Detector",'.
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#Detector",'.
        '"isInstrumentAttachment":"' . $instrument .'",'.
        '"hasPriority":"'.$form_state->getValue('detector_priority').'",'.
        '"hasContent":"'.$form_state->getValue('detector_content').'",'.
        '"hasExperience":"'.$form_state->getValue('detector_experience').'",'.
        '"hasLanguage":"'.$form_state->getValue('detector_language').'",'.
        '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
        '"comment":"'.$form_state->getValue('detector_description').'",'.
        '"hasSIRMaintainerEmail":"'.$maintainerEmail.'"}';
    return $dataJSON; 
  }

  /*
  public function setUri($uri) {
    if ($uri == null || $uri === "") {
        $this->message = "No URI has been provided to load a repository";
        $this->isSuccessful = FALSE;
        \Drupal::state()->delete(Repository::CURRENT_REPO);
        $this->reset();
    }
    $this->content = BrowseRepo::exec($uri);
    if ($this->content == null) {
      $this->message = "No response from URI " . $uri . ". The Repository may be down.";
      $this->isSuccessful = FALSE;
      \Drupal::state()->delete(Repository::CURRENT_REPO);
      $this->reset();
    } else {
      $obj = json_decode($this->content);
      if ($obj == null || !$obj->isSuccessful) {
        $this->message = "A response from " . $uri . " has been received. However, respose flags an error with the Repository.";
        $this->isSuccessful = FALSE;
        \Drupal::state()->delete(Repository::CURRENT_REPO);
        $this->reset();
      } else {
        $this->message = "Repository successfully loaded from [" . $uri . "]";
        $this->isSuccessful = TRUE;
        $this->bookmarkUri = $uri;
        foreach($obj->body as $key=>$value){
          if ($key == 'uri') {
            $this->uri = $value;
          } else if ($key == 'typeUri') {
            $this->typeUri = $value;
          } else if ($key == 'label') {
            $this->label = $value;
          } else if ($key == 'title') {
            $this->label = $value;
          } else if ($key == 'comment') {
            $this->comment = $value;
          } else if ($key == 'baseOntology') {
            $this->baseOntology = $value;
          } else if ($key == 'baseURL') {
            $this->baseURL = $value;
          } else if ($key == 'intitution') {
            $this->institutionUri = $value;
          } else if ($key == 'startedAt') {
            $this->startedAt = $value;
          }
        }
        \Drupal::state()->set(Repository::CURRENT_REPO, $this);
      }
    }
  }
  */
}