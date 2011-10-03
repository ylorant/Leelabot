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
 * \brief MOAP API class for leelabot.
 * 
 * This class is the MOAP API, that will be called by the MOAPServer interface. It hosts all the functions which will be accessible by the client via MOAP.
 */
class LeelabotWebservice
{
	private $_site;
	private $_main;
	private $_MOAPServer;
	private $_id;
	
	public function __construct(&$site, &$main)
	{
		$this->_site = $site;
		$this->_main = $main;
		
		$site->addPage('/', 'LeelabotWebservice', 'Webservice');
		
		$this->loadWebservice();
	}
	
	public function Webservice($id, $data)
	{
		$this->_id = $id;
		if($data['query'] == 'POST')
			$this->_MOAPServer->handle();
		else
		{
			$hiddenMethods = array('Webservice', 'loadWebservice', '__construct');
			$this->_main->BufferSetReplyCode($id, 200);
			$this->_main->BufferAppendData($id,'<h1>List of available functions :</h1><ul>');
			foreach(get_class_methods($this) as $method)
			{
				if(!in_array($method, $hiddenMethods))
					$this->_main->BufferAppendData($id,'<li>'.$method.'()</li>');
			}
			$this->_main->BufferAppendData($id,'</ul><p>Please refer to the documentation for further details.</p>');
			$this->_main->sendBuffer($id);
		}
	}
	
	public function loadWebservice()
	{
		$this->_MOAPServer = new MOAPServer();
		$this->_MOAPServer->setClass($this);
	}
	
	public function player($id)
	{
		$this->_main->BufferAppendData($this->_id, serialize(Storage::toArray(Server::getPlayer($id))));
		$this->_main->sendBuffer($this->_id);
	}
}

$this->addClasses(array('LeelabotWebservice'));
