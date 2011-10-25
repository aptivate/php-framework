<?php
require_once('routing.php');
require_once('request.php');

class Aptivate_HTTP
{
	static $defaultView;
	static $dataAccess;
	
	public static function process()
	{
		error_log($_SERVER['REQUEST_URI']);
		error_log($_SERVER['PATH_INFO']);
		
		$request = new Aptivate_Request();
		$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : "/";
		$response = Aptivate_Routing::dispatch($path, $request,
			self::$dataAccess, self::$defaultView);
		
		foreach ($response->setCookies as $name => $value)
		{
			setcookie($name, $value);
		}
		
		if ($response->status_code == 302)
		{
			header("Location: ".$response->redirect_full_url);
		}
		
		echo $response->response_body;
	}
}
?>
		
