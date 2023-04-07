<?php

namespace App\Controllers;

use App\Models\TemporalMedai;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TemporalMedaiController extends BaseController
{

    public function import(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['medai-data'];
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            if (($handle = fopen($uploadedFile, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $num = count($data);
                    echo "<p> $num fields in line $row: <br /></p>\n";
                    $row++;
                    for ($c=0; $c < $num; $c++) {
                        echo $data[$c] . "<br />\n";
                    }
                }
                fclose($handle);
            }
        }
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