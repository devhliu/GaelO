<?php 
/**
 Copyright (C) 2018 KANOUN Salim
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
 * Build Json data for statistics pages
 */
class Statistics {
	
	private $linkpdo;
	public $studyObject;
	private $studyVisitManager;

	public function __construct(Study $study, PDO $linkpdo){
		$this->linkpdo=$linkpdo;
		$this->studyObject=$study;
		//Cette ligne doit venir du constructeur
		$visitGroup=$study->getSpecificGroup(Visit_Group::GROUP_MODALITY_PET);
		$this->studyVisitManager=new Study_Visit_Manager($this->studyObject, $visitGroup, $this->linkpdo);
		
	}
	
	/**
	 * List users who have done reviews (and other reviewers missing) for each visit
	 * with date of review, status of review, 
	 * @return array[]
	 */
	public function getReviewsDetailsByVisit(){
		
		//List the Reviewers declared in the study
		$reviewerUsersObjects=$this->studyObject->getUsersByRoleInStudy(User::REVIEWER);
		$availableReviewers=[];
		foreach ($reviewerUsersObjects as $reviewerObject){
			$availableReviewers[]=$reviewerObject->lastName." ".$reviewerObject->firstName;
		}
		
		//Retrieve created Visit from the study Object
		$createdVisitObjects=$this->studyObject->getStudySpecificGroupManager(Visit_Group::GROUP_MODALITY_PET)->getCreatedVisits();
		
		//GlobalMap
		$reviewdetailsMap=[];
		
		foreach ($createdVisitObjects as $createdVisit){
			if($createdVisit->stateQualityControl==Visit::QC_ACCEPTED){
				//If QC Accepted, visit is suitable for review so analyze it
				$newVisit['visitId']=$createdVisit->id_visit;
				$newVisit['patientNumber']=$createdVisit->patientCode;
				$newVisit['visit']=$createdVisit->visitType;
				$newVisit['acquisitionDate']=$createdVisit->acquisitionDate;
				$newVisit['reviewStatus']=$createdVisit->reviewStatus;
				//Retrieve review
				$reviewObjects=$createdVisit->getReviewsObject(false);
				$newVisit['numberOfReview']=count($reviewObjects);
				$newVisit['reviewDoneBy']=[];
				$newVisit['reviewDetailsArray']=[];
				foreach ($reviewObjects as $review){
					$reviewerObject=$review->getUserObject();
					$details['user']=$reviewerObject->lastName." ".$reviewerObject->firstName;
					$details['date']=$review->reviewDate;
					$newVisit['reviewDetailsArray'][]=$details;
					$newVisit['reviewDoneBy'][]=$reviewerObject->lastName." ".$reviewerObject->firstName;
				}
				
				//Determine missing reviewer for this visit
				$newVisit['reviewNotDoneBy']=array_diff($availableReviewers, $newVisit['reviewDoneBy']);
				
				//Add all data to the global map
				$reviewdetailsMap[ $createdVisit->id_visit ]=$newVisit;
				
			}
		}
		return $reviewdetailsMap;
		
		
	}

	/**
	 * List review one by one with user and date
	 * @return array
	 */
	public function getReviewsDate(){
		$reviewdetailsMap=$this->getReviewsDetailsByVisit();

		$result=[];
		
		foreach($reviewdetailsMap as $visitType=>$details){
		    $review=[];
			foreach($details['reviewDetailsArray'] as $detail){
				$review['username']=$detail['user'];
				$review['date']=$detail['date'];
				$result[]=$review;

			}
		}
		return $result;
	}

	/**
	 * Provide uploadedFraction of patient in position 0 and upload delay in position 1
	 * @return array
	 */
	public function getUploadFractionAndDelay(){

		$dataJson=$this->studyVisitManager->getPatientsVisitsStatus();
		$allPatientStatus=json_decode($dataJson, true);
		$results[0]=$this->getUploadedFraction($allPatientStatus);
		$results[1]=$this->getUploadDelay($allPatientStatus);

		return $results;
		
	}
	
	/**
	 * Return the uploaded fraction of patients
	 * @param array $allPatientStatus
	 * @return array
	 */
	private function getUploadedFraction($allPatientStatus){
		$resultArray=[];
		
		
		foreach ($allPatientStatus as $visitType => $patients){
			foreach ($patients as $patientCode=>$patientDetails){
				
				if($patientDetails['status']==Patient_Visit_Manager::DONE || $patientDetails['status']==Patient_Visit_Manager::SHOULD_BE_DONE){
					$visit['status']=$patientDetails['status'];
					$visit['uploadStatus']=$patientDetails['upload_status'];
					$visit['visitType']=$visitType;
					$visit['country']=$patientDetails['country'];
					$visit['center']=$patientDetails['center'];
					$resultArray[]=$visit;
				}
			}
		}

		return $resultArray;
		
	}
	
	/**
	 * Calculate upload delay (from declared visit date and upload date)
	 * @param array $allPatientStatus
	 * @return array
	 */
	private function getUploadDelay($allPatientStatus){

	    $resultArray=[];
	    
	    foreach ($allPatientStatus as $visitType => $patients){
	        foreach ($patients as $patientCode=>$patientDetails){
	            
	            if($patientDetails['status']==Patient_Visit_Manager::DONE && $patientDetails['state_investigator_form']==Patient_Visit_Manager::DONE){
	                
	                $acquisitionDate=new DateTimeImmutable($patientDetails['acquisition_date']);
	                $uploadDate=new DateTimeImmutable($patientDetails['upload_date']);
	                $uploadDelay=($uploadDate->getTimestamp()-$acquisitionDate->getTimestamp()) / (3600*24);
	                $visit['uploadDelay']=$uploadDelay;
	                $visit['acquisitionCompliancy']=$patientDetails['compliancy'];
	                $visit['visitType']=$visitType;
	                $visit['idVisit']=$patientDetails['id_visit'];
	                $visit['center']=$patientDetails['center'];
	                $visit['country']=$patientDetails['country'];
	                $resultArray[]=$visit;
	            }
	        }
	    }
	    
	    return $resultArray;
		
	
	}
	
	/**
	 * Return QC time (in days) for each visit (from upload date to QC)
	 * @return array
	 */
	public function getQCTime(){
		
		$uploadedVisitArray=$this->studyVisitManager->getUploadedVisits();
		
		$responseDelayArray=[];
		
		foreach ($uploadedVisitArray as $visit){
			$responseQcArrayDetails = [];
			if($visit->qcStatus!=Visit::QC_NOT_DONE){
				$uploadDate=new DateTimeImmutable($visit->uploadDate);
				$qcDate=new DateTimeImmutable($visit->controlDate);
				$qcDelay=($qcDate->getTimestamp()-$uploadDate->getTimestamp()) / (3600*24);

				if($visit->correctiveActionDate==null){
					$hasCorrectiveAction=false;
				}else{
					$hasCorrectiveAction=true;
				}

				$responseQcArrayDetails['idVisit']=$visit->id_visit;
				$responseQcArrayDetails['qcDelay']=$qcDelay;
				$responseQcArrayDetails['hasCorrectiveAction']=$hasCorrectiveAction;
			}
			$responseDelayArray[] = $responseQcArrayDetails;
		}
		
		return $responseDelayArray;
		
	}
	
	/**
	 * Output the time to reach the review conclusion (from QC date to Review Done status)
	 * @return array[]
	 */
	public function getConclusionTime(){
		
		$uploadedVisitArray=$this->studyVisitManager->getUploadedVisits();
		
		$responseDelayArray=[];
		
		foreach ($uploadedVisitArray as $visit){
			if($visit->reviewStatus==Form_Processor::DONE){
				$qcDate=new DateTimeImmutable($visit->controlDate);
				$conclusionDate=new DateTimeImmutable($visit->reviewConclusionDate);
				$conclusionDelay=($conclusionDate->getTimestamp()-$qcDate->getTimestamp())/ (3600*24);
				
				$responseConclusionArrayDetails['idVisit']=$visit->id_visit;
				$responseConclusionArrayDetails['conclusionDelay']=$conclusionDelay;
				
				$responseDelayArray[]=$responseConclusionArrayDetails;
			}
		}
		
		return $responseDelayArray;
		
	}
	
	/**
	 * Return all review status for each visit
	 * @return array
	 */
	public function getReviewStatus(){
		
		$uploadedVisitArray=$this->studyVisitManager->getUploadedVisits();
		
		$responseReviewArray=[];
		
		foreach ($uploadedVisitArray as $visit){
			$responseReviewArrayElement = [];
			if($visit->statusDone==Visit::DONE){
				$responseReviewArrayElement['status'] = $visit->reviewStatus;
				if($visit->reviewStatus==Visit::DONE){
					$responseReviewArrayElement['visitType'] = $visit->visitType;
					$responseReviewArrayElement['conclusionValue'] = $visit->reviewConclusion;
				}
			}
			$responseReviewArray[]=$responseReviewArrayElement;
		}
		
		return $responseReviewArray;
		
	}
	
	/**
	 * Return array of each visit's QC status
	 * @return string
	 */
	public function getQcStatus(){
		
		$uploadedVisitArray=$this->studyVisitManager->getUploadedVisits();
		
		$responseQcArray=[];
		
		foreach ($uploadedVisitArray as $visit){
			$responseQcArrayDetails=[];
			if($visit->statusDone==Visit::DONE){
				$patientObject=$visit->getPatient();
				$center=$patientObject->getPatientCenter();
				if($visit->correctiveActionDate==null){
					$hasCorrectiveAction=false;
				}else{
					$hasCorrectiveAction=true;
				}
				$responseQcArrayDetails['qcStatus']=$visit->qcStatus;
				$responseQcArrayDetails['hasCorrectiveAction']=$hasCorrectiveAction;
				$responseQcArrayDetails['center']=$center->name;
				$responseQcArrayDetails['country']=$center->countryName;
			}
			$responseQcArray[] = $responseQcArrayDetails;
		}
		
		return $responseQcArray;
		
	}
	
	
	/**
	 * Return array of delay between injection time and acquisition time.
	 * Will only return the first PET series found for each visit
	 * @return array|number
	 */
	public function getAcquisitionPetDelay(){
		
		$uploadedVisitArray=$this->studyVisitManager->getUploadedVisits();
		
		$delayArray=array();
		foreach ($uploadedVisitArray as $visit){
			$uploadedSeries=$visit->getSeriesDetails();
			foreach ($uploadedSeries as $serie){

				if($serie->acquisitionDateTime==null || $serie->injectedDateTime==null){
					continue;
				}
				
				$acquisitionTime=new DateTimeImmutable($serie->acquisitionDateTime);
				$injectionTime=new DateTimeImmutable($serie->injectedDateTime);
				if($acquisitionTime!=null && $injectionTime!=null){
					$acquisitionDelay=($acquisitionTime->getTimestamp() - $injectionTime->getTimestamp())/ 60;
					$relatedPatient=$visit->getPatient();
					$patientCenter=$relatedPatient->getPatientCenter();
					$delayDetails['country']=$patientCenter->countryName;
					$delayDetails['idVisit']=$visit->id_visit;
					$delayDetails['center']=$patientCenter->name;
					$delayDetails['patientNumber']=$relatedPatient->patientCode;
					$delayDetails['visitType']=$visit->visitType;
					$delayDetails['delayAcquisition']=$acquisitionDelay;
					
					$delayArray[]=$delayDetails;
					break;
				}
			}
		}

		return $delayArray;
		
	}
	
	/**
	 * Return specific data of all reviews
	 * @return array[]
	 */
	public function getReviewData(){
	    
        $createdVisits=$this->studyVisitManager->getUploadedVisits();
        
        $reviewsJson=[];
        foreach ($createdVisits as $visit){

					$reviews=array();

					$localReview=$visit->getReviewsObject(true);
					if(!empty($localReview)){
						$reviews[]=$localReview;

					}

					$reviewsReviewers=$visit->getReviewsObject(false);

					foreach ($reviewsReviewers as $expertReview){
						$reviews[]=$expertReview;
					}

	        foreach ($reviews as $review){

						if($review->validated){
								$specificData=$review->getSpecificData();
								$parentVisit=$review->getParentVisitObject();
								$visitType=$parentVisit->visitType;
								$reviewResult=$specificData;
								$reviewResult['_reviewDate']=$review->reviewDate;
								$reviewResult['_localForm']=boolval($review->isLocal);
								$reviewResult['_adjudicationForm']=boolval($review->isAdjudication);
								$reviewResult['_username']=$review->username;
								$reviewResult['_center']=$parentVisit->getPatient()->getPatientCenter()->name;
								$reviewResult['_visitType']=$visitType;
								
								$reviewsJson['data'][$visitType][]=$reviewResult;
						}

	        }
		}
		
        $visitTypePossible=$this->studyVisitManager->getVisitGroupObject()->getAllVisitTypesOfGroup();
        foreach ($visitTypePossible as $visitType){
            $inputType=$visitType->getSpecificTableInputType();
            $dataDetails[$visitType->name]=$inputType;
        }
        
        $reviewsJson['structureDetails']=$dataDetails;
        
        return $reviewsJson;
    }
	   
	
}
