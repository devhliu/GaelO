<?php

namespace App\GaelO\Repositories;

use App\GaelO\Interfaces\PersistenceInterface;
use App\GaelO\Util;
use App\OrthancSeries;

class OrthancSeriesRepository implements PersistenceInterface{


    public function __construct(OrthancSeries $orthancSeries){
        $this->orthancSeries = $orthancSeries;
    }

    public function create(array $data) : void {
        $orthancSeries = new OrthancSeries();
        $model = Util::fillObject($data, $orthancSeries);
        $model->save();
    }

    public function update($orthancSeriesID, array $data) : void {
        $model = $this->orthancSeries->find($orthancSeriesID);
        $model = Util::fillObject($data, $model);
        $model->save();
    }

    public function find($orthancSeriesID) : array {
        return $this->orthancSeries->findOrFail($orthancSeriesID)->toArray();
    }

    public function delete($orthancSeriesID) : void {
        $this->orthancSeries->find($orthancSeriesID)->delete();
    }

    public function deletebySeriesInstanceUID(string $seriesInstanceUID) : void {
        $this->orthancSeries->where('series_uid',$seriesInstanceUID)->firstOrFail()->delete();
    }

    public function getAll() : array {
        throw new \Exception('Not Usable in Orthanc Study Repository');
    }

    public function addSeries(string $seriesOrthancID, string $orthancStudyID, ?string $acquisitionDate,
                            ?string $acquisitionTime, ?string $modality, ?string $seriesDescription,
                            ?int $injectedDose, ?string $radiopharmaceutical, ?int $halfLife,
                            ?string $injectedTime,?string $injectedDateTime, ?int $injectedActivity, ?int $patientWeight,
                            int $numberOfInstances, string $seriesUID, ?string $seriesNumber,
                            int $seriesDiskSize, int $seriesUncompressedDiskSize, ?string $manufacturer,
                            ?string $modelName ){

        $data = [
            'orthanc_id' => $seriesOrthancID,
            'orthanc_study_id' => $orthancStudyID,
            'acquisition_date' => $acquisitionDate,
            'acquisition_time' => $acquisitionTime,
            'modality' => $modality,
            'series_description'=> $seriesDescription,
            'injected_dose'=>$injectedDose,
            'radiopharmaceutical'=>$radiopharmaceutical,
            'half_life'=>$halfLife,
            'injected_time'=>$injectedTime,
            'injected_datetime'=>$injectedDateTime,
            'injected_activity'=>$injectedActivity,
            'patient_weight'=>$patientWeight,
            'number_of_instances'=>$numberOfInstances,
            'series_uid'=>$seriesUID,
            'series_number'=>$seriesNumber,
            'disk_size'=>$seriesDiskSize,
            'uncompressed_disk_size'=>$seriesUncompressedDiskSize,
            'manufacturer'=>$manufacturer,
            'model_name'=>$modelName

        ];

        $this->create($data);

    }

    public function updateSeries(string $seriesOrthancID, string $orthancStudyID, ?string $acquisitionDate,
            ?string $acquisitionTime, ?string $modality, ?string $seriesDescription,
            ?int $injectedDose, ?string $radiopharmaceutical, ?int $halfLife,
            ?string $injectedTime,?string $injectedDateTime, ?int $injectedActivity, ?int $patientWeight,
            int $numberOfInstances, string $seriesUID, ?string $seriesNumber,
            int $seriesDiskSize, int $seriesUncompressedDiskSize, ?string $manufacturer,
            ?string $modelName ){

        $data = [
        'orthanc_study_id' => $orthancStudyID,
        'acquisition_date' => $acquisitionDate,
        'acquisition_time' => $acquisitionTime,
        'modality' => $modality,
        'series_description'=> $seriesDescription,
        'injected_dose'=>$injectedDose,
        'radiopharmaceutical'=>$radiopharmaceutical,
        'half_life'=>$halfLife,
        'injected_time'=>$injectedTime,
        'injected_datetime'=>$injectedDateTime,
        'injected_activity'=>$injectedActivity,
        'patient_weight'=>$patientWeight,
        'number_of_instances'=>$numberOfInstances,
        'series_uid'=>$seriesUID,
        'series_number'=>$seriesNumber,
        'disk_size'=>$seriesDiskSize,
        'uncompressed_disk_size'=>$seriesUncompressedDiskSize,
        'manufacturer'=>$manufacturer,
        'model_name'=>$modelName

        ];

        $this->update($seriesOrthancID, $data);

    }

    public function isExistingOrthancSeriesID(string $orthancSeriesID) : bool {
        return empty($this->orthancSeries->find($orthancSeriesID)) ? false : true;
    }

    public function getSeriesBySeriesInstanceUID(string $seriesInstanceUID, bool $includeDeleted) : array {
        if($includeDeleted){
            $series = $this->orthancSeries->with('orthancStudy')->where('series_uid',$seriesInstanceUID)->withTrashed()->firstOrFail()->toArray();
        }else{
            $series = $this->orthancSeries->with('orthancStudy')->where('series_uid',$seriesInstanceUID)->firstOrFail()->toArray();
        }

        return $series;

    }
}
