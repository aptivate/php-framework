<?php

/*
 * Copyright 2009-2012 Aptivate Ltd. and Chris Wilson. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE FREEBSD PROJECT ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE FREEBSD PROJECT OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation
 * are those of the authors and should not be interpreted as representing
 * official policies, either expressed or implied, of Aptivate Ltd.
 *
 * <http://www.freebsd.org/copyright/freebsd-license.html>
 */

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'url_helper.php');

/**
 * Hide some nastiness of the PHP request model ($_SERVER, $_GET,
 * $_POST, etc.), adds support for namespacing of parameters as in
 * Ruby (post[name], post[id], etc) and make it easier to mock up
 * requests for testing. 
 */
class Aptivate_Request extends ArrayObject
{
	/**
	 * app_path is the path *within* the application. Each script
	 * knows its own app_path, and passes it to the Aptivate_Request
	 * constructor. You can link to any script in the app by 
	 * appending that script's app_path (which never changes) to the
	 * app_root (determined automatically by Aptivate_Request).
	 *
	 * E.g. if your application's top directory is in /usr/share/bar,
	 * and your Apache configuration aliases /apps/bar to
	 * /usr/share/bar, and the user accesses
	 * http://yourserver/apps/bar/wee/baz, then the app_root is
	 * "/apps/bar" (since all URLs in the app start with this prefix)
	 * and the app_path (for the current script) is "wee/baz".
	 *
	 * You can (and probably should) include a <base> element in
	 * all your pages which points to the app_root. Then you can
	 * link to any page using just its app_path, which you already
	 * know, and link to any resource using static paths too.
	 *
	 * app_path DOES NOT include a leading slash, so it can be used
	 * for relative paths when a <base> element is included in the page.
	 */
	public $app_path;
	
	/**
	 * app_root DOES include a leading slash, useful for absolute paths
	 * when there is no <base> element, or it's unreliable, e.g. IE CSS.
	 * It also includes a trailing slash, otherwise "/" would be
	 * inconsistent or unsupported.
	 */
	public $app_root;
	
	private $method;
	private $get;
	private $post;
	private $cookies;

	private static function is_suffix($path, $suffix)
	{
		return substr($path, strlen($path) - strlen($suffix)) == $suffix;
	}
	
	private static function remove_suffix($path, $suffix, $alternative)
	{
		if (isset($path) and isset($suffix) and
			static::is_suffix($path, $suffix))
		{
			return substr($path, 0, strlen($path) - strlen($suffix));
		}
		
		return $alternative;
	}
	
	public function __construct($method = null,
		$script_path_within_app = null, $test_request_path = null,
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
		
		/*
		If the request led Apache to a .php file without any
		PATH_INFO, for example accessing / or /ischool/ on the
		server using mod_dir (DirectoryIndex), then Apache won't
		set any PATH_INFO.
		
		In that case, we can't figure out where the app root is
		without help. The caller must tell us, by providing either
		$path (the full path, meaning there is no app root at all)
		or $script_path_within_app (the path to that script relative
		to the root) how to determine the app root.
		
		If a script knows that it's /foobar/index.php relative to
		the app root, then it should pass that as
		$script_path_within_app and we'll strip it from the 
		SCRIPT_NAME to work out the real app root.
		
		In that case we use SCRIPT_NAME instead of REQUEST_URI,
		as it will end with $script_path_within_app regardless of
		whether the web server had to search for the file or not.
		In contrast, in this case REQUEST_URI would probably just be
		/apps/foobar/, and since it doesn't end with /foobar/index.php,
		we can't use it to find the app_root.

		As a convenience when using php-cgi, which doesn't
		set SCRIPT_NAME or SCRIPT_FILENAME and sets PHP_SELF
		to just the PATH_INFO (or empty string if there is
		none), you can omit REQUEST_URI and it will be assumed
		to be set to $script_path_within_app.
		*/
		
		if (isset($_SERVER['REQUEST_URI']))
		{
			$request_uri = $_SERVER['REQUEST_URI'];
		}
		else
		{
			$request_uri = $script_path_within_app;
		}

		// SCRIPT_NAME will start with '/' if set by a web server,
		// and usually not if run from the command line. In which
		// case, ignore the one provided by the php/php-cgi binary
		// and use the one provided by the .php file instead, for
		// convenience to the user running the script from the
		// command line.
		
		if (isset($_SERVER['SCRIPT_NAME']) and
			substr($_SERVER['SCRIPT_NAME'], 0, 1) == '/')
		{
			$script_name = $_SERVER['SCRIPT_NAME'];
		}
		else
		{
			$script_name = $script_path_within_app;
		}

		if (!isset($test_request_path) && !$script_path_within_app)
		{
			throw new Exception("Aptivate_Request must always ".
				"be passed the caller's relative path within ".
				"the app, to help locate the app root.");
			
			// Even if it's not used when PATH_INFO is set;
			// this helps to ensure that callers are correct.
			//
			// But you can omit this when constructing fake requests
			// in unit tests, as you have to supply the app_path
			// and we pretend that the app_root is '/'.
		}
		
		if (isset($test_request_path))
		{
			if (!is_string($test_request_path))
			{
				throw new Exception("test_request_path must be a string");
			}

			// No application root on manually-constructed
			// (artificial) requests.
			
			$this->app_root = "/";
			$this->app_path = $test_request_path;
			$this->script_path_within_app = "/nonexistent.php";
		}
		else
		{
			if (substr($script_path_within_app, 0, 1) != '/')
			{
				throw new Exception("script_path_within_app must ".
					"start with a slash, not $script_path_within_app");
			}
			
			$this->script_path_within_app = $script_path_within_app;
			
			if (!$this->is_suffix($script_name,
				substr($script_path_within_app, 1)))
			{
				throw new Exception("Aptivate_Request constructed ".
					"with $script_path_within_app as the relative ".
					"path, but it must be a suffix of ".
					$script_name);
			}
						
			$this->app_root = $this->remove_suffix($script_name,
				substr($script_path_within_app, 1), FALSE);
			
			/*
			print "script_name = $script_name\n";
			print "script_path_within_app = $script_path_within_app\n";
			print "app_root = ".$this->app_root."\n";
			*/
			
			if (substr($this->app_root, strlen($this->app_root) - 1, 1)
				!= '/')
			{
				throw new Exception("app_root must end with a slash ".
					"(".$this->app_root.")");
			}
			
			if (isset($_SERVER['PATH_INFO']) and $_SERVER['PATH_INFO'])
			{
				$this->app_path = $_SERVER['PATH_INFO'];
			}
			else
			{
				if (substr($request_uri, 0, strlen($this->app_root))
					!= $this->app_root)
				{
					throw new Exception("The computed app_root must ".
						"be a prefix of REQUEST_URI ($request_uri), ".
						"not ".$this->app_root);
				}
				
				if (isset($_SERVER['QUERY_STRING']) and
					$_SERVER['QUERY_STRING'] != '')
				{
					$query_string = "?".$_SERVER['QUERY_STRING'];
					
					if (!$this->is_suffix($request_uri, $query_string))
					{
						throw new Exception("The REQUEST_URI ($request_uri) ".
							"must end with the query string ($query_string)");
					}
				
					$request_uri = substr($request_uri, 0,
						strlen($request_uri) - strlen($query_string));
				}
				
				$this->app_path = substr($request_uri,
					strlen($this->app_root));
					
				// prepend a slash which will be trimmed off below
				$this->app_path = "/".$this->app_path;
			}
			
			// print "app_root = ".$this->app_root;
			// print "app_path = ".$this->app_path;
		}

		if (substr($this->app_root, 0, 1) != '/')
		{
			throw new Exception("Computed app_root must start with ".
				"a slash (".$this->app_root.")");
		}
		
		if (substr($this->app_path, 0, 1) != '/')
		{
			throw new Exception("path must start with a slash ".
				"for us to trim it (".$this->app_path.")");
		}
		
		$this->app_path = substr($this->app_path, 1);
		
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
		// Note: we can't use array_merge here, because if the parameters
		// happen to be numeric, array_merge will renumber them, which is
		// not what we want at all!
		return array_replace($this->get, $this->post);
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
		$req = new Aptivate_Request($this->method,
			$this->script_path_within_app, '/'.$this->app_path,
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
			$uri = $this->app_root.'/'.$this->app_path;
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
