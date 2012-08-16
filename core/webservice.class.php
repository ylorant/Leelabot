<?php
/**
 * \file core/outerapi.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief OuterAPI class file.
 *
 * \section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * \section DESCRIPTION
 *
 * This file hosts the OuterAPI class, interfacing leelabot with the webserver NginyUS, and allowing the bot to be operated remotely.
 */
 
/**
 * \brief API class for leelabot.
 * 
 * This class is the Leelabot API, that will be called by the MLPFIMServer interface. It hosts all the functions which will be accessible by the client via MLPFIM.
 */
class LeelabotWebservice
{
	private $_site;
	private $_main;
	private $_MLPFIMServer;
	private $_id;
	private $_methods = array();
	private $_methodParameters = array();
	private $_authfile;
	private $_auth;
	
	public function __construct(&$site, &$main)
	{
		$this->_site = $site;
		$this->_main = $main;
		
		//We set the webservice class into the OuterAPI class, for adding properly methods
		Leelabot::$instance->outerAPI->setWSObject($this);
		
		$site->addPage('/', $this, 'Webservice');
		
		$this->loadWebservice();
		
		//Move that to a new class, and add core methods to it (list servers, plugins, manage them)
		$this->addMethod('getMethodList', array($this, 'WSMethodGetMethodList'));
	}
	
	public function Webservice($id, $data)
	{
		var_dump($data);
		
		$this->_id = $id;
		if(isset($data['Origin']))
			$this->_main->BufferAddHeader($id, 'Access-Control-Allow-Origin', '*');
		switch($data['query'])
		{
			case 'POST':
				//Before processing anything, we check if the user has been authenticated
				if($this->_auth)
				{
					//If the user has not been authenticated, we return a blank page (the request has been return by authenticate())
					if(!$this->_main->authenticate($id, $data, array($this, 'checkAuth')))
						return TRUE;
				}
				
				if(!$this->_MLPFIMServer->handle($data['rawPOST']))
				{
					$this->_main->BufferSetReplyCode($id, 500);
					$this->_main->sendBuffer($id);
				}
				break;
			case 'OPTIONS':
				$this->_main->BufferSetReplyCode($id, 200);
				$this->_main->BufferAddHeader($id, 'Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
				$this->_main->BufferAddHeader($id, 'Access-Control-Allow-Headers', 'Content-Type, Authorization');
				$this->_main->BufferAddHeader($id, 'Access-Control-Max-Age', '1728000');
				$this->_main->sendBuffer($id);
				break;
			default:
				$this->_main->BufferSetReplyCode($id, 200);
				$this->_main->BufferAppendData($id,'<html><head><title>Leelabot API function list</title></head><body>');
				$this->_main->BufferAppendData($id,'<h1>List of available functions :</h1><ul>');
			
				foreach(array_keys($this->_methods) as $method)
					$this->_main->BufferAppendData($id,'<li>'.$method.'('.join(', ', $this->_methodParameters[$method]).')</li>');
				
				$this->_main->BufferAppendData($id,'</ul><p>Please refer to the documentation for further details.</p>');
				$this->_main->BufferAppendData($id, '</body></html>');
				$this->_main->sendBuffer($id);
				break;
		}
	}
	
	public function WSMethodGetMethodList()
	{
		return array('success' => true, 'data' => array_keys($this->_methods));
	}
	
	public function loadWebservice()
	{
		$this->_MLPFIMServer = new MLPFIMServer();
		$this->_MLPFIMServer->setClass($this);
		$this->_MLPFIMServer->setReplyCallback(array($this, 'reply'));
	}
	
	public function checkAuth($user, $passwd)
	{
		$userFile = parse_ini_file($this->_authfile);
		
		if(!isset($userFile[$user]) || $userFile[$user] != $passwd)
			return FALSE;
		
		return TRUE;
	}
	
	public function setAuthentication($auth, $file)
	{
		$this->_auth = $auth;
		$this->_authfile = $file;
	}
	
	public function reply($data)
	{
		$this->_main->BufferAppendData($this->_id, $data);
		$this->_main->sendBuffer($this->_id);
	}
	
	public function addMethod($method, $callback)
	{
		if(isset($this->_methods[$method]))
			return FALSE;
		$this->_methods[$method] = $callback;
		
		$reflectionClass = new ReflectionClass($callback[0]);
		$reflectionMethod = $reflectionClass->getMethod($callback[1]);
		$reflectionParameters = $reflectionMethod->getParameters();
		$this->_methodParameters[$method] = array();
		
		foreach($reflectionParameters as $param)
			$this->_methodParameters[$method][$param->getPosition()] = $param->getName();
	}
	
	public function __call($method, $args)
	{
		if(isset($this->_methods[$method]))
		{
			if(count($args) == count($this->_methodParameters[$method]))
				return call_user_func_array($this->_methods[$method], $args);
			else
				return array('response' => false, 'error' => 'Parameter count does not match');
		}
		else
			return array('response' => false, 'error' => 'Method not found');
	}
}

$this->addClasses(array('LeelabotWebservice'));
