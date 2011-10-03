<?php

define('E_DEBUG', 32768);

//Fake leelabot class for aliasing message function
class Leelabot
{
	public static function message($message, $args = array(), $type = E_NOTICE, $force = FALSE, $translate = TRUE)
	{
		//Parsing message vars
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);
		
		//Getting type string
		switch($type)
		{
			case E_NOTICE:
			case E_USER_NOTICE:
				$prefix = 'Notice';
				break;
			case E_WARNING:
			case E_USER_WARNING:
				$prefix = 'Warning';
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$prefix = 'Error';
				break;
			case E_DEBUG:
				$prefix = 'Debug';
				break;
			default:
				$prefix = 'Unknown';
		}
		
		echo date("m/d/Y h:i:s A").' -- '.$prefix.' -- '.$message.PHP_EOL;
	}
}

//Basic replacement for Storage
class Storage
{
	public function __construct($array)
	{
		foreach($array as $name => $value)
			$this->$name = $value;
	}
}


chdir('../../');

include('lib/nginyus/nginyus.php');
include('lib/nusoap/nusoap.php');

NginyUS_load('lib/nginyus');

$server = new NginyUS();
$server->setAddress('0.0.0.0', 3000);

$manager = $server->manageSites();
$manager->newSite('admin');
$manager->loadConfig('admin', array(
	'SiteRoot' => '127.0.0.1/admin',
	'Alias' => 'bender.koinko.in/admin, bender.nyan.at/admin',
	'DocumentRoot' => 'web',
	'ProcessFiles' => 'local.php'));

$manager->newSite('webservice');
$manager->getSite('webservice')->SoapURI = 'http://127.0.0.1:3000/api';
$manager->loadConfig('webservice', array(
	'SiteRoot' => '127.0.0.1/api',
	'Alias' => 'bender.koinko.in/api, bender.nyan.at/api',
	'DocumentRoot' => 'tests/soap',
	'ProcessFiles' => 'webservice.php'));



pcntl_signal(SIGTERM, array($server, 'stop'));

$server->connect();
while(1)
{
	$server->process();
	usleep(1000);
}
