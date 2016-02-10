<?php

namespace FormAPI;
use \PDO;

/**
 * Establishes a Database Connection and provides it to the application
 * Uses a common factory design pattern
 */
class PDOFactory
{
  private static $factory;
  private $db;

  public static function getFactory()
  {
    if (!self::$factory)
    self::$factory = new self();
    return self::$factory;
  }

  public function getConnection() {
    //Get connection information from .env
    $server_address = getenv("SQL_SERVER");
    $username = getenv("SQL_USERNAME");
    $password = getenv("SQL_PASSWORD");
    $dbname = getenv("SQL_DATABASE");

    $dataSource = "mysql:host=" . $server_address . ";dbname=" . $dbname;
    //$dataSource = "mysql:host=new.uemf.org;dbname=uemfforms";

    if (!$this->db)
      $this->db = new \PDO($dataSource, $username, $password);

    // Set the error mode to PDO Exceptions.
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $this->db;
  }
}
?>