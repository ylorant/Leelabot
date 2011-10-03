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
 * \brief OuterAPI class for leelabot.
 * 
 * This class is the OuterAPI, the class that will make the link between Leelabot, the webserver and SOAP. It will load the webserver, add the internal webadmin if needed,
 * add the SOAP Server, and start all this.
 */
class OuterAPI
{
	private $_server; ///< NginyUS server class
	private $_manager; ///< Site manager class
	private $_wsdl; ///< URL/Path to the WSDL file
	
	/** Loads the webserver and configures it.
	 * This function loads the webserver and configures it from the data given in argument.
	 * 
	 * \param $config Configuration data
	 * 
	 * \return TRUE if server loaded successfully, FALSE otherwise.
	 */
	public function load($config)
	{
		Leelabot::message('Loading OuterAPI...');
		include('lib/nginyus/nginyus.php');
		NginyUS_load('lib/nginyus/');
		
		$this->_server = new NginyUS();
		
		//Checking validity of the IP/Port couple
		if(!isset($config['BindAddress']) || !isset($config['BindPort']) || !in_array($config['BindPort'], range(0, 65535)) || !filter_var($config['BindAddress'], FILTER_VALIDATE_IP))
		{
			Leelabot::message('Bind address/port not found or incorrect', array(), E_WARNING);
			return FALSE;
		}
		$this->_server->setAddress($config['BindAddress'], $config['BindPort']);
		
		//Getting the SiteManager
		$this->_manager = $this->_server->manageSites();
		
		if(isset($config['Webservice']) && (!isset($config['Webservice']['Enable']) || Leelabot::parseBool($config['Webservice']['Enable'])))
		{
			Leelabot::message('Loading Webservice...');
			$WSConfig = $config['Webservice'];
			
			//Including the MOAP Webservice class
			include('lib/moap/moapserver.php');
			
			//If there is aliases, we update them to add the API path to them
			if(!empty($WSConfig['Aliases']))
			{
				$newAliases = array();
				foreach(explode(',', $WSConfig['Aliases']) as $alias)
					$newAliases[] = trim($alias).'/api';
				
				$WSConfig['Aliases'] = join(', ', $newAliases);
			}
			else
				$WSConfig['Aliases'] = '';
			
			//Creating site for that
			$this->_manager->newSite('webservice');
			$site = $this->_manager->getSite('webservice');
			$this->_manager->loadConfig('webservice', array(
				'SiteRoot' => $config['BindAddress'].'/api',
				'Alias' => $WSConfig['Aliases'],
				'DocumentRoot' => $config['WebRoot'],
				'ProcessFiles' => 'webservice.php'));
			
			if(!isset($soapConfig['password']))
				Leelabot::message('Using Webservice without a password is not secure !', array(), E_WARNING);
		}
		
		$this->_server->connect();
	}
	
	/** Processes the Webserver.
	 * This method executes an iteration of the webserver main loop, allowing it to handle incoming queries.
	 */
	public function process()
	{
		$this->_server->process();
	}
}
