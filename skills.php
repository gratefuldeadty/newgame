<?php

class Game 
{
    private static $engine;
    
    // Do not allow object instantiation.
    private function __construct() {}
    private function __destruct() {}
    private function __clone() {}
    
    /**
     * Handle calls to static methods
     * @param string $name
     * @param array $params
     * @return mixed - Callback results.
     */
    public static function __callStatic($name, $params)
    {
        static $initialized = false;
        if (!$initialized)
        {
            require_once __DIR__.'/autoload.php';
            self::$engine = new \game\Engine();
            $initialized = true;
        }
        return \game\core\Dispatcher::invokeMethod([self::$engine, $name], $params);
    }
    
    
    /**
     * @return object app instance
     */
    public static function app()
    {
        return self::$engine;    
    }
}
