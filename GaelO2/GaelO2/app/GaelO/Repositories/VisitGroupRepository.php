<?php

namespace App\GaelO\Repositories;

use App\Models\VisitGroup;
use App\GaelO\Interfaces\PersistenceInterface;
use App\GaelO\Interfaces\VisitGroupRepositoryInterface;
use App\GaelO\Util;
use Exception;

class VisitGroupRepository implements PersistenceInterface, VisitGroupRepositoryInterface {

    public function __construct(VisitGroup $visitGroup){
        $this->visitGroup = $visitGroup;
    }

    public function create(array $data){
        $visitGroup = new VisitGroup();
        $model = Util::fillObject($data, $visitGroup);
        $model->save();
    }

    public function update($id, array $data) : void {
        $model = $this->visitGroup->find($id);
        $model = Util::fillObject($data, $model);
        $model->save();
    }

    public function find($id){
        return $this->visitGroup->findOrFail($id)->toArray();
    }

    public function delete($id) : void {
        $this->visitGroup->findOrFail($id)->delete();
    }

    public function getAll() : array {
        throw new Exception('Not Needed');
    }

    public function createVisitGroup(String $studyName, String $modality)  : void {

        $data = [
            'study_name'=> $studyName,
            'modality' => $modality
        ];

        $this->create($data);

    }

    public function hasVisitTypes(int $visitGroupId) : bool {
        $visitTypes = $this->visitGroup->find($visitGroupId)->visitTypes()->get();
        return $visitTypes->count()>0 ? true : false;
    }

    public function isExistingVisitGroup(String $studyName, String $modality) : bool {
        $visitGroup = $this->visitGroup->where([['study_name', '=', $studyName], ['modality', '=', $modality]])->get();
        return sizeof($visitGroup)>0;
    }

}

?>
