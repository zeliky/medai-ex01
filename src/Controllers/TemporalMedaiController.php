<?php

namespace App\Controllers;

use App\Beans\MedaiRecord;
use App\Models\Clients;
use App\Models\TemporalMedai;
use http\Exception\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TemporalMedaiController extends BaseController
{

    public function importExcel(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['filename'];
        $params = $request->getParsedBody();
        $importMode =$params['import_mode'] ?? 'clean';

 echo "<pre>";
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            if ($importMode=='clean'){
                TemporalMedai::cleanData();
            }

            $first = true;
            $headers = [];
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($uploadedFile->getFilePath());
            $sheetData = $spreadsheet->getActiveSheet()->toArray();
            $i = 0;;
            foreach ($sheetData as $data) {
                if ($first) {
                    $first = false;
                    $headers = $data;

                } else {
                    $row = array_combine($headers, $data);
                    try {
                        TemporalMedai::addRecord([
                                'first_name' => $row['First name'] ?? '',
                                'last_name' => $row['Last name'] ?? '',
                                'loinc_code' => $row['LOINC-NUM'] ?? '',
                                'value' => $row['Value'] ?? '',
                                'unit' => $row['Unit'] ?? '',
                                'valid_start_time' => $row['Valid start time'] ?? '',
                                'transaction_time' => $row['Transaction time'] ?? '',
                            ]
                        );
                    } catch (\Exception $exception) {
                        error_log('cannot add record ' . json_encode($data) . ": " . $exception->getMessage());
                    }
                }
            }
        }
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['filename'];
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $first = true;
            $headers = [];
            if (($handle = fopen($uploadedFile->getFilePath(), "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if ($first){
                        $first = false;
                        $headers = $data;
                        continue;
                    } else {
                        $row = array_combine($headers,$data) ;
                        try {
                            TemporalMedai::addRecord([
                                    'first_name' => $row['First name'] ?? '',
                                    'last_name' => $row['Last name'] ?? '',
                                    'loinc_code' => $row['LOINC-NUM'] ?? '',
                                    'value' => $row['Value'] ?? '',
                                    'unit' => $row['Unit'] ?? '',
                                    'valid_start_time' => $row['Valid start time'] ?? '',
                                    'transaction_time' => $row['Transaction time'] ?? '',
                                ]
                            );
                        }catch (\Exception $exception){
                            error_log('cannot add record ' . json_encode($data). ": ". $exception->getMessage());
                        }
                    }

                }
                fclose($handle);
            }
        }
        return $response->withStatus(302)->withHeader('Location', '/');
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            if (empty($args['id'])){
                return $this->missingInvalidArgument($response, 'id');
            }
            $record = TemporalMedai::find($args['id']);
            if (empty($record)){
                return $this->errorNotFound($response);
            }
            $client = Clients::find($record['client_id']);
            if ($client){
                $record['first_name'] = $client->first_name;
                $record['last_name'] = $client->last_name;
            }

            return $this->jsonResponse($response, $record);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }
    }


    public function search(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $records = TemporalMedai::getRecords($params);
            return $this->jsonResponse($response, $records);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }
    }

    public function retreive(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $now = new \DateTime();
            $params = $request->getQueryParams();
            $clientId = $params['client_id'] ?? null;
            $loincCode = $params['loinc_id'] ?? null;
            $validTime = ($params['at_date'] ?? $now->format('Y-m-d'))  .' ' . ($params['at_hour'].':59' ?? '23:59:59');
            $viewTime = ($params['pov_date'] ?? $now->format('Y-m-d'))  .' ' . ($params['pov_hour'].':59' ?? '23:59:59');
            $records = TemporalMedai::getLatestRecord($clientId, $loincCode, $validTime, $viewTime);
            return $this->jsonResponse($response, $records);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }
    }

    public function add(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $rawData = $this->getRawPostData($request);
            $newRecord = TemporalMedai::addRecord($rawData);
            return $this->jsonResponse($response, [
                'data' => $newRecord
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }

    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {

        try {
            $rawData = $this->getRawPostData($request);

            $record = new MedaiRecord($rawData);
            $res = $this->checkRequired($record , ['client_id','loinc_code','valid_start_time','value'],$response);
            if ($res)
                return $res;

            $newRecord = TemporalMedai::updateRecordByKeys($record);

            return $this->jsonResponse($response, [
                'data'=>$newRecord
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }
    }

    public function deleteById(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            if (empty($args['id'])){
                return $this->missingInvalidArgument($response, 'id');
            }
            $success = TemporalMedai::deleteRecordById($args['id']);
            if (!$success) {
                $this->missingInvalidArgument($response,'id');
            }

            return $this->jsonResponse($response, [
                'success'=>$success
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }

    }


    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $rawData = $this->getRawPostData($request);
            $success = TemporalMedai::deleteRecordNaturalKey(
                $rawData['first_name'] ?? null,
                $rawData['last_name'] ?? null,
                $rawData['loinc_code'] ?? null,
                $rawData['valid_start_time'] ?? null,
                    (!is_null($rawData['deleted_at']) ? \DateTime::createFromFormat('Y-m-d H:i:s',$rawData['deleted_at']) :null)

            );
            return $this->jsonResponse($response, [
                'success'=>$success
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }

    }

    private function checkRequired(MedaiRecord $record,$fields, ResponseInterface $response){
        foreach( $fields as $f){
            if(is_null($record->$f)) {
                return $this->missingInvalidArgument($response, $f);
            }
        }
        return null;

    }




}