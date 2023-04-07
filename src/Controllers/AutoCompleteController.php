<?php

namespace App\Controllers;

use App\Models\Clients;
use App\Models\Loinc;
use App\Models\TemporalMedai;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AutoCompleteController extends BaseController
{

    public function clients(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $records = Clients::findByNameAutoComplete($params['q']);
            return $this->jsonResponse($response, $records);

        } catch (\PDOException $e) {
            return $this->errorResponse($response, $e->getMessage());
        }
    }


    public function loinc(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $records = Loinc::findByLoincNumAutoComplete($params['q']);
            return $this->jsonResponse($response, $records);

        } catch (\PDOException $e) {
            return $this->errorResponse($response, $e->getMessage());
        }
    }
}