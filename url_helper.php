<?php
class UrlHelper
{
	public static function url_params($url, $params)
	{
		$first_param = !strpos($url, '?');
		
		foreach ($params as $name => $value)
		{
			if ($first_param)
			{
				$url .= "?";
				$first_param = FALSE;
			}
			else
			{
				$url .= "&";
			}
			
			$url .= urlencode($name) . "=" . urlencode($value);
		}
		
		return $url;
	}
}
?>
