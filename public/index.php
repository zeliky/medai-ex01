<?php

require_once __DIR__ . '/../vendor/autoload.php';
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . '/../logs/application.log');



use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;
use \App\Controllers\TemporalMedaiController;
use \App\Controllers\HomeController;
use \App\Controllers\AutoCompleteController;




$app = AppFactory::create();


$app->addRoutingMiddleware();
$app->add(new BasePathMiddleware($app));



$app->addErrorMiddleware(true, true, true);

$app->get('/', HomeController::class . ':index');
$app->get('/medai/{id}', TemporalMedaiController::class . ':get');
$app->get('/medai', TemporalMedaiController::class . ':search');
$app->post('/medai', TemporalMedaiController::class . ':add');
$app->put('/medai', TemporalMedaiController::class . ':update');
$app->delete('/medai', TemporalMedaiController::class . ':delete');
$app->get('/autocomplete/clients', AutoCompleteController::class . ':clients');
$app->get('/autocomplete/loinc', AutoCompleteController::class . ':loinc');





$app->run();