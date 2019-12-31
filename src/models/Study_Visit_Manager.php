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
 * Manage visit for a group level of a study
 */

class Study_Visit_Manager
{

    private $studyObject;
    private $visitGroupObject;
    private $linkpdo;


    public function __construct(Study $studyObject, Visit_Group $visitGroupObject, PDO $linkpdo)
    {
        $this->linkpdo = $linkpdo;
        $this->studyObject = $studyObject;
        $this->visitGroupObject = $visitGroupObject;
    }

    public function getVisitGroupObject(){
        return $this->visitGroupObject;
    }


    /**
     * Return uploaded and non deleted visit Objects
     */
    public function getUploadedVisits()
    {

        $uploadedVisitQuery = $this->linkpdo->prepare('SELECT id_visit FROM visits WHERE study = :study
                                                    AND visit_group_id = :visitGroupId
                                                    AND deleted=0
                                                    AND visits.upload_status="Done" ');

        $uploadedVisitQuery->execute(array(
            'study' => $this->study,
            'visitGroupId' => $this->visitGroupObject->groupId
        ));

        $uploadedVisitIds = $uploadedVisitQuery->fetchall(PDO::FETCH_COLUMN);

        $visitObjectArray = [];
        foreach ($uploadedVisitIds as $id_visit) {
            $visitObjectArray[] = new Visit($id_visit, $this->linkpdo);
        }

        return $visitObjectArray;
    }

    public function getAwaitingUploadVisit()
    {

        $uploadedVisitQuery = $this->linkpdo->prepare("SELECT id_visit FROM visits WHERE study = :study
                                                        AND visit_group_id = :visitGroupId
														AND deleted=0
														AND visits.upload_status ='Not Done'
														AND visits.status_done='Done' ");

        $uploadedVisitQuery->execute(
            array(
                'study' => $this->study,
                'visitGroupId' => $this->visitGroupObject->groupId
            )
        );
        $uploadedVisitIds = $uploadedVisitQuery->fetchAll(PDO::FETCH_COLUMN);

        $visitObjectArray = [];
        foreach ($uploadedVisitIds as $id_visit) {
            $visitObjectArray[] = new Visit($id_visit, $this->linkpdo);
        }

        return $visitObjectArray;
    }

    /**
     * Get Visits awaiting review
     * Optionally visit awaiting review can be specific to an username
     * @param string $username
     * @return Visit[]
     */
    public function getAwaitingReviewVisit(string $username = null)
    {

        //Query visit to analyze visit awaiting a review
        $idVisitsQuery = $this->linkpdo->prepare('SELECT id_visit FROM visits INNER JOIN visit_type ON (visits.visit_type=visit_type.name AND visits.study=visit_type.study)
                                      WHERE (visits.study = :study
                                      AND visit_group_id = :visitGroupId
                                      AND deleted=0
                                      AND review_available=1) ORDER BY visit_order ');

        $idVisitsQuery->execute(array(
            'study' => $this->study,
            'visitGroupId' => $this->visitGroupObject->groupId
        ));

        $visitList = $idVisitsQuery->fetchAll(PDO::FETCH_COLUMN);

        $visitObjectArray = [];

        foreach ($visitList as $visitId) {
            $visitObject = new Visit($visitId, $this->linkpdo);

            if (!empty($username)) {
                if ($visitObject->isAwaitingReviewForReviewerUser($username)) $visitObjectArray[] = $visitObject;
            } else {
                $visitObjectArray[] = $visitObject;
            }
        }

        return $visitObjectArray;
    }

    public function getVisitWithQCStatus($qcStatus)
    {

        $visitQuery = $this->linkpdo->prepare("SELECT id_visit FROM visits WHERE study = :study
                                                        AND visit_group_id = :visitGroupId
														AND deleted=0
                                                        AND state_quality_control=:qcStatus");

        $visitQuery->execute(array(
            'study' => $this->study,
            'qcStatus' => $qcStatus,
            'visitGroupId' => $this->visitGroupObject->groupId
        ));
        $visitIds = $visitQuery->fetchall(PDO::FETCH_COLUMN);

        $visitObjectArray = [];
        foreach ($visitIds as $id_visit) {
            $visitObjectArray[] = new Visit($id_visit, $this->linkpdo);
        }

        return $visitObjectArray;
    }

    public function getVisitsMissingInvestigatorForm()
    {

        $visitQuery = $this->linkpdo->prepare("SELECT id_visit FROM visits WHERE study = :study
                                                            AND visit_group_id = :visitGroupId
                                                            AND deleted=0 
                                                            AND state_investigator_form !='Done' 
                                                            AND upload_status='Done'");

        $visitQuery->execute(array(
            'study' => $this->study,
            'visitGroupId' => $this->visitGroupObject->groupId
        ));

        $visitIds = $visitQuery->fetchAll(PDO::FETCH_COLUMN);

        $visitObjectArray = [];
        foreach ($visitIds as $id_visit) {
            $visitObjectArray[] = new Visit($id_visit, $this->linkpdo);
        }

        return $visitObjectArray;
    }

    /**
     * Return studie's visit object
     */
    public function getCreatedVisits(bool $deleted = false)
    {

        $uploadedVisitQuery = $this->linkpdo->prepare('SELECT id_visit FROM visits, visit_type WHERE visits.study = :study
                                                    AND visits.deleted=:deleted
                                                    AND visits.visit_group_id = :visitGroupId 
                                                    AND visit_type.name=visits.visit_type
                                                    AND visit_type.study=visits.study
                                                    ORDER BY patient_code, visit_type.visit_order');

        $uploadedVisitQuery->execute(array(
            'study' => $this->study, 
            'deleted' => intval($deleted),
            'visitGroupId' => $this->visitGroupObject->groupId
        ));
        
        $uploadedVisitIds = $uploadedVisitQuery->fetchAll(PDO::FETCH_COLUMN);

        $visitObjectArray = [];
        foreach ($uploadedVisitIds as $id_visit) {
            $visitObjectArray[] = new Visit($id_visit, $this->linkpdo);
        }

        return $visitObjectArray;
    }


    public function getAllPatientsVisitsStatus(){

        //Get ordered list of possible visits in this study
        $allVisits=$this->visitGroupObject->getAllVisitTypesOfGroup();
        //Get patients list in this study
        $allPatients=$this->studyObject->getAllPatientsInStudy();

        $results=[];

        foreach($allPatients as $patient) {

            $patientCenter=$patient->getPatientCenter();
            $visitManager=$patient->getVisitManager();

            foreach($allVisits as $possibleVisit) {

                $patientData=[];
                $patientData['center']=$patientCenter->name;
                $patientData['country']=$patientCenter->countryName;
                $patientData['firstname']=$patient->patientFirstName;
                $patientData['lastname']=$patient->patientLastName;
                $patientData['birthdate']=$patient->patientBirthDate;
                $patientData['registration_date']=$patient->patientRegistrationDate;

                $visitStatus=$visitManager->determineVisitStatus($possibleVisit->name);

                $results[$possibleVisit->name][$patient->patientCode]= array_merge($patientData,$visitStatus);

            }

        }

        return(json_encode($results));
    }
}
