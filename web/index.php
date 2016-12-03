<?php
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\SecurityJWTServiceProvider;

$app = new Silex\Application();

$app['debug'] = true;

$app['driver'] = 'pdo_sqlite';
$app['path'] = __DIR__.'/../db/datos.db';

$app['serverName'] = "midominio.com";
$app['algorithm'] = ["HS256"];
$app['secret'] = 'mi clave secreta';

$app['users'] = function () use ($app) {
    $users = [
        'ADMIN' => [
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'enabled' => true
        ],
        'USER' => [
            'roles' => ['ROLE_USER'],
            'enabled' => true
        ]
    ];
    return new Symfony\Component\Security\Core\User\InMemoryUserProvider($users);
};

$app['security.firewalls'] = [
    'login' => [
        'pattern' => 'login',
        'anonymous' => true,
    ],
    'secured' => [
        'pattern' => '^.*$',
        'logout' => ['logout_path' => '/logout'],
        'users' => $app['users'],
        'jwt' => [
            'use_forward' => true,
            'require_previous_session' => false,
            'stateless' => true,
        ]
    ],
];

$app['security.jwt'] = [
    'secret_key' => $app['secret'],
    'life_time'  => 86400,
    //'life_time'  => 60,
    'algorithm'  => ["HS256"],
    'options'    => [
        'username_claim' => 'sub', // default name, option specifying claim containing username
        'header_name'  => 'X-Access-Token',
        'token_prefix' => 'Bearer',
    ]
];

$app->register(new SecurityServiceProvider());
$app->register(new SecurityJwtServiceProvider());

$app->register(new DoctrineServiceProvider(), [
    'db.options' => [
        'driver' => $app['driver'],
        'path' => $app['path']
    ],
]);

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
    $sql = "SELECT id, nombre, usuario, tipo FROM usuarios WHERE usuario = ? AND password = ?";
    $res = $app['db']->fetchAll($sql, [$user, $pass]);
    if ($res) {

      $data['sub'] = $res[0]['tipo'];
      $data['id'] = $res[0]['id'];
      $data['usuario'] = $res[0]['usuario'];
      $data['nombre'] = $res[0]['nombre'];

      $jwt = $app['security.jwt.encoder']->encode($data);
      return new JsonResponse( array('token' => $jwt), 200 );

    } else {
      return new JsonResponse( array('mensaje' => 'usuario o clave erroneas'), 401 );
    }
});

$app->run();
