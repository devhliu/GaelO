<?php

namespace App\GaelO\UseCases\DeleteSeries;

use App\GaelO\Constants\Constants;
use App\GaelO\Exceptions\GaelOException;
use App\GaelO\Exceptions\GaelOForbiddenException;
use App\GaelO\Interfaces\PersistenceInterface;
use App\GaelO\Services\AuthorizationVisitService;
use App\GaelO\Services\DicomSeriesService;
use App\GaelO\Services\TrackerService;
use Exception;

class DeleteSeries{

    private AuthorizationVisitService $authorizationVisitService;
    private DicomSeriesService $dicomSeriesService;
    private TrackerService $trackerService;


    public function __construct( PersistenceInterface $persistenceInterface, DicomSeriesService $dicomSeriesService, AuthorizationVisitService $authorizationVisitService, TrackerService $trackerService)
    {
        $this->authorizationVisitService = $authorizationVisitService;
        $this->dicomSeriesService = $dicomSeriesService;
        $this->trackerService = $trackerService;
        $this->persistenceInterface = $persistenceInterface;
    }

    public function execute(DeleteSeriesRequest $deleteSeriesRequest, DeleteSeriesResponse $deleteSeriesResponse){

        try{

            $seriesData = $this->dicomSeriesService->getSeriesBySeriesInstanceUID($deleteSeriesRequest->seriesInstanceUID);
            dd($seriesData);
            $visitId = $seriesData['orthancStudy']['visit_id'];
            $this->checkAuthorization($deleteSeriesRequest->currentUserId, $visitId, $deleteSeriesRequest->role);

            $this->dicomSeriesService->deleteSeries($deleteSeriesRequest->seriesInstanceUID, $deleteSeriesRequest->role);

            $visitContext = $this->persistenceInterface->getVisitContext($visitId);
            $studyName = $visitContext['visit_type']['visit_group']['study_name'];

            $actionDetails = [
                'seriesInstanceUID'=>$seriesData['series_uid'],
                'reason'=>$deleteSeriesRequest->reason
            ];

            $this->trackerService->writeAction(
                $deleteSeriesRequest->currentUserId,
                $deleteSeriesRequest->role,
                $studyName,
                $visitId,
                Constants::TRACKER_DELETE_DICOM_SERIES,
                $actionDetails
            );

            $deleteSeriesResponse->status = 200;
            $deleteSeriesResponse->statusText =  'OK';

        } catch (GaelOException $e){

            $deleteSeriesResponse->body = $e->getErrorBody();
            $deleteSeriesResponse->status = $e->statusCode;
            $deleteSeriesResponse->statusText =  $e->statusText;

        } catch (Exception $e){
            throw $e;
        }

    }

    public function checkAuthorization(int $userId, int $visitId, string $role) : void{

        //Series delete only for Investigator, Controller, Supervisor
        if( !in_array($role, [Constants::ROLE_INVESTIGATOR, Constants::ROLE_CONTROLER, Constants::ROLE_SUPERVISOR]) ){
            throw new GaelOForbiddenException();
        }

        $this->authorizationVisitService->setCurrentUserAndRole($userId, $role);
        $this->authorizationVisitService->setVisitId($visitId);

        if ( ! $this->authorizationVisitService->isVisitAllowed()){
            throw new GaelOForbiddenException();
        }

    }
}