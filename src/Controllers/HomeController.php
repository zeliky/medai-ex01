<?php

namespace App\Controllers;

use App\Models\TemporalMedai;
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

        $params = $request->getQueryParams();
        $page= $params['page'] ?? 0;
        $pageSize= $params['limit'] ?? 100;
        $results = TemporalMedai::getRecords($params, $page, $pageSize);
        $viewData = [
            'params' => $params,
            'results' => $results['data'],
            'qry' => $results['qry'],
        ];
        return $this->view->render($response, 'index.phtml', $viewData);
    }
}
