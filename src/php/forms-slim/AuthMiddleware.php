<?php

namespace FormsSlim;
use \FormAPI\AuthService as AuthService;
use \Exception;

class AuthMiddleware extends \Slim\Middleware {

  private $options = array(
    'exceptions' => array()
    );

  public function __construct($exceptions = array()) {
    $this->options['exceptions'] = $exceptions;
    //echo "setting options";
    //var_dump($exceptions);
  }

  public function call() {
    $app = \Slim\Slim::getInstance();
    $request = $app->request();
    $response = $app->response();
    $route = $request->getResourceUri();
    //echo "\r\ncurrent route: " . $route;
    if ($this->whitelisted($route) || $request->isOptions()) {
      //Current route is an exception to this Middleware
      //echo "skipping auth";
      $this->next->call();
    } else {
      //echo "processing auth";
      //Handle the middleware layer

      //Verify that the Authorization header was used
      $authorization_header = $request->headers->get('AUTHORIZATION');
      if ($authorization_header) {
        $authArray = explode(' ', $authorization_header);

        //Verify that the Authorization header specifies 'Bearer'
        if ($authArray[0] == 'Bearer') {
          $jwt = $authArray[1];

          //Validate the JWT
          if ($this->validate($jwt)) {
            if (AuthService::authenticate($jwt)) {
              $this->next->call();
            } else {
              $result['success'] = false;
              $result['message'] = "Error with your token: " . "Token is not registered on the server";

              $this->app->response->setStatus(400); //send 400 instead of 401 because GoDaddy will send back a WWW-Authenticate: Basic header otherwise
              $this->app->response->setBody(json_encode($result));
            }
          }

        } else {
            $result['success'] = false;
            $result['message'] = "No token provided";

            $this->app->response->setStatus(400); //send 400 instead of 401 because GoDaddy will send back a WWW-Authenticate: Basic header otherwise
            $this->app->response->setBody(json_encode($result));
        }
      } else {
        //echo 'No json provided';
        $result['success'] = false;
        $result['message'] = "Authorization header is required for this route: " . $route;

        $response->setStatus(400); //send 400 instead of 401 because GoDaddy will send back a WWW-Authenticate: Basic header otherwise
        $this->app->response->setBody(json_encode($result));
      }
    }
  }

  private function validate($jwt) {
    $secretKey = base64_decode(getenv('JWT_SECRET'));
    try {
      //First, validate the token
      $token = \Firebase\JWT\JWT::decode($jwt, $secretKey, array("HS512"));
    } catch (Exception $crap) {
      $result['success'] = false;
      $result['message'] = "Error with your token: " . $crap->getMessage();

      //Immediately decline access to the API
      $this->app->response->setStatus(400); //send 400 instead of 401 because GoDaddy will send back a WWW-Authenticate: Basic header otherwise
      $this->app->response->setBody(json_encode($result));
      return false;
    }
    return true;
  }

  private function whitelisted($route) {
    foreach ($this->options['exceptions'] as $whitelisted_route) {
      if (fnmatch($whitelisted_route, $route)) { //use fnmatch beacuse it allows for wildcards.. much easier than creating a regex
        return true;
      }
    }

    //return false if we fall through
    return false;
  }
}

?>