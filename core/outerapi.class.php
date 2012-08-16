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
	private $_WSEnabled = FALSE; ///< Indicator for webservice activation
	private $_WSObject; ///< Object queried by the webserver for the Webservice.
	private $_WSAuth = FALSE; ///< Indicator of authentication security for Webservice
	private $_WSAuthFile = ''; ///< Webservice authorized users file
	private $_WAEnabled = FALSE; ///< Indicator for Webadmin activation
	private $_WAObject; ///< Object queried by the webserver for the Webadmin.
	private $_WAAuth = FALSE; ///< Indicator of authentication security for Webadmin
	private $_WAAuthFile = ''; ///< Webadmin authorized users file
	
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
		
		//Loading the webservice
		if(isset($config['Webservice']) && (!isset($config['Webservice']['Enable']) || Leelabot::parseBool($config['Webservice']['Enable'])))
		{
			Leelabot::message('Loading Webservice...');
			$this->_WSEnabled = TRUE;
			$WSConfig = $config['Webservice'];
			
			//Including the MLPFIM Webservice class
			include('lib/mlpfim/mlpfimserver.php');
			
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
				'DocumentRoot' => 'core',
				'ProcessFiles' => 'webservice.class.php'));
			
			if(isset($WSConfig['Authentication']) && Leelabot::parseBool($WSConfig['Authentication']) == TRUE && isset($WSConfig['AuthFile']) && is_file(Leelabot::$instance->getConfigLocation().'/'.$WSConfig['AuthFile']))
			{
				$this->_WSAuth = TRUE;
				$WSConfig['AuthFile'] = Leelabot::$instance->getConfigLocation().'/'.$WSConfig['AuthFile'];
				$this->_WSAuthFile = '../'.$WSConfig['AuthFile'];
			}
			else
				Leelabot::message('Using Webservice without authentication is not secure !', array(), E_WARNING, TRUE);
		}
		
		//Loading the webadmin
		if(isset($config['Webadmin']) && (!isset($config['Webadmin']['Enable']) || Leelabot::parseBool($config['Webadmin']['Enable'])))
		{
			Leelabot::message('Loading Webadmin...');
			$this->_WAEnabled = TRUE;
			$WAConfig = $config['Webadmin'];
			
			//If there is aliases, we update them to add the admin path to them
			if(!empty($WAConfig['Aliases']))
			{
				$newAliases = array();
				foreach(explode(',', $WAConfig['Aliases']) as $alias)
					$newAliases[] = trim($alias).'/admin';
				
				$WAConfig['Aliases'] = join(', ', $newAliases);
			}
			else
				$WAConfig['Aliases'] = '';
			
			//Creating the site in the webserver
			$this->_manager->newSite('webadmin');
			$site = $this->_manager->getSite('webadmin');
			$this->_manager->loadConfig('webadmin', array(
				'SiteRoot' => $config['BindAddress'].'/admin',
				'Alias' => $WAConfig['Aliases'],
				'DocumentRoot' => '.',
				'ProcessFiles' => 'web/controllers/dispatcher.class.php'));
			
			
			if(isset($WAConfig['Authentication']) && Leelabot::parseBool($WAConfig['Authentication']) == TRUE && isset($WAConfig['AuthFile']) && is_file(Leelabot::$instance->getConfigLocation().'/'.$WAConfig['AuthFile']))
			{
				$this->_WAAuth = TRUE;
				$WAConfig['AuthFile'] = Leelabot::$instance->getConfigLocation().'/'.$WAConfig['AuthFile'];
				$this->_WAAuthFile = $WAConfig['AuthFile'];
			}
			else
				Leelabot::message('Using Webadmin without authentication is not secure !', array(), E_WARNING, TRUE);
		}
		
		$this->_server->connect();
		
		//Setting InnerAPI classes
		Webadmin::setWAObject($this->_manager->getSite('webadmin')->classes['LeelabotAdmin']);
		
		$this->_manager->getSite('webadmin')->classes['LeelabotAdmin']->setAuthentication($this->_WAAuth, $this->_WAAuthFile);
		$this->_manager->getSite('webservice')->classes['LeelabotWebservice']->setAuthentication($this->_WSAuth, $this->_WSAuthFile);
	}
	
	/** Returns if the webservice is active.
	 * This function returns the current state of the webservice, active or not.
	 * 
	 * \return The current state of the webservice, as a boolean.
	 */
	public function getWSState()
	{
		return $this->_WSEnabled;
	}
	
	/** Sets the Webservice Object.
	 * This function sets the object that will handle all the webservices calls on the class.
	 * 
	 * \param $object The Webservice object.
	 * 
	 * \return Nothing.
	 */
	public function setWSObject(&$object)
	{
		$this->_WSObject = $object;
	}
	
	/** Returns the object of the webservice.
	 * This function returns the object of the webservice, useful for adding and deleting methods to the webservice.
	 * 
	 * \return The webservice object.
	 */
	public function getWSObject()
	{
		return $this->_WSObject;
	}
	
	/** Returns if the webadmin is active.
	 * This function returns the current state of the webadmin, active or not.
	 * 
	 * \return The current state of the webservice, as a boolean.
	 */
	public function getWAState()
	{
		return $this->_WAEnabled;
	}
	
	/** Sets the Webadmin Object.
	 * This function sets the object that will handle all the webadmin calls on the class.
	 * 
	 * \param $object The Webadmin object
	 * 
	 * \return Nothing.
	 */
	public function setWAObject(&$object)
	{
		$this->_WAObject = $object;
	}
	
	/** Returns the object of the webadmin.
	 * This function returns the object of the webadmin, useful for adding and deleting pages to the webadmin.
	 * 
	 * \return The webadmin object.
	 */
	public function getWAObject()
	{
		return $this->_WAObject;
	}
	
	/** Processes the Webserver.
	 * This method executes an iteration of the webserver main loop, allowing it to handle incoming queries.
	 */
	public function process()
	{
		$this->_server->process();
	}
}
