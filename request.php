<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'url_helper.php');

/**
 * Hide some nastiness of the PHP request model ($_SERVER, $_GET,
 * $_POST, etc.), adds support for namespacing of parameters as in
 * Ruby (post[name], post[id], etc) and make it easier to mock up
 * requests for testing. 
 */
class Aptivate_Request extends ArrayObject
{
	public $app_path;
	public $app_root;
	private $method;
	private $get;
	private $post;
	private $cookies;
	
	private static function remove_suffix($path, $suffix, $alternative)
	{
		if (isset($path) and isset($suffix) and
			substr($path, strlen($path) - strlen($suffix)) == $suffix)
		{
			return substr($path, 0, strlen($path) - strlen($suffix));
		}
		
		return $alternative;
	}
	
	public function __construct($method = null, $path = null,
		array $getParams = null, array $postParams = null, 
		array $cookies = null)
	{
		if (isset($method))
		{
			$this->method = $method;
		}
		elseif (isset($_SERVER['REQUEST_METHOD']))
		{
			$this->method = $_SERVER['REQUEST_METHOD'];
		}
		else
		{
			$this->method = null;
		}

		if (isset($_SERVER['REQUEST_URI']) and
			isset($_SERVER['PATH_INFO']))
		{
			$path_without_query = $_SERVER['REQUEST_URI'];
			
			if (isset($_SERVER['QUERY_STRING']))
			{
				$path_without_query = $this->remove_suffix(
					$path_without_query, /* haystack */
					'?'.$_SERVER['QUERY_STRING'], /* needle */
					$path_without_query /* alternative */);
			}
			
			$this->app_root = $this->remove_suffix(
				$path_without_query, /* haystack */
				$_SERVER['PATH_INFO'], /* needle */
				null /* alternative, leaves $this->app_root unset */);
			/*
			print_r($_SERVER);
			print("path without query = $path_without_query\n");
			print("app root = $this->app_root\n");
			*/
		}

		if (isset($this->app_root))
		{
			$this->app_path = $_SERVER['PATH_INFO'];
		}
		elseif (isset($path))
		{
			if (!is_string($path))
			{
				throw new Exception("Path must be a string");
			}

			// No application root on manually-constructed
			// (artificial) requests.
			
			$this->app_root = "";
			$this->app_path = $path;
		}
		elseif (isset($_SERVER['REQUEST_URI']))
		{
			// leave unset to cause an error if used
		}
		
		$this->get     = isset($getParams)  ? $getParams  : $_GET;
		$this->post    = isset($postParams) ? $postParams : $_POST;
		$this->cookies = isset($cookies)    ? $cookies    : $_COOKIE;
		
		$this->host = isset($_SERVER['HTTP_HOST']) ?
			$_SERVER['HTTP_HOST'] : null;
		$this->port = isset($_SERVER['SERVER_PORT']) ?
			$_SERVER['SERVER_PORT'] : null;
	}
	
	public function param($paramName, $default = null)
	{
		if (isset($this->post[$paramName]))
		{
			return $this->post[$paramName];
		}
		
		if (isset($this->get[$paramName]))
		{
			return $this->get[$paramName];
		}

		return $default;
	}
	
	public function params()
	{
		return array_merge($this->get, $this->post);
	}
	
	public function method()
	{
		return $this->method;
	}
	
	public function isPost()
	{
		return ($this->method == 'POST');
	}
	
	/**
	 * Override ArrayObject::offsetGet, to overload [] operator, to
	 * support structured parameter names (namespaces) by returning
	 * a Request object whose parameters are a subset of all the
	 * request parameters, with a particular prefix.
	 */
	public function offsetGet($index)
	{
		$req = new Aptivate_Request($this->method, $this->app_path,
			isset($this->get[$index])  ? $this->get[$index]  : array(),
			isset($this->post[$index]) ? $this->post[$index] : array(),
			$this->cookies);
		$req->app_root = $this->app_root;
		return $req;
	}
	
	/**
	 * @return the value of a specific named cookie, or null if the
	 * cookie does not exist (was not sent by the client in the
	 * request).
	 */
	public function cookie($name)
	{
		if (isset($this->cookies[$name]))
		{
			return $this->cookies[$name];
		}
		else
		{
			return null;
		}
	}

	public function requested_uri($include_app_root, $include_params)
	{
		if ($include_app_root)
		{
			$uri = $this->app_root . $this->app_path;
		}
		else
		{
			$uri = $this->app_path;
		}
		
		if ($include_params)
		{
			return UrlHelper::url_params($uri, $this->get);
		}
		else
		{
			return $uri;
		}
	}
	
	public function isSecure()
	{
		return isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] != "" and
			$_SERVER['HTTPS'] != "off";
	}
};

?>
