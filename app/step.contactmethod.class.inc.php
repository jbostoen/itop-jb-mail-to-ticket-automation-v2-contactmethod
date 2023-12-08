<?php

/**
 * @copyright   Copyright (C) 2019-2020 Jeffrey Bostoen
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2020-12-20 19:11:40
 *
 * 
 */
 
namespace jb_itop_extensions\mail_to_ticket;

// iTop internals
use \DBObjectSet;
use \DBObjectSearch;
use \EmailMessage;

// iTop classes
use \Person;
 
/**
 * Class StepFindCallerByContactMethod. Step to find the caller (Person) based on ContactMethod.
 *
 * Keep in mind: e-mail address might be shared by multiple people. This is only a basic implementation.
 *
 */
abstract class StepFindCallerByContactMethod extends Step {
	
	/**
	 * @inheritDoc
	 * @details Should be run before StepFindCaller; therefore $iPrecedence should be lower than that of StepFindCaller (110)
	 *
	 */
	public static $iPrecedence = 109; // Should be run before StepFindCaller; therefore $iPrecedence should be lower than that of StepFindCaller
	
	
	/**
	 * @inheritDoc 
	 * @details Checks if all information within the e-mail is compliant with the steps defined for this mailbox
	 */
	public static function Execute() {
		
		// Checking if there's an unknown caller
		
			// Don't even bother if jb-contactmethod is not enabled as an extension.
			if(class_exists('ContactMethod') == false) {
				static::Trace(".. Step not relevant: class ContactMethod does not exist.");
				return true;
			}
			
			if(isset(static::$oEmail->oInternal_Contact) == false || static::$oEmail->oInternal_Contact === null) {
				
				$oCaller = null;
				$sOQL = 'SELECT ContactMethod WHERE contact_method = "email" AND contact_detail LIKE :email';
				$sCallerEmail = static::$oEmail->sCallerEmail;
				$oSet_ContactMethod = new DBObjectSet(DBObjectSearch::FromOQL($sOQL), [], ['email' => $sCallerEmail]);
				
				switch($oSet_ContactMethod->Count()) {
					
					case 1:
						
						// Ok, the ContactMethod was found in iTop
						$oContactMethod = $oSet_ContactMethod->Fetch();
						static::Trace("... ContactMethod found: ID ".$oContactMethod->GetKey());
						
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
						static::Trace("... ContactMethod not found.");
						break;
					
					default:
					
						static::Trace("... Found {$oSet_ContactMethod->Count()} ContactMethod objects. Unhandled in this basic implementation.");
						break;
						
				}
		
				if($oCaller === null) {
					static::Trace("... Caller has not been identified (or without enough certainty).");
				}
		
				// Set caller for email
				static::$oEmail->oInternal_Contact = $oCaller;
		
			}
			else {
				static::Trace("... Caller already determined by previous step. Skip.");
			}
		
		
	}
	
}


/**
 * Class StepFindAdditionalContactsByContactMethod. Step to find the additional recipients (Person) based on ContactMethod.
 *
 * Keep in mind: e-mail address might be shared by multiple people. This is only a basic implementation.
 *
 */
abstract class StepFindAdditionalContactsByContactMethod extends Step {
	
	/**
	 * @inheritDoc
	 * @details Should be run before StepFindCaller; therefore $iPrecedence should be lower than that of StepFindCaller
	 *
	 */
	public static $iPrecedence = 114; // Should be run before StepFindAdditionalContacts; therefore $iPrecedence should be lower than that of StepFindAdditionalContacts (115)
	
	/**
	 * @inheritDoc
	 * @details Checks if all information within the e-mail is compliant with the steps defined for this mailbox
	 *
	 */
	public static function Execute() {
		
		// Checking if there's an unknown caller
		
			// Don't even bother if jb-contactmethod is not enabled as an extension.
			if(class_exists('ContactMethod') == false) {
				static::Trace(".. Step not relevant: class ContactMethod does not exist.");
				return true;
			}
			
			
			$oEmail = static::GetMail();
			$oMailBox = static::GetMailBox();
			$oTicket = static::GetTicket();
			
			$sCallerEmail = $oEmail->sCallerEmail;

			// Take both the To: and CC:
			$aAllContacts = array_merge($oEmail->aTos, $oEmail->aCCs);
			$aAllContacts = static::GetAddressesFromRecipients($aAllContacts);
			
			// Mailbox aliases
			$sMailBoxAliases = $oMailBox->Get('mail_aliases');
			$aMailBoxAliases = (trim($sMailBoxAliases) == '' ? [] : preg_split(NEWLINE_REGEX, $sMailBoxAliases));
			
			// Ignore helpdesk mailbox; any helpdesk mailbox aliases, original caller's email address
			if($oTicket !== null) {
				// For existing tickets: other people might reply. So only exclude mailbox aliases and the original caller.
				// If it's someone else replying, it should be seen as a new contact.
				$sOriginalCallerEmail = $oTicket->Get('caller_id->email');
				$aAllOtherContacts = array_udiff($aAllContacts, [$sOriginalCallerEmail, $oMailBox->Get('login')], $aMailBoxAliases, 'strcasecmp');
			}
			else {
				$aAllOtherContacts = array_udiff($aAllContacts, [$sCallerEmail, $oMailBox->Get('login')], $aMailBoxAliases, 'strcasecmp');
			}
			$aAllOtherContacts = array_unique($aAllOtherContacts);

			
			// For each recipient: try to look up the contact method, find the person object and update the person's e-mail.
			// Since the default Mail to Ticket Automation will try to match contacts based on the person's e-mail property, this is currently the way to go.
			foreach($aAllOtherContacts as $sCurrentEmail) {
				
				// Check if this contact exists.
				// Non-existing contacts must be created.
				// Actual linking of contacts happens after steps have been processed.
				$sContactQuery = 'SELECT ContactMethod WHERE contact_method = "email" AND contact_detail LIKE :email';
				$oSet_Methods = new DBObjectSet(DBObjectSearch::FromOQL($sContactQuery), [], [
					'email' => $sCurrentEmail
				]);
				
				static::Trace(".. Results (contact methods): {$oSet_Methods->Count()}");
				
				// Only if unique match
				if($oSet_Methods->Count() == 1) {
					
					$oMethod = $oSet_Methods->Fetch();
					
					$sPersonQuery = 'SELECT Person WHERE id = :id';
					$oSet_Persons = new DBObjectSet(DBObjectSearch::FromOQL($sPersonQuery), [], [
						'id' => $oMethod->Get('person_id')
					]);
					
					static::Trace(".. Results (linked person): {$oSet_Persons->Count()}");
					
					$oPerson = $oSet_Persons->Fetch();
					if($oPerson !== null) {
						
						static::Trace(".. Update person {$oPerson->Get('friendlyname')} - set e-mail to {$sCurrentEmail}");
						$oPerson->Set('email', $sCurrentEmail);
						$oPerson->DBUpdate();
						
					}
				
				}
				
			}
			
		
		
	}
	
}


