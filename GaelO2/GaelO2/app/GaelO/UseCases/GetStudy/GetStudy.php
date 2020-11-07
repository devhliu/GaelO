<?php

namespace App\GaelO\UseCases\GetStudy;

use App\GaelO\Exceptions\GaelOException;
use App\GaelO\Exceptions\GaelOForbiddenException;
use App\GaelO\Interfaces\PersistenceInterface;
use Exception;

class GetStudy{

    public function __construct(PersistenceInterface $persistenceInterface){
        $this->persistenceInterface = $persistenceInterface;

    }

    public function execute(GetStudyRequest $getStudyRequest, GetStudyResponse $getStudyResponse) : void{

        try{
            $this->checkAuthorization($getStudyRequest->currentUserId);

            $studies = $this->persistenceInterface->getStudies(true);

            $responseArray = [];
            foreach($studies as $study){
                $responseArray[] = StudyEntity::fillFromDBReponseArray($study);
            }

            $getStudyResponse->body = $responseArray;
            $getStudyResponse->status = 200;
            $getStudyResponse->statusText = 'OK';

        } catch (GaelOException $e){

            $getStudyResponse->body = $e->getErrorBody();
            $getStudyResponse->status = $e->statusCode;
            $getStudyResponse->statusText = $e->statusText;

        } catch (Exception $e){
            throw $e;
        }


    }

    private function checkAuthorization($userId)  {
        $this->authorizationService->setCurrentUser($userId);
        if( ! $this->authorizationService->isAdmin($userId)) {
            throw new GaelOForbiddenException();
        };
    }

}
