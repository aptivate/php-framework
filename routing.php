<?php
class Aptivate_Routing
{
	static $routes;
	
	public static function configure(array $routes)
	{
		static::$routes = $routes;
	}
	
	public static function controller_for($path)
	{
		if (substr($path, 0, 1) != '/')
		{
			throw new Exception("Path must start with a slash (/)");
		}
		
		$matches = array();
		
		if (preg_match('|^(/[^/]+)(/[^/]+).*|', $path, $matches))
		{
			$controller_path = $matches[1];
			$view_path = $matches[2];
		}
		else
		{
			$controller_path = $path;
			$view_path = null;
		}
		
		foreach (static::$routes as $route_path => $controller_or_view)
		{
			if (is_array($controller_or_view) and $route_path == $path)
			{
				return $controller_or_view;
			}
		}

		foreach (static::$routes as $route_path => $controller_or_view)
		{
			if (!is_array($controller_or_view) and
				$route_path == $controller_path)
			{
				return array($controller_or_view, $view_path);
			}
		}

		throw new Exception("No controller match found for path = ".
			$path." (controller=$controller_path, view=$view_path)");		
	}
	
	public static function path_to($controller, $opt_view = null)
	{
		foreach (static::$routes as $path => $controller_or_view)
		{
			if (!empty($opt_view))
			{
				if (is_array($controller_or_view) and 
					$controller_or_view[0] == $controller and
					$controller_or_view[1] == $opt_view)
				{
					return $path;
				}
				elseif ($controller_or_view == $controller)
				{
					return $path."/".$view;
				}
			}
			elseif (!is_array($controller_or_view) and
				$controller_or_view == $controller)
			{
				// no view requested, must match a route with no view
				return $path;
			}
		}
		
		throw new Exception("No route found to controller = ".
			"$controller, view = ".(empty($opt_view)?"null":$opt_view));
	}
	
	public static function dispatch($dispatch_uri, $request,
		$dataAccess, $view)
	{
		$controller_and_view = static::controller_for($dispatch_uri);
		$controller = new $controller_and_view[0]($dataAccess, $request);
		$controller->dispatch($controller_and_view[1], $view);
		return $controller;
	}
}
?>
