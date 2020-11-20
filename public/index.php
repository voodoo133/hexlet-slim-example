<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start();

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
});




$app->get('/users', function ($request, $response) use ($router) {
    $userName = $request->getQueryParam('userName', null);

    $users = json_decode(file_get_contents("users.json"), true);

    if (!empty($userName)) {
        $users = array_filter($users, fn($u) => stripos($u['nickname'], $userName) !== FALSE);
    }

    $messages = $this->get('flash')->getMessages();

    $params = ['formAction' => $router->urlFor('users'), 'users' => $users, 'messages' => $messages];
    
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) use ($router) {
  $params = ['formAction' => $router->urlFor('users/add'), 'nickname' => '', 'email' => '', 'messages' => []];

  return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('users/new');




$app->get('/users/{id}', function ($request, $response, $args) {
    $userId = intval($args['id']);

    $users = json_decode(file_get_contents("users.json"), true);

    $usersFound = array_values(array_filter($users, fn($u) => $u['id'] === $userId));

    $userFound = $usersFound[0] ?? null;

    if (!empty($userFound)) {
        $params = [ 'id' => $userFound['id'], 'nickname' => $userFound['nickname'] ];

        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    } else {
        return $response->withStatus(404);
    }
});




$app->post('/users', function ($request, $response) use ($router) {
    $bodyParamUser = $request->getParsedBodyParam('user');

    $user = [];

    $user['nickname'] = filter_var($bodyParamUser['nickname'], FILTER_SANITIZE_STRING);
    $user['email'] = filter_var($bodyParamUser['email'], FILTER_SANITIZE_EMAIL);

    if (empty($user['nickname']) || filter_var($user['email'], FILTER_VALIDATE_EMAIL) === false) {
        $this->get('flash')->addMessageNow('error', 'Invalid Data');

        $messages = $this->get('flash')->getMessages();

        $params = [
            'formAction' => $router->urlFor('users/add'),
            'nickname' => $bodyParamUser['nickname'],
            'email' => $bodyParamUser['email'],
            'messages' => $messages
        ];

        return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
    }

    if (file_exists('users.json')) {
      $usersJson = file_get_contents("users.json");
      $users = json_decode($usersJson, true);
    }

    if (empty($users)) $users = [];

    $user['id'] = time();

    $users[] = $user;

    file_put_contents("users.json", json_encode($users, JSON_UNESCAPED_UNICODE));

    $this->get('flash')->addMessage('success', 'User has been added');

    return $response->withRedirect($router->urlFor('users'), 302);
})->setName('users/add');




$app->get('/users/{id}/edit', function ($request, $response, $args) use ($router) {
    $userId = intval($args['id']);

    $users = json_decode(file_get_contents("users.json"), true);

    $usersFound = array_values(array_filter($users, fn($u) => $u['id'] === $userId));

    $userFound = $usersFound[0] ?? null;

    if (!empty($userFound)) {
        $params = [ 
            'formAction' => $router->urlFor('users/update', [ 'id' => $userFound['id'] ]),
            'nickname' => $userFound['nickname'], 
            'email' => $userFound['email'],
            'messages' => []
        ];

        return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    } else {
        return $response->withStatus(404);
    }
})->setName('users/edit');




$app->post('/users/{id}', function ($request, $response, $args) use ($router) {
    $userId = intval($args['id']);

    $users = json_decode(file_get_contents("users.json"), true);

    $userFound = count(array_filter($users, fn($u) => $u['id'] === $userId)) === 1;

    if (empty($userFound)) return $response->withStatus(404);

    $bodyParamUser = $request->getParsedBodyParam('user');

    $nickname = filter_var($bodyParamUser['nickname'], FILTER_SANITIZE_STRING);
    $email = filter_var($bodyParamUser['email'], FILTER_SANITIZE_EMAIL);

    if (empty($nickname) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $this->get('flash')->addMessageNow('error', 'Invalid Data');

        $params = [ 
            'formAction' => $router->urlFor('users/update', [ 'id' =>  $userId ]),
            'nickname' => $nickname, 
            'email' => $email,
            'messages' => $this->get('flash')->getMessages()
        ];

        return $this->get('renderer')->render($response->withStatus(422), 'users/edit.phtml', $params);
    }

    array_walk($users, function (&$u) use ($userId, $nickname, $email) {
        if ($u['id'] === $userId) {
            $u['nickname'] = $nickname;
            $u['email'] = $email;
        }
    });

    file_put_contents("users.json", json_encode($users, JSON_UNESCAPED_UNICODE));

    $this->get('flash')->addMessage('success', 'User has been updated');

    return $response->withRedirect($router->urlFor('users'), 302);
})->setName('users/update');


$app->post('/users/{id}/delete', function ($req, $res, $args) use ($router) {
    $userId = intval($args['id']);

    $users = json_decode(file_get_contents("users.json"), true);

    $users = array_filter($users, fn($u) => $u['id'] !== $userId);

    file_put_contents("users.json", json_encode($users, JSON_UNESCAPED_UNICODE));

    $this->get('flash')->addMessage('success', 'User has been deleted');

    return $res->withRedirect($router->urlFor('users'));
});


$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('course');




$app->run();