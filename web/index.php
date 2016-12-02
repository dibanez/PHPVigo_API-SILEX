<?php
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;

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

$app->post('/login', function(Request $request) use ($app) {
    $user = addslashes(substr($request->get('user'), 0, 50));
    $pass = addslashes(substr($request->get('pass'), 0, 50));
    $sql = "SELECT nombre, usuario, tipo FROM usuarios WHERE usuario = ? AND password = ?";
    $res = $app['db']->fetchAll($sql, [$user, $pass]);
    if ($res) {
      return new JsonResponse( array('mensaje' => 'usuario ok: '.$user), 200 );
    } else {
      return new JsonResponse( array('mensaje' => 'usuario o clave erroneas'), 401 );
    }
});

$app->run();
