<?php
/**
 Copyright (C) 2018-2020 KANOUN Salim
 This program is free software; you can redistribute it and/or modify
 it under the terms of the Affero GNU General Public v.3 License as published by
 the Free Software Foundation;
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 Affero GNU General Public Public for more details.
 You should have received a copy of the Affero GNU General Public Public along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

/**
 * Abstract class to handle review in the system
 * Each Study-Visit should extend this abstract class and redifine the two abstract methods
 * - saveSpecificForm : Which recieve the raw form and should write data to the specific table of database
 * - setVisitValidation : Which should define the rules to change status of review's visit (ex : Wait Adjudication or Done)
 * 
 * These two methodes will be launched for each new review recieved by the system
 * 
 * The makeReviewUnavailable() can be overrided to let the visit available for review even if Review status "Done" is reached 
 * (by default it will no longer accept new review after reaching this status)
 * @author salim
 *
 */

abstract class Form_Processor {
	
	protected $linkpdo;
	protected $visitObject;
	protected $study;
	protected $id_visit;
	protected $specificTable;
	protected $local;
	private $username;
	protected $reviewStatus;
	protected $reviewAvailable;
	public $reviewObject;
	protected $rawDataForm;
	
	function __construct(Visit $visitObject, bool $local, string $username, PDO $linkpdo) {
		$this->linkpdo=$linkpdo;
		$this->local=$local;
		$this->username=$username;
		
		$this->visitObject=$visitObject;
		$this->id_visit=$visitObject->id_visit;
		$visitCharacteristics=$visitObject->getVisitCharacteristics();
		
		//Store the table specific name and the current study
		$this->specificTable=$visitCharacteristics->tableReviewSpecificName;
		$this->study=$visitObject->study;
		//Store the review status
		$this->reviewStatus=$visitObject->reviewStatus;
		$this->reviewAvailable=$visitObject->reviewAvailable;

		$this->loadSavedForm();
	}
	
	/**
	 * Save the  form in the specific table as specified in the child dedicated objet who will extend this class
	 * @param $data :Post data from the dedicated from
	 * @param $id_Review : current Id review
	 * @param $specificTable : name of the specific table for the study-visit
	 * @param $update : if review already exists (draft), value is true (if true make update, if false make insert)
	 */
	abstract protected function saveSpecificForm($data, $id_review, $update);
	
	/**
	 * Set the visit status after review (Not Done, adjudication, Done), need to be redifined in the child object
	 */
	abstract public function setVisitValidation();
	
	/*
	 * Create new entry in review table
	 */
	protected function createReview() {
		$this->reviewObject=Review::createReview($this->id_visit, $this->username, $this->local,
			($this->reviewStatus == Visit::REVIEW_WAIT_ADJUDICATION), $this->linkpdo);
	}
	
	/**
	 * Set review status as draft (validate = false) or validated (validate=true)
	 * This methods is triggered by the system at form submission and call the save specific form
	 * to define which value need to be written in the specific table
	 */
	public function saveForm($data, bool $validate) {
	    
		$this->rawDataForm=$data;
	    
		//If reviewer check that review is available before saving process
		if (!$this->local && !$this->reviewAvailable) {
			return;
		}
		
		//Get saved form, return either local form or reviewer's users form if exist
		//or null if not existing
		
		if (empty($this->reviewObject)) {
			$this->createReview();
			$update=false;       
		}else {
			$update=true;
			//If already existing validated review, exit without modifying anything
			if ($this->reviewObject->validated) {
				return;
			}
	       
		}
	    
		//Call the child redifined save specific form to save the specific data of the form
		try {
			$this->saveSpecificForm($data, $this->reviewObject->id_review, $update);
		}catch (Exception $e) {
			error_log($e->getMessage());
			if (!$update) {
				$this->reviewObject->hardDeleteReview();
			}
			throw new Exception("Error during save");
		}

		if($update){
			$this->reviewObject->updateReviewDate();
		}
		
		if ($validate) {
			$this->reviewObject->changeReviewValidationStatus($validate);
		}
		//update the visit status if we are processing a local form
		if ($this->local) {
			if ($validate) {
				$this->visitObject->changeVisitStateInvestigatorForm(Visit::LOCAL_FORM_DONE);
			}else {
				$this->visitObject->changeVisitStateInvestigatorForm(Visit::LOCAL_FORM_DRAFT);
			}
		}
		
		//Log Activity
		if ($this->local) {
			$role="Investigator";
		}else {
			$role="Reviewer";
		}
		$actionDetails['patient_code']=$this->visitObject->patientCode;
		$actionDetails['type_visit']=$this->visitObject->visitType;
		$actionDetails['modality_visit']=$this->visitObject->visitGroupObject->groupModality;
		$actionDetails['id_review']=$this->reviewObject->id_review;
		$actionDetails['associated_files']=$this->reviewObject->associatedFiles;
		$actionDetails['local_review']=intval($this->local);
		$actionDetails['adjudication']=intval($this->reviewStatus == Visit::REVIEW_WAIT_ADJUDICATION);
		$actionDetails['create']=!$update;
		$actionDetails['raw_data']=$data;
		
		Tracker::logActivity($this->username, $role, $this->study, $this->id_visit, "Save Form", $actionDetails);
		
		//If central review still not at "Done" status Check if validation is reached
		if ($validate && !$this->local && $this->reviewStatus != Visit::REVIEW_DONE) {
			$this->setVisitValidation();
		}
		
	}
	
	/**
	 * update the review conclusion of the visit, pass decision in argument
	 * and trigger the review availability decision method
	 * @param $reviewConclusion : constant value from this clas
	 * @param string $reviewStatus
	 * @param $conclusionValue
	 */
	protected function changeVisitValidationStatus(string $reviewStatus, $conclusionValue="N/A") {
		$this->visitObject->changeVisitValidationStatus($reviewStatus, $conclusionValue);
		$this->reviewAvailabilityDecision($reviewStatus);

		//Send Notification emails
		if ($reviewStatus == Visit::REVIEW_WAIT_ADJUDICATION) {

			$email=new Send_Email($this->linkpdo);
			//SK A AMELIORER POUR EVITER DE MAILIER LES REVIEWER QUI ONT DEJA REPONDU
			//NECESSITE DE FILTER LA LISTE DES REVIEWERS DE L ETUDE
			$email->addGroupEmails($this->visitObject->study, User::REVIEWER)
					->addGroupEmails($this->visitObject->study, User::SUPERVISOR);
			$email->sendAwaitingAdjudicationMessage($this->visitObject->study, $this->visitObject->patientCode, $this->visitObject->visitType);

		}else if ($reviewStatus == Visit::REVIEW_DONE) {

			$email=new Send_Email($this->linkpdo);
			$uploaderUserObject=new User($this->visitObject->uploaderUsername, $this->linkpdo);
			$uploaderEmail=$uploaderUserObject->userEmail;
			$email->addGroupEmails($this->visitObject->study, User::MONITOR)
					->addGroupEmails($this->visitObject->study, User::SUPERVISOR)
					->addEmail($uploaderEmail);
			$email->sendVisitConcludedMessage($this->visitObject->study, $this->visitObject->patientCode, $this->visitObject->visitType, $conclusionValue);

		}
	}
	
	/**
	 * Return the saved form of the current user.
	 * Used to fill the form at display
	 * @return array of the general and specific table
	 */
	public function loadSavedForm() {
		try {
			if ($this->local) {
				$this->reviewObject=$this->visitObject->getReviewsObject(true);
			}else {
				$this->reviewObject=$this->visitObject->queryExistingReviewForReviewer($this->username);
			}

		}catch (Exception $e) { }
	    
		return $this->reviewObject;
		
	}
	
	/**
	 * return all reviews (local and reviewer) of the current visit
	 * Usefull to determine visit conclusion status
	 */
	public function getAllValidatedFormsOfVisit() {
	    
		$query=$this->linkpdo->prepare('SELECT * FROM reviews,'.$this->specificTable.' WHERE reviews.id_review='.$this->specificTable.'.id_review AND reviews.id_visit=:idVisit AND reviews.validated=1 AND reviews.deleted=0');
		$query->execute(array(
			'idVisit'=>$this->id_visit
		));
		$datas=$query->fetchAll(PDO::FETCH_ASSOC);
		return $datas;
	}

	/**
	 * Return of validated review object (no local form)
	 */
	public function getValidatedReviewObjects() : array {
		$reviewsObject=$this->visitObject->getReviewsObject(false);
		//Filter only validated review that will be analyzed
		$validatedReviewObjects=array_filter($reviewsObject, function($review) {
			if ($review->validated) {
				return true;
			}else {
				return false;
			}
		});
		//Reindex results from index zero
		$validatedReviewObjects=array_values($validatedReviewObjects);

		return $validatedReviewObjects;
	}
	
	/**
	 * Get existing form for current user
	 * can be overrided to add additional data such as investigator form data
	 */
	public function getExistingFormData() {
		if( ! empty($this->reviewObject)){
			return ($this->reviewObject->getSpecificData());
		}else{
			return [];
		}
		
	}
	
	/**
	 * When Review conclusion "Done" reached Will make review unavailable for new review
	 * Can be overided if needed different condition
	 * @param string $reviewConclusion
	 */
	protected function reviewAvailabilityDecision(string $reviewConclusion) {
		//If Done reached make the review unavailable for review
		if ($reviewConclusion == Visit::REVIEW_DONE) {
			$this->visitObject->changeReviewAvailability(false);
		}
		//Needed in case of deletion of a review (even if true by default initialy, need to come back if deletion)
		else {
			$this->visitObject->changeReviewAvailability(true);
		}
	}
	
}