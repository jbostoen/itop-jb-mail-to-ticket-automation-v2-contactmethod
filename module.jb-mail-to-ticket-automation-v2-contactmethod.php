<?php

/**
 * @copyright   Copyright (C) 2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-12-30 11:19:16
 *
 * iTop module definition file
 */

SetupWebPage::AddModule(
        __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
        'jb-mail-to-ticket-automation-v2-contactmethod/2.6.201230',
        array(
                // Identification
                //
                'label' => 'Feature: Mail to Ticket Automation - find caller by contact method',
                'category' => 'business',

                // Setup
                //
                'dependencies' => array( 
					'jb-contactmethod/2.7.0',
					'jb-itop-standard-email-synchro/2.7.231201',
                ),
                'mandatory' => false,
                'visible' => true,

                // Components
                //
                'datamodel' => array(
					'model.jb-mail-to-ticket-automation-v2-contactmethod.php',
					'app/step.contactmethod.class.inc.php'
                ),
                'webservice' => array(

                ),
                'data.struct' => array(
					// add your 'structure' definition XML files here,
                ),
                'data.sample' => array(
					// add your sample data XML files here,
                ),

                // Documentation
                //
                'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
                'doc.more_information' => '', // hyperlink to more information, if any

                // Default settings
                //
                'settings' => array(
                        // Module specific settings go here, if any
                ),
        )
);

