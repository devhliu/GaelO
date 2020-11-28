<?php

namespace App\GaelO\UseCases\ModifyPatientWithdraw;

use App\GaelO\Constants\Constants;
use App\GaelO\Exceptions\GaelOBadRequestException;
use App\GaelO\Exceptions\GaelOException;
use App\GaelO\Exceptions\GaelOForbiddenException;
use App\GaelO\Interfaces\PersistenceInterface;
use App\GaelO\Services\AuthorizationPatientService;
use App\GaelO\Services\TrackerService;
use App\GaelO\Util;
use Exception;

class ModifyPatientWithdraw {

    public function __construct(PersistenceInterface $persistenceInterface,
                                AuthorizationPatientService $authorizationPatientService,
                                TrackerService $trackerService)
    {
        $this->persistenceInterface = $persistenceInterface;
        $this->authorizationPatientService = $authorizationPatientService;
        $this->trackerService = $trackerService;
    }

    public function execute(ModifyPatientWithdrawRequest $modifyPatientWithdrawRequest, ModifyPatientWithdrawResponse $modifyPatientWithdrawResponse){

        try{

            $patientEntity = $this->persistenceInterface->find($modifyPatientWithdrawRequest->patientCode);
            $this->checkAuthorization($modifyPatientWithdrawRequest->currentUserId, $patientEntity['study_name']);

            $modifiedData = [];

            //Handle Withdraw status update
            $patientEntity['withdraw'] = $modifyPatientWithdrawRequest->withdraw;

            if($modifyPatientWithdrawRequest->withdraw){

                if(empty($modifyPatientWithdrawRequest->withdrawDate) ||
                    empty($modifyPatientWithdrawRequest->withdrawReason)
                ){
                    throw new GaelOBadRequestException('Withdraw Date and Reason must be specified for withdraw declaration');
                }

                $patientEntity['withdraw_reason'] = $modifyPatientWithdrawRequest->withdrawReason;
                $patientEntity['withdraw_date'] = Util::formatUSDateStringToSQLDateFormat($modifyPatientWithdrawRequest->withdrawDate);

            }else{
                $patientEntity['withdraw_reason'] = null;
                $patientEntity['withdraw_date'] = null;
            }

            $modifiedData['withdraw'] = $modifyPatientWithdrawRequest->withdraw;
            $modifiedData['withdraw_reason'] = $patientEntity['withdraw_reason'];
            $modifiedData['withdraw_date'] = $patientEntity['withdraw_date'];

            $this->persistenceInterface->update($modifyPatientWithdrawRequest->patientCode, $patientEntity);
            $this->trackerService->writeAction($modifyPatientWithdrawRequest->currentUserId, Constants::ROLE_SUPERVISOR, $patientEntity['study_name'], null, Constants::TRACKER_PATIENT_WITHDRAW, $modifiedData);

            $modifyPatientWithdrawResponse->status = 200;
            $modifyPatientWithdrawResponse->statusText = 'OK';



        }catch(GaelOException $e){

            $modifyPatientWithdrawResponse->body = $e->getErrorBody();
            $modifyPatientWithdrawResponse->status = $e->statusCode;
            $modifyPatientWithdrawResponse->statusText = $e->statusText;

        }catch (Exception $e){
            throw $e;
        }

    }

    public function checkAuthorization(int $userId, string $study){
        $this->authorizationPatientService->setCurrentUserAndRole($userId, Constants::ROLE_SUPERVISOR);
        if( ! $this->authorizationPatientService->isRoleAllowed($study)){
            throw new GaelOForbiddenException();
        };
    }
}
