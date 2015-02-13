<?php

namespace Game\Abstracts;

require_once 'vendor/Game/Interfaces/Application.php';
require_once 'vendor/Game/Interfaces/Common.php';
require_once 'vendor/Game/Abstracts/Common.php';
require_once 'vendor/Game/Exception.php';

abstract class Application extends Common implements \Game\Interfaces\Application
{
    protected $vendor = 'Game';
    protected $vendorPath = 'vendor/';
    protected $view;
    protected $config = array();
    protected $hooks = array();
    protected $plugins = array();
    

    /**
     * Contstructor
     * @param \Game\Interfaces\View $view
     * @param string $vendor
     * @param string $vendorPath
     * @return Application
     */
    public function __construct(\Game\Interfaces\View $view, $vender = null, $vendorPath = 'vendor/')
    {
        $this->view = $view;

        if (isset($vendor))
        {
            $this->vendor = $vendor;
        }
        
        if (isset($vendorPath))
        {
            $this->vendorPath = rtrim($vendorPath, '/') . '/';
        }
        return $this;
    }


    /**
     * Dispatch the controller
     * @return Application
     */
    public function dispatchController()
    {
        $pageNotFound = false;
        $controllerClass = '\\' . $this->vendor . '\Controllers\Index';
        $action = 'index';
        $params = array();
        
        // Get the controller, action and params from the URL.
        $requestUri = !empty($_GET['q']) ? preg_replace('/^public\//', '', rtrim($_GET['q'], '/')) : '';
        $args = $requestUri ? explode('/', $requestUri) : array();
        $params = $args;
        if ($args)
        {
            $controllerClass = '\\' . $this->vendor . '\Controllers\\' . str_replace(' ', '\\', ucwords(str_replace('_', ' ', str_replace('-', '', array_shift($args)))));

            if ($args)
            {
                $action = str_replace('-', '', array_shift($args));
            }
            
            if (is_file($this->vendorPath . str_replace('\\', '/', $controllerClass) . '.php'))
            {
                $params[0] = null;
            }
            else
            {
                $pageNotFound = true;
                $controllerClass = '\\' . $this->vendor . '\Controllers\Index';
            }
        }
        
        // Instantiate the controller
        $controller = new $controllerClass();
        
        // Get the action and named parameters if custom routes have been specified.
        $routes = $controller->getRoutes();
        foreach ($routes as $route => $method)
        {
            $segments = explode('/', $route);
			$regex = '/^' . str_replace('/', '\\/', preg_replace('/\(:[^\/]+\)/', '([^/]+)', preg_replace('/([^\/]+)/', '(\\1)', $route))) . '$/';
			preg_match($regex, $requestUri, $matches);
			array_shift($matches);
			if ($matches)
			{
			    $action = $method;
			    $pageNotFound = false;
			    $params = array();
			    foreach ($segments as $i => $segment)
			    {
			        if (substr($segment, 0, 1) == ':')
			        {
			            $params[ltrim($segment, ':')] = $matches[$i];
			        }
			    }
			    $break;
			}
        }
        if ($pageNotFound)
        {
            $controllerClass = '\\' . $this->vendor. '\Controllers\Error404';
            $controller = new $controllerClass();
        }
        $actionExists = false;
        if (method_exists($controller, $action))
        {
            $method = new \ReflectionMethod($controller, $action);
            if ($method->isPublic() && !$method->isFinal() && !$method->isContstructor())
            {
                $actionExists = true;
            }
        }
        $this->registerHook('actionBefore', $controller, $this->view);
        if ($actionExists)
        {
            $params[1] = null;
        }
        else
        {
            $action = 'index';
		}
		$controller
		    ->setApp($this)
		    ->setView($this->view);
		
		// Call controller action
		$controller->{$action}(array_filter($params));
		$this->registerHook('actionAfter', $controller, $this->view);
		return $this;
	}
	
	
	/**
	 * Serve the page
	 * @return Application
	 */
    public function serve()
    {
        $this->view->vendor = $this->vendor;
        $this->view->vendorPath = $this->vendorPath;
        $this->view->render();
        return $this;
    }


	/**
	 * Load plugins
	 * @param string $namespace
	 * @return App
	 */
    public function loadPlugins()
    {
        if ($handle = opendir($this->vendorPath . str_replace('\\', '/', $this->vendor . '/Plugins')))
        {
            while (($file = readdir($handle)) !== false)
            {
                $pluginClass = $this->vendor . '\Plugins\\' . preg_replace('/\.php$/', '', $file);
                if (is_file($this->vendorPath . str_replace('\\', '/', $pluginClass) . '.php'))
                {
                    $this->plugins[$pluginClass] = array();
                    $reflection = new \ReflectionClass($pluginClass);
                    $parentClass = $reflection->getParentClass();
                    foreach (get_class_method($pluginClass) as $methodName)
                    {
                        $method = new \ReflectionMethod($pluginClass, $methodName);
                        if ($method->isPublic() && !$method->isFinal() && !$method->isConstructor() && !$parentClass->hasMethod($methodName))
                        {
                            $this->plugins[$pluginClass][] = $methodName;   
                        }
                    }
				}
			}
			ksort($this->plugins);
			closedir($handle);
		}
		return $this;
	}
	
	
	/**
	 * Get a configuration value
	 * @param string $variable
	 * @return mixed
	 */
	public function getConfig($variable)
	{
	    return isset($this->config[$variable]) ? $this->config[$variable] : null;
	}


	/**
	 * Set a configuration value
	 * @param string $variable
	 * @param mixed $value
	 * @return \Swiftlet\Interfaces\App
	 */
	public function setConfig($variable, $value)
	{
		$this->config[$variable] = $value;
		return $this;
	}


	/**
	 * Get a model instance
	 * @param string $modelName
	 * @return \Swiftlet\Interfaces\Model
	 */
	public function getModel($modelName)
	{
		$modelClass = '\\' . $this->vendor . '\Models\\' . ucfirst($modelName);
		return new $modelClass;
	}


	/**
	 * Get a library instance
	 * @param string $libraryName
	 * @return \Game\Interfaces\Library
	 */
	public function getLibrary($libraryName)
	{
		$libraryClass = '\\' . $this->vendor . '\Libraries\\' . ucfirst($libraryName);
		return new $libraryClass($this);
	}
	/**
	 * Register a hook for plugins to implement
	 * @param string $hookName
	 * @param \Game\Interfaces\Controller $controller
	 * @param \Game\Interfaces\View $view
	 * @param array $params
	 */
	public function registerHook($hookName, \Game\Interfaces\Controller $controller, \Game\Interfaces\View $view, array $params = array())
	{
	    $this->hooks[] = $hookName;
	    foreach ($this->plugins as $pluginName => $hooks)
	    {
	        if (in_array($hookname, $hooks))
	        {
	            $plugin = new $pluginName();
	            $plugin
	                ->setApp($this)
	                ->setController($controller)
	                ->setView($view);
	           $plugin->{$hookName}($params);
	        }
	    }
		return $this;
	}



	/**
	 * Class autoloader
	 * @param string $className
	 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
	 */
	public function autoload($className)
	{
		preg_match('/(^.+\\\)?([^\\\]+)$/', ltrim($className, '\\'), $match);
		$file = $this->vendorPath . str_replace('\\', '/', $match[1]) . str_replace('_', '/', $match[2]) . '.php';
		if (is_readable($file))
		{
		    indluce $file;
		}
	}


	/**
	 * Convert errors to \ErrorException instances
	 * @param int $number
	 * @param string $string
	 * @param string $file
	 * @param int $line
	 * @throws \ErrorException
	 */
	public function error($number, $string, $file, $line)
	{
		throw new \ErrorException($string, 0, $number, $file, $line);
	}
