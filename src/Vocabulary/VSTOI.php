<?php

  namespace Drupal\sir\Vocabulary;

  class VSTOI {

    const VSTOI                           = "http://hadatac.org/ont/vstoi#";

    /*
     *    CLASSES
     */

    const DETECTOR                        = VSTOI::VSTOI . "Detector";
    const INSTRUMENT                      = VSTOI::VSTOI . "Instrument";
    const QUESTIONNAIRE                   = VSTOI::VSTOI . "Questionnaire";
    const EXPERIENCE                      = VSTOI::VSTOI . "Experience";
    const ITEM                            = VSTOI::VSTOI . "Item";
    const PSYCHOMETRIC_QUESTIONNAIRE      = VSTOI::VSTOI . "PsychometricQuestionnaire";
    const RESPONSE_OPTION                 = VSTOI::VSTOI . "ResponseOption";

    /*
     *    PROPERTIES
     */

    const IS_INSTRUMENT_ATTACHMENT        = VSTOI::VSTOI . "isInstrumentAttachment";
    const HAS_PLATFORM                    = VSTOI::VSTOI . "hasPlatform";
    const HAS_SERIAL_NUMBER               = VSTOI::VSTOI . "hasSerialNumber";
    const HAS_WEB_DOCUMENTATION           = VSTOI::VSTOI . "hasWebDocumentation";
    const HAS_CONTENT                     = VSTOI::VSTOI . "hasContent";
    const HAS_EXPERIENCE                  = VSTOI::VSTOI . "hasExperience";
    const HAS_INSTRUCTION                 = VSTOI::VSTOI . "hasInstruction";
    const HAS_LANGUAGE                    = VSTOI::VSTOI . "hasLanguage";
    const HAS_PRIORITY                    = VSTOI::VSTOI . "hasPriority";
    const HAS_SHORT_NAME                  = VSTOI::VSTOI . "hasShortName";
    const HAS_STATUS                      = VSTOI::VSTOI . "hasStatus";
    const HAS_SIR_MAINTAINER_EMAIL        = VSTOI::VSTOI . "hasSIRMaintainerEmail";
    const HAS_VERSION                     = VSTOI::VSTOI . "hasVersion";
    const OF_EXPERIENCE                   = VSTOI::VSTOI . "ofExperience";


  }