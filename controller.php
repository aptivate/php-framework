<?php

require_once 'aptivate-php/form.php';
require_once 'aptivate-php/request.php';
require_once 'aptivate-php/url_helper.php';

/**
 * Controller class that handles changing the school settings for
 * school_settings.php.
 */
class Aptivate_Controller
{
	protected $dataAccess;
	protected $request;
	protected $assigns;
	protected $urlComponent;
	protected $defaultView = "index";
	public $setCookies = array();
	
	public function __construct($dataAccess, $request)
	{
		if (!isset($request))
		{
			$request = new Aptivate_Request();
		}
		
		$this->dataAccess = $dataAccess;
		$this->request = $request;
		$this->assigns = array();
		
		// Underscores are a substitute for namespaces for backwards
		// compatibility with PHP 5.2 that doesn't support them.
		// They don't form part of the URL component.
		$this->urlComponent = preg_replace('/.*_/', '',
			get_class($this));
		$this->urlComponent = preg_replace('/Controller$/', '',
			$this->urlComponent);
		$this->urlComponent = preg_replace_callback('/[A-Z]/',
			function($matches)
			{
				return '_'.strtolower($matches[0]);
			}, $this->urlComponent);
		// The translation above will add an underscore in front of
		// each capital letter, which may leave us with an unwanted
		// leading underscore, so remove that.
		$this->urlComponent = preg_replace('/^_/', '',
			$this->urlComponent);
	}
	
	public function dispatch($view_path, $view)
	{
		if ($this->before_filter())
		{
			// Work out which method to call based on the URL path
			// after the prefix of $this->urlComponent
			$view_path = $this->request->app_path;
			
			if (substr($view_path, 0, 1) != '/')
			{
				throw new Exception("Path must start with a slash (/)");
			}
			
			if (substr($view_path, 1, strlen($this->urlComponent)) ==
				$this->urlComponent)
			{
				$view_path = substr($view_path,
					strlen($this->urlComponent) + 2);
			}
			
			if (empty($view_path))
			{
				$view_path = $defaultView;
			}
			
			call_user_func(array($this, $view_path));
		}
		
		if ($this->status_code == 302 and !isset($this->template))
		{
			$this->response_body = "<h1>302 Redirected</h1>";
		}
		else
		{
			$this->response_body = $view->renderTemplate(
				$this->template, $this->assigns);
		}
			
		return $this; // response object;
	}
	
	protected function before_filter()
	{
		if (!$this->require_admin()) return FALSE;
		return TRUE;
	}
	
	public static $salt;
	
	public static function hash_password($password)
	{
		return hash("sha256", $password.static::$salt);
	}

	protected function redirect($url, $params = array())
	{
		if (isset($this->status_code))
		{
			throw new Exception("Already rendered response");
		}
		
		$this->status_code = 302;
		
		$url = UrlHelper::url_params($url, $params);
		$this->redirect_url = $url;
		$this->redirect_full_url = $this->make_redirect_url($url);
		
		error_log("Redirecting to: ".$this->redirect_full_url);
	}
	
	public function make_redirect_url($url)
	{
		if (preg_match('/^\\w+:/', $url))
		{
			$full_url = $url;
		}
		else
		{
			if ($this->request->isSecure())
			{
				$protocol = "https";
				$default_port = 443;
			}
			else
			{
				$protocol = "http";
				$default_port = 80;
			}
			
			$full_url = $protocol."://".$this->request->host;
			
			if (!isset($this->request->port) or 
				$this->request->port != $default_port)
			{
				$full_url .= ":".$this->request->port;
			}

			error_log($url);
			
			if (substr($url, 0, 1) == "/")
			{
				// Assume that if the user gives a path starting
				// with a slash, they really meant relative to the
				// application, and not the web server root. 
				// We get this by appending the request's application
				// root path to the URL, before the user-provided path.
				
				$full_url .= $this->request->app_root;
			}
			else
			{
				// relative to current directory, which is returned
				// by $this->request->requested_uri_without_params
				// including the application root.
				$path_uri = preg_replace('|[^/]+|', '',
					$this->request->requested_uri_without_params());
				$full_url .= $path_uri;
			}
			
			$full_url .= $url;
		}
		
		return $full_url;
	}

	protected function render_template($template, $assigns)
	{
		if (isset($this->status_code))
		{
			throw new Exception("Already rendered response");
		}
		
		$this->status_code = 200;
		$this->template = $template;
		$this->assigns = array_merge($this->assigns, $assigns);
	}

	protected function set_cookie($name, $value)
	{
		$this->setCookies[$name] = $value;
	}
};
?>
