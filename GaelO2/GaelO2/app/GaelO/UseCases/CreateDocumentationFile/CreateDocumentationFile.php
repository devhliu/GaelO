<?php

namespace App\GaelO\UseCases\CreateDocumentationFile;

use App\GaelO\Adapters\LaravelFunctionAdapter;
use App\GaelO\Constants\Constants;
use App\GaelO\Exceptions\GaelOBadRequestException;
use App\GaelO\Exceptions\GaelOException;
use App\GaelO\Exceptions\GaelOForbiddenException;
use App\GaelO\Interfaces\PersistenceInterface;
use App\GaelO\Services\AuthorizationService;
use App\GaelO\Services\TrackerService;
use Exception;

class CreateDocumentationFile{

    public function __construct(PersistenceInterface $documentationRepository, AuthorizationService $authorizationService, TrackerService $trackerService)
    {
        $this->documentationRepository = $documentationRepository;
        $this->authorizationService = $authorizationService;
        $this->trackerService = $trackerService;
    }

    public function execute(CreateDocumentationFileRequest $createDocumentationFileRequest, CreateDocumentationFileResponse $createDocumentationFileResponse){

        try{

            $documentationEntity = $this->documentationRepository->getDocumentation($createDocumentationFileRequest->id);
            $studyName = $documentationEntity['study_name'];
            $this->checkAuthorization($createDocumentationFileRequest->currentUserId, $studyName);

            if($createDocumentationFileRequest->contentType !== 'application/pdf'){
                throw new GaelOBadRequestException("Only application/pdf content accepted");
            }

            if( ! $this->is_base64_encoded($createDocumentationFileRequest->binaryData)){
                throw new GaelOBadRequestException("Payload should be base64 encoded");
            }

            $storagePath = LaravelFunctionAdapter::getStoragePath();

            $destinationPath = '/documentations/'.$studyName;
            if (!is_dir($storagePath.'/'.$destinationPath)) {
                mkdir($storagePath.'/'.$destinationPath, 0755, true);
            }

            file_put_contents ( $storagePath.'/'.$destinationPath.'/'.$documentationEntity['id'].'.pdf', base64_decode($createDocumentationFileRequest->binaryData) );

            $documentationEntity['path']= $destinationPath.'/'.$documentationEntity['id'].'.pdf';

            $this->documentationRepository->update($createDocumentationFileRequest->id, $documentationEntity);

            $actionDetails =[
                'documentation_id'=>$createDocumentationFileRequest->currentUserId,
            ];

            $this->trackerService->writeAction(
                $createDocumentationFileRequest->currentUserId,
                Constants::ROLE_SUPERVISOR,
                $studyName,
                null,
                Constants::TRACKER_UPLOAD_DOCUMENTATION,
                $actionDetails);

            //Return created documentation ID to help front end to send file data
            $createDocumentationFileResponse->status = 201;
            $createDocumentationFileResponse->statusText =  'Created';

        } catch (GaelOException $e){

            $createDocumentationFileResponse->body = $e->getErrorBody();
            $createDocumentationFileResponse->status = $e->statusCode;
            $createDocumentationFileResponse->statusText =  $e->statusText;

        }catch (Exception $e){
            throw $e;
        }

    }

    private function checkAuthorization(int $currentUserId, string $studyName){
        $this->authorizationService->setCurrentUser($currentUserId);
        if( !$this->authorizationService->isRoleAllowed(Constants::ROLE_SUPERVISOR, $studyName)){
            throw new GaelOForbiddenException();
        }
    }

    private function is_base64_encoded($data) : bool {
        if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $data)) {
        return true;
        } else {
        return false;
        }
    }
}