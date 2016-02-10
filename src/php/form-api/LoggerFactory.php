<?php

namespace FormAPI;

class LoggerFactory
{
    private static $factory;
    private $logger;

    public static function getFactory()
    {
        if (!self::$factory)
            self::$factory = new LoggerFactory();
        return self::$factory;
    }

    public function getLogger() {
      //Get connection information from
      $logger_location = getenv('LOG_LOCATION');

      $logger_settings = array(
        'prefix' => 'GrLog_',
        'appendContext' => false,
        'logFormat' => json_encode(array(
            'datetime' => '{date}',
            'logLevel' => '{level}',
            'message'  => '{message}',
            'context'  => '{context}',
        ), JSON_HEX_QUOT)
      );

      if (!$this->logger)
        $this->logger = new \Katzgrau\KLogger\Logger($logger_location, \PSR\Log\LogLevel::DEBUG, $logger_settings);
      return $this->logger;
    }
}
?>