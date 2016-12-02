<?php
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\HttpFoundation\JsonResponse;

$app = new Silex\Application();

$app['driver'] = 'pdo_sqlite';
$app['path'] = __DIR__.'/../db/datos.db';

use Silex\Provider\DoctrineServiceProvider;
$app->register(new DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => $app['driver'],
        'path' => $app['path']
    ),
));


$app->get('/hola/{name}', function($name) use ($app) {
    return 'Hola '.$app->escape($name);
});

$app->get('/usuarios', function() use ($app) {
    $sql = "select * from usuarios";
    $datos = $app['db']->fetchAll($sql);
    return new JsonResponse( $datos, 200 );
});


$app->run();
