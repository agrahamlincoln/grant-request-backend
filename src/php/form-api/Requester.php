<?php

namespace FormAPI;
use \FormAPI\Repository\RequesterRepository;

class Requester {
  public $requester_id;
  public $name;
  public $email_address;
  public $ip_address;
  public $token;

  public static function getByEmail($email) {
    return (new RequesterRepository())->fetch($email);
  }

  public function save() {
    return (new RequesterRepository())->save($this);
  }

  public static function import($model) {
    $db = new RequesterRepository();

    $requester = $db->fetch($model->email);

    if ($requester == null) {
      $requester = new Requester();
    }
    $requester->name = $model->name;
    $requester->email_address = $model->email;
    $requester->ip_address = $_SERVER['REMOTE_ADDR'];

    return $db->save($requester);
  }
}

?>