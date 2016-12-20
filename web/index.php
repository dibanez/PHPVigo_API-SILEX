<?php
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\SecurityJWTServiceProvider;

$app = new Silex\Application();

$app['debug'] = false;

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

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
      return new JsonResponse(
          array(
              'statusCode' => $code,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTraceAsString(),
          )
      );
    } else {
      return new JsonResponse( array('mensaje' => 'Acceso restringido'), 401 );
    }
});

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

$app->get('/users', function(Request $request) use ($app) {
      //$id_token = $app['security.token_storage']->getToken();
      //$app['token'] = $app['security.jwt.encoder']->decode($id_token->credentials);

      if ($app['security.authorization_checker']->isGranted('ROLE_ADMIN')) {
          $sql = "SELECT * FROM usuarios;";
          $datos = $app['db']->fetchAll($sql);
          return new JsonResponse( $datos, 200 );
      } else {
          return new JsonResponse( array('mensaje' => 'no tienes acceso'), 401 );
      }

});

$app->get('/users/{id}', function(Request $request, $id) use ($app) {
      $sql = "SELECT * FROM usuarios WHERE id=?;";
      $datos = $app['db']->fetchAssoc($sql, [$id]);

      if ($app['security.authorization_checker']->isGranted('ROLE_ADMIN')) {
          if ($datos) {
            return new JsonResponse( $datos, 200 );
          } else {
            return new JsonResponse( array('mensaje' => 'Usuario no existe'), 401 );
          }
      } else {
          $id_token = $app['security.token_storage']->getToken();
          $app['token'] = $app['security.jwt.encoder']->decode($id_token->credentials);
          if($app['token']->id == $datos['id']) {
            return new JsonResponse( $datos, 200 );
          } else {
            return new JsonResponse( array('mensaje' => 'no tienes acceso'), 401 );
          }
      }
})->assert('id', '\d+');

$app->post('/users', function(Request $request) use ($app) {
      //$id_token = $app['security.token_storage']->getToken();
      //$app['token'] = $app['security.jwt.encoder']->decode($id_token->credentials);
      $nombre = addslashes(substr($request->get('name'), 0, 50));
      $usuario = addslashes(substr($request->get('user'), 0, 50));
      $password = addslashes(substr($request->get('pass'), 0, 50));
      $tipo = addslashes(substr($request->get('tipo'), 0, 50));
      if ($app['security.authorization_checker']->isGranted('ROLE_ADMIN')) {
          $sqladduser = "INSERT INTO usuarios (nombre, usuario, password, tipo) VALUES (?,?,?,?);";
          $app['db']->executeUpdate($sqladduser, [$nombre, $usuario, $password, $tipo]);
          return new JsonResponse( array('mensaje' => 'usuario agregado'), 200 );
      } else {
          return new JsonResponse( array('mensaje' => 'no tienes acceso'), 401 );
      }

});

$app->put('/users/{id}', function(Request $request, $id) use ($app) {
    if ($app['security.authorization_checker']->isGranted('ROLE_ADMIN')) {
        $datos = array_map( 'addslashes', $request->request->all() );
        $app['db']->update('usuarios', $datos, array('id' => $id));
        return new JsonResponse( array('mensaje' => 'usuario actualizado'), 200 );
    } else {
        return new JsonResponse( array('mensaje' => 'no tienes acceso'), 401 );
    }
})->assert('id', '\d+');

$app->delete('/users/{id}', function(Request $request, $id) use ($app) {
    if ($app['security.authorization_checker']->isGranted('ROLE_ADMIN')) {
        $app['db']->delete('usuarios', array('id' => $id));
        return new JsonResponse( array('mensaje' => "usuario con id: $id Borrado"), 200 );
    } else {
        return new JsonResponse( array('mensaje' => 'no tienes acceso'), 401 );
    }
})->assert('id', '\d+');


$app->run();
