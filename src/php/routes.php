<?php
/**
 * SLIM ROUTES
 * ===========
 *
 * These routes are defined for the SLIM Framework and they enhance the existing routes for the FormAPI
 */

/**
 * SUBMIT ROUTE
 *
 * Submits a request by importing the model of a request from JSON. The application imports the model into the database
 */
$app->options('/submit/', function() use ($app) {
  echo "{ 'success': 'true' }";
  $app->response->headers->set("Allow", "GET,HEAD,POST,OPTIONS,TRACE");
  $app->response->headers->set("Content-type", "application/json");
});
$app->post('/submit/', function() use ($app) {
  $response = array();

  //Generate response for the Add operation
  $add_response = array();

  //Get the model from the post request
  $modelJson = $app->request->getBody();
  //attempt to decode the json into a stdObj
  if($model = json_decode($modelJson)) {
    //Json successfully decoded
    $grantRequest = new \FormAPI\Gr\GrantRequest($model);
    $result = $grantRequest->saveModel();

    $response['request_id'] = $result['request_id'];
  } else {
    //bad request
    $app->response->setStatus(400);
    $result['success'] = false;
    $result['Message'] = "Could not decode json object";
  }

  //Send the response back to the poster
  echo json_encode($response);
});

/**
 * GET ROUTE
 *
 * Retrieves a model from the database and returns it in a human-readable format as composed by the twig template.
 */
$app->options('/get/:id', function() use ($app) {
  echo "{ 'success': 'true' }";
  $app->response->headers->set("Allow", "GET,HEAD,POST,OPTIONS,TRACE");
  $app->response->headers->set("Content-type", "application/json");
});
$app->get('/get/:id', function($id = '') {
  $greq = new FormAPI\Gr\GrantRequest($id);
  $model = $greq->getModel();

  $twig = \FormAPI\TwigFactory::getFactory()->getTwig();
  echo $twig->render('request.html', array("model" => $model));
});

/**
 * SEND ROUTE
 *
 * Sends the user-defined model via email to users defined by the appEmail table. If no model is found for the ID, nothing is sent.
 */
$app->options('/send/:id', function() use ($app) {
  echo "{ 'success': 'true' }";
  $app->response->headers->set("Allow", "GET,HEAD,POST,OPTIONS,TRACE");
  $app->response->headers->set("Content-type", "application/json");
});
$app->get('/send/:id', function($id = '') use ($app) {
  if ($id == 0) {
    $response['success'] = false;
    $response['message'] = "No request found";
    $app->response->setStatus(400); #BAD REQUEST
  } else {
    $mailer = new \FormAPI\Mailer(1);

    $greq = new FormAPI\Gr\GrantRequest($id);
    $model = $greq->getModel();

    $shortTitle = $model['proposal']['shortTitle'];
    $subject = "Grants Request: " . $shortTitle;

    //Render template
    $twig = \FormAPI\TwigFactory::getFactory()->getTwig();
    $body = $twig->render('request.html', array("model" => $model));

    $email_success = $mailer->send($subject, $body);
    if ($email_success) {
      $response['success'] = true;
      $response['message'] = "Successfully notified grants administrators";
    } else {
      $response['success'] = false;
      $response['message'] = "Something went wrong while attempting to send the email";
      $app->response->setStatus(500); #INTERNAL SERVER ERROR
    }
  }

  echo json_encode($response);
});
?>