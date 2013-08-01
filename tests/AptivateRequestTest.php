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
	 * Test as configured on my laptop development environment,
	 * with Alias /ischool /home/installuser/Dropbox/projects/ischool/website/web.
	 * 
	 * Apache sets PHP_SELF = SCRIPT_NAME + PATH_INFO, e.g.
	 * /ischool/index.php/foo/bar (when PATH_INFO is '/foo/bar'
	 * for http://localhost/ischool/foo/bar.
	 */
	public function testApacheModPhpEnvironment()
	{
		$old_server = $_SERVER;
		
		try
		{
			// Request for http://localhost/ischool/
		
			$_SERVER['REQUEST_URI'] = '/ischool/';
			$_SERVER['QUERY_STRING'] = '';
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_SERVER['SCRIPT_NAME'] = '/ischool/index.php';
			$_SERVER['SCRIPT_FILENAME'] = '/home/installuser/Dropbox/projects/ischool/website/web/index.php';
			unset($_SERVER['PATH_INFO']);
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestRoot('/ischool/');

			// Request for http://localhost/ischool/?foo=bar
			// with Alias /ischool /home/installuser/Dropbox/projects/ischool/website/web
		
			$_SERVER['REQUEST_URI'] = '/ischool/?foo=bar';
			$_SERVER['QUERY_STRING'] = 'foo=bar';
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
			$_SERVER['REQUEST_URI'] = '/ischool/index.php';
			$_SERVER['QUERY_STRING'] = '';
			$this->assertAptivateRequestPhpFile('/ischool/', 'index.php');

			// Request for http://localhost/ischool/index.php?foo=bar
			$_SERVER['REQUEST_URI'] = '/ischool/index.php?foo=bar';
			$_SERVER['QUERY_STRING'] = 'foo=bar';
			$this->assertAptivateRequestPhpFile('/ischool/', 'index.php');

			// Request for http://localhost/ischool/auth.php?foo=bar
			// Make sure it works for other directly-accessed
			// PHP files as well (not using mod_rewrite).
			$_SERVER['REQUEST_URI'] = '/ischool/auth.php?foo=bar';
			$_SERVER['SCRIPT_NAME'] = '/ischool/auth.php';
			$_SERVER['SCRIPT_FILENAME'] = '/home/installuser/Dropbox/projects/ischool/website/web/auth.php';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestPhpFile('/ischool/', 'auth.php');

			// Request for http://localhost/ischool/school/settings
			$_SERVER['REQUEST_URI'] = '/ischool/school/settings';
			$_SERVER['QUERY_STRING'] = '';
			$_SERVER['SCRIPT_NAME'] = '/ischool/CodeIgniter/index.php';
			$_SERVER['SCRIPT_FILENAME'] = '/home/installuser/Dropbox/projects/ischool/website/web/CodeIgniter/index.php';
			$_SERVER['PATH_INFO'] = '/school/settings';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] .
				$_SERVER['PATH_INFO'];
			$this->assertAptivateRequestSchoolSettings('/ischool/');

			// Request for http://localhost/ischool/school/settings?foo=bar
			$_SERVER['REQUEST_URI'] = '/ischool/school/settings?foo=bar';
			$_SERVER['QUERY_STRING'] = 'foo=bar';
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
			// Request for http://staging.ischool.zm/
			$_SERVER['REQUEST_URI'] = '/';
			$_SERVER['QUERY_STRING'] = '';
			$this->assertAptivateRequestRoot('/');

			// Request for http://staging.ischool.zm/?foo=bar
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
			$_SERVER['REQUEST_URI'] = '/index.php';
			$_SERVER['QUERY_STRING'] = '';
			$this->assertAptivateRequestPhpFile('/', 'index.php');

			// Request for http://staging.ischool.zm/index.php?foo=bar
			$_SERVER['REQUEST_URI'] = '/index.php?foo=bar';
			$_SERVER['QUERY_STRING'] = 'foo=bar';
			$this->assertAptivateRequestPhpFile('/', 'index.php');

			// Request for http://staging.ischool.zm/auth.php?foo=bar
			// Make sure it works for other directly-accessed
			// PHP files as well (not using mod_rewrite).
			$_SERVER['REQUEST_URI'] = '/auth.php?foo=bar';
			$_SERVER['SCRIPT_NAME'] = '/auth.php';
			$_SERVER['SCRIPT_FILENAME'] = '/media/hdb1/ischool/ischool.zm/staging_html/auth.php';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
			$this->assertAptivateRequestPhpFile('/', 'auth.php');

			// Request for http://staging.ischool.zm/school/settings
			$_SERVER['REQUEST_URI'] = '/school/settings';
			$_SERVER['QUERY_STRING'] = '';
			$_SERVER['SCRIPT_NAME'] = '/CodeIgniter/index.php';
			$_SERVER['SCRIPT_FILENAME'] = '/media/hdb1/ischool/ischool.zm/staging_html/CodeIgniter/index.php';
			$_SERVER['PATH_INFO'] = '/school/settings';
			$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] .
				$_SERVER['PATH_INFO'];
			$this->assertAptivateRequestSchoolSettings('/');

			// Request for http://staging.ischool.zm/school/settings?foo=bar
			$_SERVER['REQUEST_URI'] = '/school/settings?foo=bar';
			$_SERVER['QUERY_STRING'] = 'foo=bar';
			$this->assertAptivateRequestSchoolSettings('/');
			
			// Having switched to using CodeIgniter for most things,
			// we now alias / to /media/hdb1/ischool/ischool.zm/staging_html/CodeIgniter.
			// Thus, when index.php is run, we see
			$_SERVER['SCRIPT_NAME'] = '/index.php';
			// But this index.php still knows its name only as
			// /CodeIgniter/index.php, which is not a subset of the
			// latter. This must not fail, and must treat the
			// app root as /, not /CodeIgniter.
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

	public function test_attached_files_list_with_no_file_uploaded()
	{
		$_FILES = array(
			'myfile' => array(
				'error' => array(4),
				'size' => array(0),
				'name' => array(''),
				'type' => array(''),
				'tmp_name' => array(''),
			));
		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url');
		$files = $req->attached_files();
		// print("attached_files = ".print_r($files, TRUE)."\n");
		$this->assertEquals(array(), $files);
	}	

	public function test_attached_files_list_with_some_files_missing()
	{
		$_FILES = array(
			'myfile' => array(
				'error' => array(0, 4, 0),
				'size' => array(1, 2, 3),
				'name' => array('foo', 'bar', 'baz'),
				'type' => array('music', 'documentary', 'book'),
				'tmp_name' => array('/tmp/foo.4', '/tmp/bar.5',
					'/tmp/baz.6'),
			));
		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url');
		$files = $req->attached_files();
		// print("attached_files = ".print_r($files, TRUE)."\n");
		$this->assertEquals(
			array(
				'myfile' => array(
					array(
						'name' => 'foo',
						'type' => 'music',
						'tmp_name' => '/tmp/foo.4',
						'size' => 1,
						'error' => 0
					),
					# "bar" is skipped because error = 4, "no file attached"
					array(
						'name' => 'baz',
						'type' => 'book',
						'tmp_name' => '/tmp/baz.6',
						'size' => 3,
						'error' => 0
					),
				),
			), $files);
	}
	
	public function test_build_new_uris_based_on_requested_uri()
	{
		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url');
		$this->assertEquals('/', $req->app_root);
		$this->assertEquals('fake-test-url', $req->app_path);
		
		$this->assertEquals('/fake-test-url',
			$req->requested_uri(TRUE /* $include_app_root */,
				TRUE /* $include_params */));
		$this->assertEquals('/fake-test-url',
			$req->requested_uri(TRUE /* $include_app_root */,
				FALSE /* $include_params */));
				
		$this->assertEquals('fake-test-url',
			$req->requested_uri(FALSE /* $include_app_root */,
				TRUE /* $include_params */));
		$this->assertEquals('fake-test-url',
			$req->requested_uri(FALSE /* $include_app_root */,
				FALSE /* $include_params */));

		$this->assertEquals('fake-test-url',
			$req->requested_uri(FALSE /* $include_app_root */,
				FALSE /* $include_params */,
				array('foo' => 'bar') /* $extra_params */));
		$this->assertEquals('fake-test-url?foo=bar',
			$req->requested_uri(FALSE /* $include_app_root */,
				TRUE /* $include_params */,
				array('foo' => 'bar') /* $extra_params */));
		$this->assertEquals('fake-test-url?foo=bar&baz=whee',
			$req->requested_uri(FALSE /* $include_app_root */,
				TRUE /* $include_params */,
				array('foo' => 'bar', 'baz' => 'whee')
				/* $extra_params */));

		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url', array('foo' => 'food'));
		$this->assertEquals('fake-test-url?foo=food',
			$req->requested_uri(FALSE /* $include_app_root */,
				TRUE /* $include_params */));
		$this->assertEquals('fake-test-url?foo=food&baz=whee',
			$req->requested_uri(FALSE /* $include_app_root */,
				TRUE /* $include_params */,
				array('baz' => 'whee')
				/* $extra_params */));
		$this->assertEquals('fake-test-url?foo=bar&baz=whee',
			$req->requested_uri(FALSE /* $include_app_root */,
				TRUE /* $include_params */,
				array('foo' => 'bar', 'baz' => 'whee')
				/* $extra_params */));
		$this->assertEquals('fake-test-url?baz=whee',
			$req->requested_uri(FALSE /* $include_app_root */,
				TRUE /* $include_params */,
				array('foo' => NULL, 'baz' => 'whee')
				/* $extra_params */),
			"We should be able to delete parameters by setting ".
			"them to NULL");
	}

	/**
	 * Aptivate_Request should extract sub-arrays from the request properly
	 */
	public function test_aptivate_request_extracts_parameter_subsets()
	{
		$_GET = array('hello' => array('bonk' => 'whee!'));
		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url');
		$this->assertEquals('whee!', $req['hello']->param('bonk'));
		$this->assertEquals('whee!', $req['hello']->param('bonk'));
		
		$_POST = $_GET;
		$_GET = array();
		$req = new Aptivate_Request('GET', '/fake-test-script.php',
			'/fake-test-url');
		$this->assertEquals('whee!', $req['hello']->param('bonk'));
		$this->assertEquals('whee!', $req['hello']->param('bonk'));
	}

	/**
	 * Aptivate_Request should give a helpful error message when asked
	 * to extract a subarray of parameters (using []) and the subset has
	 * a single value instead of being an array. This is a developer error
	 * (using the wrong name for a form field?).
	 */
	public function test_aptivate_request_handles_invalid_param_subset_properly()
	{
		$_GET = array('hello' => 'whee!'); // not an array
		$caught_exception = NULL;
		
		try
		{
			$req = new Aptivate_Request('GET', '/fake-test-script.php',
				'/fake-test-url');
			$req['hello'];
		}
		catch (Exception $e)
		{
			$caught_exception = $e;
		}
		
		$this->assertEquals("The GET parameter 'hello' should be a ".
			"sub-array, but was: 'whee!'", $caught_exception->getMessage());
		
		$_GET = array();
		$_POST = array('hello' => 'whee!'); // not an array
		$caught_exception = NULL;
		
		try
		{
			$req = new Aptivate_Request('POST', '/fake-test-script.php',
				'/fake-test-url');
			$req['hello'];
		}
		catch (Exception $e)
		{
			$caught_exception = $e;
		}
		
		$this->assertEquals("The POST parameter 'hello' should be a ".
			"sub-array, but was: 'whee!'", $caught_exception->getMessage());
	}
}
?>
