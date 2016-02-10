<?php

namespace FormAPI;

class TwigFactory
{
    private static $factory;
    private $twig;

    public static function getFactory()
    {
        if (!self::$factory)
            self::$factory = new TwigFactory();
        return self::$factory;
    }

    public function getTwig() {

      if (!$this->twig) {
        $loader = new \Twig_Loader_Filesystem('views');
        $twig = new \Twig_Environment($loader, array('cache' => 'views'));
        $this->twig = $twig;
      }

      return $this->twig;
    }
}
?>