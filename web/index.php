<?php
require_once __DIR__.'/../vendor/autoload.php'; 

$app = new Silex\Application(); 

$app->get('/hola/{name}', function($name) use($app) { 
    return 'Hola '.$app->escape($name); 
}); 

$app->run(); 
