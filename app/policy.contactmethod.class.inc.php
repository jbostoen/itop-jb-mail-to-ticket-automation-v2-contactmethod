<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-12-20 19:11:40
 *
 * Classes implementing iPolicy. These policies use ContactMethod objects to find Contacts (caller, additional contacts)
 * 
 */
 
namespace jb_itop_extensions\mail_to_ticket;

// iTop internals
use \DBObjectSet;
use \DBObjectSearch;
use \EmailMessage;

// iTop classes
use \Person;
use \utils;
 
/**
 * Class PolicyFindCallerByContactMethod Offers a policy to find the caller (Person) based on ContactMethod.
 *
 * Keep in mind: e-mail address might be shared by multiple people. This is only a basic implementation.
 *
 */
abstract class PolicyFindCallerByContactMethod extends Policy implements iPolicy {
	
	/**
	 * @var \Integer $iPrecedence It's not necessary that this number is unique; but when all policies are listed; they will be sorted ascending (intended to make sure some checks run first; before others).
	 * @details Should be run before PolicyFindCaller; therefore $iPrecedence should be lower than that of PolicyFindCaller
	 *
	 */
	public static $iPrecedence = 109;
	
	/**
	 * @var \String $sPolicyId Shortname for policy
	 */
	public static $sPolicyId = 'policy_find_caller_by_contact_method';
	
	/**
	 * Checks if all information within the e-mail is compliant with the policies defined for this mailbox
	 *
	 * @return boolean Whether this is compliant with a specified policy. Returning 'false' blocks further processing.
	 */
	public static function IsCompliant() {
		
		// Note: even if a caller is NOT found, this method should always return "true".
		// Further processing will use the default PolicyFindCaller method which will block further processing if truly necessary.
				
		// Generic 'before' actions
		parent::BeforeComplianceCheck();
		
		// Checking if there's an unknown caller
		
			// Don't even bother if jb-contactmethod is not enabled as an extension.
			if(class_exists('ContactMethod') == false) {
				self::Trace(".. Policy not relevant: class ContactMethod does not exist.");
				return true;
			}
			
			if(isset(self::$oEmail->oInternal_Contact) == false || self::$oEmail->oInternal_Contact === null) {
				
				$oCaller = null;
				$sOQL = 'SELECT ContactMethod WHERE contact_method = "email" AND contact_detail LIKE :email';
				$sCallerEmail = self::$oEmail->sCallerEmail;
				$oSet_ContactMethod = new DBObjectSet(DBObjectSearch::FromOQL($sOQL), [], ['email' => $sCallerEmail]);
				
				switch($oSet_ContactMethod->Count()) {
					
					case 1:
						
						// Ok, the ContactMethod was found in iTop
						$oContactMethod = $oSet_ContactMethod->Fetch();
						self::Trace("... ContactMethod found: ID ".$oContactMethod->GetKey());
						
						$sOQL = 'SELECT Person WHERE id = :id';
						$oSet_Person = new DBObjectSet(DBObjectSearch::FromOQL($sOQL), [], ['id' => $oContactMethod->Get('person_id')]);
						$oCaller = $oSet_Person->Fetch();
						
						// Person was found. But is this already the primary e-mail address?
						// This is necessary to respond to the e-mail address which was used last by the caller
						$oCaller->Set('email', $oContactMethod->Get('contact_detail'));
						$oCaller->DBUpdate();
						
						break;
						
					case 0:
		
						// ContactMethod was not found.
						self::Trace("... ContactMethod not found.");
						break;
					
					default:
					
						self::Trace("... Found {$oSet_ContactMethod->Count()} ContactMethod objects. Unhandled in this basic implementation.");
						break;
						
				}
		
				if($oCaller === null) {
					self::Trace("... Caller has not been identified (or without enough certainty).");
				}
		
				// Set caller for email
				self::$oEmail->oInternal_Contact = $oCaller;
		
			}
			else {
				self::Trace("... Caller already determined by previous policy. Skip.");
			}
		
		// Generic 'after' actions
		parent::AfterPassedComplianceCheck();
		
		return true;
		
	}
	
}

