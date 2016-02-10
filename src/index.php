<?php

/*
 *	FORMS API
 *	=========
 *
 *	Provides an API to send forms via email or through a plugin to external sources
 *
 *	Utilizes Slim Framework
 */

date_default_timezone_set('America/New_York');

require 'vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

//All routes that do not require authentication
$authWhitelist = array('/', '/register', '/docs/*', '/phpinfo', '/timezone');

$app = new \Slim\Slim();

// $app->get('/classcheck', function() use ($app) {
// 	echo "<h1>Checking autoloaded classes</h1>";

// 	$classes = array(
// 		"\FormAPI\PDOFactory"
// 		,"\FormsSlim\AuthMiddleware"
// 		,'\FormAPI\AuthService'
// 		,"\FormAPI\Gr\GrPerson"
// 		,"\FormAPI\Gr\GrantRequest"
// 	);

// 	foreach ($classes as $class) {
// 		echo "<br>Class Name: " . $class . "<br>Class Exists: ";
// 		var_dump(class_exists($class));
// 		echo "<br>";
// 	}
// });

$app->add(new \FormsSlim\AuthMiddleware($authWhitelist));

$app->get('/', function() use ($app) {
	$app->response->redirect('grant_request');
});

$app->get('/phpinfo', function() {
	phpinfo();
});

$app->get('/timezone', function() {
	echo date_default_timezone_get();
});

// $app->group('/docs', function() use ($app) {
// 	$app->get('/', function() {
// 		$contents = file_get_contents('documentation/README.md');
// 		$parsedown = new Parsedown();
// 		echo $parsedown->text($contents);
// 	});

// 	$app->get('/routes', function() {
// 		$contents = file_get_contents('documentation/routes.md');
// 		$parsedown = new Parsedown();
// 		echo $parsedown->text($contents);
// 	});

// });

$app->post('/authenticate', function() {
	echo "successfully authenticated.";
});

$app->options('/register', function() use ($app) {
	echo "{ 'success': 'true' }";
	$app->response->headers->set("Allow", "GET,HEAD,POST,OPTIONS,TRACE");
	$app->response->headers->set("Content-type", "application/json");
});
$app->post('/register', function() {
	$request = \Slim\Slim::getInstance()->request();
	$response = \Slim\Slim::getInstance()->response();

	if($payload = json_decode($request->getBody())) {
		//Successfully decoded JSON object.
		if ($payload->email && $payload->name) {
	    $row = \FormAPI\AuthService::fetchRequester($payload->email, $payload->name);

	    $jwt = \FormAPI\AuthService::generate($row['requester_id'], $row['email_address']);

	    if (\FormAPI\AuthService::save($row['requester_id'], $jwt)) {
	      $result['success'] = true;
	      $result['jwt'] = $jwt;
	      echo json_encode($result);
	    } else {
	    	$result['success'] = false;
	    	$result['message'] = "Unable to save token to server cache";

	    	$response->setStatus(500);
	    	$response->setBody(json_encode($result));
	    }
	  } else {
	  	$result['success'] = false;
			$result['message'] = "Invalid Data, no email or name provided";

			$response->setStatus(400);
			$response->setbody(json_encode($result));
	  }
	} else {
		//Bad Request
		$result['success'] = false;
		$result['message'] = "Invalid Data, could not decode JSON";

		$response->setStatus(400);
		$response->setBody(json_encode($result));
	}
});

//Grants Request Group Route
$app->group('/gr', function() use ($app) {
	require_once("php/routes.php");
});

$app->run();

?>