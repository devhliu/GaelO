<?php

namespace App\GaelO\UseCases\GetPatientVisit;

use App\GaelO\Interfaces\PersistenceInterface;
use App\GaelO\UseCases\GetVisit\VisitEntity;

class GetPatientVisit {

    public function __construct(PersistenceInterface $persistenceInterface){
        $this->persistenceInterface = $persistenceInterface;
    }

    public function execute(GetPatientVisitRequest $getPatientVisitRequest, GetPatientVisitResponse $getPatientVisitResponse){
        $studyName = $getPatientVisitRequest->visitId;
        $visitId = $getPatientVisitRequest->visitId;
        $patientCode = $getPatientVisitRequest->patientCode;
        $GLOBALS['patientCode'] = $patientCode;
        $GLOBALS['studyName'] = $studyName;
        if ($visitId == 0) {
            $dbData = $this->persistenceInterface->getAll();
            $dbData = array_filter($dbData, function ($element) {
                return $element['patient_code'] == $GLOBALS['patientCode'];
            });
            $dbData = array_filter($dbData, function ($element) {
                return $element['study_name'] == $GLOBALS['studyName'];
            });
            $responseArray = [];
            foreach($dbData as $data){
                $responseArray[] = VisitEntity::fillFromDBReponseArray($data);
            }
            $getPatientVisitResponse->body = $responseArray;
        } else {
            $dbData = $this->persistenceInterface->find($visitId);
            $responseEntity = VisitEntity::fillFromDBReponseArray($dbData);
            $getPatientVisitResponse->body = $responseEntity;
        }
        $getPatientVisitResponse->status = 200;
        $getPatientVisitResponse->statusText = 'OK';
    }
}
