<?php
require_once(dirname(__FILE__).'/../request.php');

class AptivateRequestTest extends PHPUnit_Framework_TestCase
{
	private function assertAptivateRequestRoot($expectedAppRoot)
	{
		$req = new Aptivate_Request(null, '/index.php');
		$this->assertEquals($expectedAppRoot, $req->app_root);
		$this->assertEquals('', $req->app_path);
	}

	private function assertAptivateRequestPhpFile($expectedAppRoot,
		$phpFile)
	{
		$req = new Aptivate_Request(null, "/$phpFile");
		$this->assertEquals($expectedAppRoot, $req->app_root);
		$this->assertEquals($phpFile, $req->app_path);
	}

	private function assertAptivateRequestSchoolSettings($expectedAppRoot)
	{
		$req = new Aptivate_Request(null, '/CodeIgniter/index.php');
		$this->assertEquals($expectedAppRoot, $req->app_root);
		$this->assertEquals('school/settings', $req->app_path);
	}

	/**
	 * php-cgi doesn't set SCRIPT_NAME or SCRIPT_FILENAME, and sets
	 * PHP_SELF to an empty string. CodeIgniter can't tell what
	 * controller you want unless you set REQUEST_URI in the
	 * environment, but otherwise you can leave it unset.
	 * Aptivate_Request will pretend that you set it to the
	 * $script_path_within_app set by whoever created it.
	 */
	public function testCommandLinePhpCgiEnvironment()
	{
		$old_server = $_SERVER;
		
		try
		{
			// simulated command line:
			// REQUEST_URI='/' php-cgi -f index.php
			// (http://stackoverflow.com/a/11965479/648162)
		
			unset($_SERVER['REQUEST_URI']);
			unset($_SERVER['QUERY_STRING']);
			unset($_SERVER['REQUEST_METHOD']);
			unset($_SERVER['SCRIPT_NAME']);
			unset($_SERVER['SCRIPT_FILENAME']);
			unset($_SERVER['PATH_INFO']);
			$_SERVER['PHP_SELF'] = '';
			$this->assertAptivateRequestPhpFile('/', 'index.php');

			// the only difference is the $script_path_within_app
			// passed to new Aptivate_Request().
			$this->assertAptivateRequestPhpFile('/', 'auth.php');
			
			// simulated command line: REQUEST_URI='/school/settings'
			// php-cgi -f CodeIgniter/index.php
			// (http://stackoverflow.com/a/11965479/648162)
			//
			// CodeIgniter will die unless SCRIPT_NAME is set to
			// something, and we configured it to use PATH_INFO
			// to determine which controller to run, so we have
			// to set that too.
			$_SERVER['PATH_INFO'] = '/school/settings';
			$_SERVER['SCRIPT_NAME'] = 'CodeIgniter/index.php';
			$this->assertAptivateRequestSchoolSettings('/');
		}
		catch (Exception $e)
		{
			$_SERVER = $old_server;
			throw $e;
		}
		
		$_SERVER = $old_server;
	}

	/**
	 * Using php (not php-cgi) sets PHP_SELF == SCRIPT_NAME ==
	 * SCRIPT_FILENAME, and doesn't set PATH_INFO at all.
	 */
	public function testCommandLinePhpEnvironment()
	{
		$old_server = $_SERVER;
		
		try
		{
			// simulated command line:
			// REQUEST_URI='/' php index.php
		
			$_SERVER['REQUEST_URI'] = '/';
			unset($_SERVER['QUERY_STRING']);
			unset($_SERVER['REQUEST_METHOD']);
			$_SERVER['SCRIPT_NAME'] = 'index.php';
			$_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_NAME'];
			unset($_SERVER['PATH_INFO']);
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestRoot('/');

			// simulated command line:
			// REQUEST_URI='/auth.php' php auth.php

			$_SERVER['REQUEST_URI'] = '/auth.php';
			$_SERVER['SCRIPT_NAME'] = 'auth.php';
			$_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_NAME'];
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestPhpFile('/', 'auth.php');

			// simulated command line:
			// REQUEST_URI='/school/settings' php CodeIgniter/index.php
		
			$_SERVER['REQUEST_URI'] = '/school/settings';
			$_SERVER['SCRIPT_NAME'] = 'CodeIgniter/index.php';
			$_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_NAME'];
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestSchoolSettings('/');
		}
		catch (Exception $e)
		{
			$_SERVER = $old_server;
			throw $e;
		}
		
		$_SERVER = $old_server;
	}

	/**
	 * Apache sets PHP_SELF = SCRIPT_NAME + PATH_INFO, e.g.
	 * /ischool/index.php/foo/bar (when PATH_INFO is '/foo/bar'
	 * for http://localhost/ischool/foo/bar.
	 */
	public function testApacheModPhpEnvironment()
	{
		$old_server = $_SERVER;
		
		try
		{
			// Request for http://localhost/ischool/?foo=bar
			// with Alias /ischool /home/installuser/Dropbox/projects/ischool/website/web
		
			$_SERVER['REQUEST_URI'] = '/ischool/?foo=bar';
			$_SERVER['QUERY_STRING'] = 'foo=bar';
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_SERVER['SCRIPT_NAME'] = '/ischool/index.php';
			$_SERVER['SCRIPT_FILENAME'] = '/home/installuser/Dropbox/projects/ischool/website/web/index.php';
			unset($_SERVER['PATH_INFO']);
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestRoot('/ischool/');

			// Request for http://localhost/ischool/index.php?foo=bar
			//
			// For anything other than index.php, we must keep the
			// filename in the app_path, otherwise local links
			// on that page will go to a different php file.
			//
			// We might as well do that for index.php too, rather
			// than treating it as a special case on the assumption
			// that the web server is configured with
			// "DirectoryIndex index.php" (which it might not be).
			$_SERVER['REQUEST_URI'] = '/ischool/index.php?foo=bar';
			$this->assertAptivateRequestPhpFile('/ischool/', 'index.php');

			// Request for http://localhost/ischool/auth.php?foo=bar
			// Make sure it works for other directly-accessed
			// PHP files as well (not using mod_rewrite).
			$_SERVER['REQUEST_URI'] = '/ischool/auth.php?foo=bar';
			$_SERVER['SCRIPT_NAME'] = '/ischool/auth.php';
			$_SERVER['SCRIPT_FILENAME'] = '/home/installuser/Dropbox/projects/ischool/website/web/auth.php';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestPhpFile('/ischool/', 'auth.php');

			// Request for http://localhost/ischool/school/settings?foo=bar
			$_SERVER['REQUEST_URI'] = '/ischool/school/settings?foo=bar';
			$_SERVER['SCRIPT_NAME'] = '/ischool/CodeIgniter/index.php';
			$_SERVER['SCRIPT_FILENAME'] = '/home/installuser/Dropbox/projects/ischool/website/web/CodeIgniter/index.php';
			$_SERVER['PATH_INFO'] = '/school/settings';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] .
				$_SERVER['PATH_INFO'];
			$this->assertAptivateRequestSchoolSettings('/ischool/');
		}
		catch (Exception $e)
		{
			$_SERVER = $old_server;
			throw $e;
		}
		
		$_SERVER = $old_server;
	}

	/**
	 * Lighttpd sets PATH_INFO but leaves it empty if there isn't any.
	 */
	public function testLighttpdFastcgiPhpEnvironment()
	{
		$old_server = $_SERVER;
		
		try
		{
			// Request for http://staging.ischool.zm/?foo=bar
			// with Alias /ischool /home/installuser/Dropbox/projects/ischool/website/web
		
			$_SERVER['REQUEST_URI'] = '/?foo=bar';
			$_SERVER['QUERY_STRING'] = 'foo=bar';
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_SERVER['SCRIPT_NAME'] = '/index.php';
			$_SERVER['SCRIPT_FILENAME'] = '/media/hdb1/ischool/ischool.zm/staging_html/index.php';
			$_SERVER['PATH_INFO'] = '';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestRoot('/');

			// Request for http://staging.ischool.zm/index.php?foo=bar
			//
			// For anything other than index.php, we must keep the
			// filename in the app_path, otherwise local links
			// on that page will go to a different php file.
			//
			// We might as well do that for index.php too, rather
			// than treating it as a special case on the assumption
			// that the web server is configured with
			// "DirectoryIndex index.php" (which it might not be).
			$_SERVER['REQUEST_URI'] = '/index.php?foo=bar';
			$this->assertAptivateRequestPhpFile('/', 'index.php');

			// Request for http://staging.ischool.zm/auth.php?foo=bar
			// Make sure it works for other directly-accessed
			// PHP files as well (not using mod_rewrite).
			$_SERVER['REQUEST_URI'] = '/auth.php?foo=bar';
			$_SERVER['SCRIPT_NAME'] = '/auth.php';
			$_SERVER['SCRIPT_FILENAME'] = '/media/hdb1/ischool/ischool.zm/staging_html/auth.php';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestPhpFile('/', 'auth.php');

			// Request for http://staging.ischool.zm/school/settings?foo=bar
			$_SERVER['REQUEST_URI'] = '/school/settings?foo=bar';
			$_SERVER['SCRIPT_NAME'] = '/CodeIgniter/index.php';
			$_SERVER['SCRIPT_FILENAME'] = '/media/hdb1/ischool/ischool.zm/staging_html/CodeIgniter/index.php';
			$_SERVER['PATH_INFO'] = '/school/settings';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] .
				$_SERVER['PATH_INFO'];
			$this->assertAptivateRequestSchoolSettings('/');
		}
		catch (Exception $e)
		{
			$_SERVER = $old_server;
			throw $e;
		}
		
		$_SERVER = $old_server;
	}
	
	public function testFakeRequestForTestsEnvironment()
	{
		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url');
		$this->assertEquals('/', $req->app_root);
		$this->assertEquals('fake-test-url', $req->app_path);
	}
}
?>
