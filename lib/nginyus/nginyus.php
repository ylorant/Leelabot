<?php

function NginyUS_load($dir)
{
	if(substr($dir, -1) != '/')
		$dir .= '/';
	include($dir.'events.class.php');
	include($dir.'framework.class.php');
	include($dir.'systemPages.class.php');
	include($dir.'siteManager.class.php');
	include($dir.'main.class.php');
}
