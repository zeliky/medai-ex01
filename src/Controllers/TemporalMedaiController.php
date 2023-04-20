<?php

namespace App\Controllers;

use App\Beans\MedaiRecord;
use App\Models\TemporalMedai;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TemporalMedaiController extends BaseController
{

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
            $record = TemporalMedai::find($args['id']);
            if (empty($record)){
                return $this->errorNotFound($response);
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
            $newRecord = TemporalMedai::updateRecordByKeys(
                $rawData['first_name'] ?? null,
                $rawData['last_name'] ?? null,
                $rawData['loinc_code'] ?? null,
                $rawData['valid_start_time'] ?? null,
                $rawData['value'] ?? null);
            return $this->jsonResponse($response, [
                'data'=>$newRecord
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $rawData = $this->getRawPostData($request);
            $success = TemporalMedai::deleteRecord(
                $rawData['first_name'] ?? null,
                $rawData['last_name'] ?? null,
                $rawData['loinc_code'] ?? null,
                $rawData['valid_start_time'] ?? null);
            return $this->jsonResponse($response, [
                'success'=>$success
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage());
        }

    }




}