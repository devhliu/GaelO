<?php

namespace App\GaelO\UseCases\DeleteDocumentation;

use App\GaelO\Constants\Constants;
use App\GaelO\Exceptions\GaelOException;
use App\GaelO\Exceptions\GaelOForbiddenException;
use App\GaelO\Interfaces\PersistenceInterface;
use App\GaelO\Services\AuthorizationService;
use App\GaelO\Services\TrackerService;
use Exception;

class DeleteDocumentation{

    public function __construct(PersistenceInterface $persistenceInterface, AuthorizationService $authorizationService, TrackerService $trackerService)
    {
        $this->documentationRepository = $persistenceInterface;
        $this->authorizationService = $authorizationService;
        $this->trackerService = $trackerService;
    }

    public function execute(DeleteDocumentationRequest $deleteDocumentationRequest, DeleteDocumentationResponse $deleteDocumentationResponse){

        try{

            $documentationEntity = $this->documentationRepository->find($deleteDocumentationRequest->id);
            $studyName = $documentationEntity['study_name'];

            $this->checkAuthorization($deleteDocumentationRequest->currentUserId, $studyName);

            $this->documentationRepository->delete($deleteDocumentationRequest->id);

            $actionDetails = [
                'documentationId' => $deleteDocumentationRequest->id,
                'documenationName'=> $documentationEntity['name'],
                'documenationVersion'=> $documentationEntity['version']
            ];

            $this->trackerService->writeAction(
                $deleteDocumentationRequest->currentUserId,
                Constants::ROLE_SUPERVISOR,
                $studyName,
                null,
                Constants::TRACKER_DELETE_DOCUMENTATION,
                $actionDetails);

                $deleteDocumentationResponse->status = 200;
                $deleteDocumentationResponse->statusText =  'OK';


        } catch (GaelOException $e){

            $deleteDocumentationResponse->body = $e->getErrorBody();
            $deleteDocumentationResponse->status = $e->statusCode;
            $deleteDocumentationResponse->statusText =  $e->statusText;

        } catch (Exception $e){
            throw $e;
        }
    }

    private function checkAuthorization(int $currentUserId, string $studyName){
        $this->authorizationService->setCurrentUserAndRole($currentUserId, Constants::ROLE_SUPERVISOR);
        if( !$this->authorizationService->isRoleAllowed($studyName)){
            throw new GaelOForbiddenException();
        }
    }
}
