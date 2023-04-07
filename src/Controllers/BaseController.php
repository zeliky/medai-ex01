<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BaseController
{
    protected function jsonResponse(ResponseInterface $response, $data)
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    }


    protected function errorNotFound(ResponseInterface $response)
    {
        $error = array(
            "error" => 'record not found'
        );

        $response->getBody()->write(json_encode($error));
        return $response

            ->withStatus(404);
    }
    protected function errorResponse(ResponseInterface $response, $message)
    {
        $error = array(
            "error" => $message
        );

        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }

    protected function getRawPostData(ServerRequestInterface $request)
    {
        $body = $request->getBody();
        $contents = $body->getContents();
        return json_decode($contents, true);
    }

}