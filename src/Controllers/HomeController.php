<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\PhpRenderer;
class HomeController
{
    protected $view;

    public function __construct()
    {
        $this->view = new PhpRenderer(__DIR__ . '/../Views');
    }

    public function index(Request $request, Response $response, array $args)
    {
        $message = 'Hello, !';
        $viewData = [
            'message' => $message
        ];
        return $this->view->render($response, 'index.phtml', $viewData);
    }
}
