<?php

/**
 * \file core/instance.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief ServerInstance class file.
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
 * This file hosts the ServerInstance class, allowing the bot to be used on multiple servers at a time.
 */

/**
 * \brief ServerInstance class for leelabot.
 * 
 * This class allow Leelabot to be used on multiple servers at a time, while keeping a maximal flexibility for all usages (like different plugins on different servers).
 * It also hosts the parser for a server command, lightening the Leelabot class.
 */
class ServerInstance
{
	private $_addr; ///< Server address.
	private $_port; ///< Server port.
	private $_rcon; ///< Server RCon password.
	private $_name; ///< Server name, for easier recognition in commands or log.
	private $_logfile; ///< Log file info (address, logins for FTP/SSH, and other info)
	private $_plugins; ///< List of plugins used by the server, may differ from global list.
	private $_leelabot; ///< Reference to main Leelabot class.
	
	public $serverInfo; ///< Holds server info.
	public $_players; ///< Holds players data.
	
	/** Constructor for the class.
	 * This is the constructor, it sets the vars to their default values (for most of them, empty values).
	 * 
	 * \return TRUE.
	 */
	public function __construct(&$main)
	{
		$this->_leelabot = $main;
		$this->_plugins = array();
		return TRUE;
	}
	
	/** Sets the address/port for the current server.
	 * This sets the address and port for the current server. It checks if the IP address and the port of the server is correct before setting it.
	 * 
	 * \param $addr The IP address of the server.
	 * \param $port The port of the server.
	 * 
	 * \return TRUE if address set correctly, FALSE otherwise.
	 */
	public function setAddress($addr, $port)
	{
		//Checking port regularity
		if($port && ($port < 0 || $port > 65535))
		{
			Leelabot::message('IP Port for the server $0 is not correct', array($this->_name), E_WARNING);
			return FALSE;
		}
		
		if($addr)
			$this->_addr = $addr;
		if($port)
			$this->_port = $port;
		
		return TRUE;
	}
	
	/** Get the address/port for the current server.
	 * This returns the address and port for the current server in an array.
	 * 
	 * \return The current server's address in an array (IP in first element, port in second).
	 */
	public function getAddress()
	{
		return array($this->_addr, $this->_port);
	}
	
	/** Get the RCon password for the current server.
	 * This returns the RCon password for the current server.
	 * 
	 * \return The current server's RCon password.
	 */
	public function getRConPassword()
	{
		return $this->_rconpassword;
	}
	
	/** Sets the name of the current server.
	 * This sets the name of the server, for easier recognition by the final user. This name needs to be in lowecase, with only alphanumeric characters, and the
	 * underscore.
	 * 
	 * \param $name The new name of the server.
	 * 
	 * \return TRUE if address set correctly, FALSE otherwise.
	 */
	public function setName($name)
	{
		//Name normalization check
		if(!preg_match('#^[A-Za-z0-9_]+$#', $name))
		{
			//Yes, it is weird to put the name of the server if it is misformed, i know.
			Leelabot::message('Misformed server name for server : $0.', array($name), E_WARNING);
			return FALSE;
		}
		
		$this->_name = $name;
		return TRUE;
	}
	
	/** Sets the location of the server's log file to read from.
	 * For remote log files, 
	 * This sets the location of the log file to read from, after checking that this file exists (only for local files, remote files are checked in runtime).
	 * 
	 * \param $config The server's configuration.
	 * 
	 * \return TRUE if server config loaded correctly, FALSE otherwise.
	 */
	public function setLogFile($logfile)
	{
		$logfile = explode('://', $logfile, 2);
		//Check logfile access method.
		$type = 'file';
		switch($logfile[0])
		{
			case 'ftp':
			case 'ssh':
				/*if(!preg_match('#^(.+):(.+)@(.+)/(.+)$#isU', $logfile[1], $matches))
				{
					Leelabot::message('Misformed $0 log file location for server "$1".', array(strtoupper($logfile[0]), $this->name), E_WARNING);
					return FALSE;
				}
				
				$this->_logfile = array(
				'type' => $logfile[0],
				'location' => $matches[4],
				'server' => $matches[3],
				'login' => $matches[1],
				'password' => $matches[2]);
				break;*/
			//If no format specified, we guess that it is a local file
			case 'file':
				$type = $logfile[0];
				$logfile[0] = $logfile[1];
			default:
				if(!is_file($logfile[0]))
				{
					Leelabot::message('Log file $0 does not exists for server "$1".', array($logfile[0], $this->_name), E_WARNING);
					return FALSE;
				}
				
				$this->_logfile = array(
				'type' => $type,
				'location' => $logfile[0]);
		}
		
		return TRUE;
	}
	
	/** Loads a server configuration.
	 * This loads a configuration for the server, by calling other methods of the class.
	 * 
	 * \param $config The server's configuration.
	 * 
	 * \return TRUE if server config loaded correctly, FALSE otherwise.
	 */
	public function loadConfig($config)
	{
		$addr = $port = NULL;
		foreach($config as $name => $value)
		{
			switch($name)
			{
				case 'Address':
					$addr = $value;
					break;
				case 'Port':
					$port = $value;
					break;
				case 'RConPassword':
					$this->_rconpassword = $value;
					break;
				case 'Logfile':
					if(!$this->setLogFile($value))
						return FALSE;
					break;
				case 'UsePlugins':
					$this->_plugins = explode(',', str_replace(', ', ',', $value));
					break;
			}
		}
		
		if(empty($this->_plugins))
			$this->_plugins = $this->_leelabot->plugins->getLoadedPlugins();
		
		if($addr || $port)
		{
			if(!$this->setAddress($addr, $port))
				return FALSE;
		}
		
		return TRUE;
	}
	
	/** Connects the bot to the server.
	 * This function connects the bot to the server, i.e. opens the log, send resume commands to the server and simulate events for the plugins to synchronize.
	 * If the server cannot be synchronized, it is reloaded.
	 * 
	 * \return TRUE if server config connected correctly, FALSE otherwise.
	 */
	public function connect()
	{
		//First, we test connectivity to the server
		Leelabot::message('Connecting to server...');
		if(!RCon::test())
		{
			Leelabot::message('Can\'t connect : $0', array(RCon::getErrorString(RCon::lastError())), E_WARNING);
			return FALSE;
		}
		
		Leelabot::message('Gathering server info...');
		$this->serverInfo = RCon::serverInfo();
		
		Leelabot::message('Gathering server players...');
		$status = RCon::status();
		
		$this->_players = array();
		foreach($status['players'] as $id => $player)
		{
			Leelabot::message('Gathering info for player $0 (Slot $1)...', array($player['name'], $id));
			$playerData = array();
			$dump = RCon::dumpUser($id);
			
			//The characterfile argument is unique to bots, so we use it to know if a player is a bot or not
			if(isset($dump['characterfile']))
				$playerData['isBot'] = TRUE;
			else
			{
				$playerData['isBot'] = FALSE;
				$address = explode(':', $player['address']);
				$playerData['addr'] = $address[0];
				$playerData['port'] = $address[1];
				$playerData['guid'] = $dump['cl_guid'];
			}
			
			$playerData['name'] = preg_replace('#^[0-9]#', '', $dump['name']);
			
			$playerData['id'] = $id;
			$this->_players[$id] = new Storage($playerData);
			
			$data = array_merge($dump, $player);
			
			Leelabot::$instance->plugins->callServerEvent('ClientConnect', $id);
			Leelabot::$instance->plugins->callServerEvent('ClientUserinfo', $data);
		}
		
		//Getting team repartition for players
		if(count($this->_players) > 0)
		{
			Leelabot::message('Gathering teams...');
			//Red team
			$redteam = RCon::redTeamList();
			foreach($redteam as $id)
			{
				$this->_players[$id]->team = Server::TEAM_RED;
				Leelabot::$instance->plugins->callServerEvent('ClientUserInfoChanged', array('team' => 'red', 't' => Server::TEAM_RED));
			}
			
			//Blue team
			$blueteam = RCon::blueTeamList();
			foreach($blueteam as $id)
			{
				$this->_players[$id]->team = Server::TEAM_BLUE;
				Leelabot::$instance->plugins->callServerEvent('ClientUserInfoChanged', array('team' => 'red', 't' => Server::TEAM_BLUE));
			}
			
			//Spectators (all players without a team)
			foreach($this->_players as $id => $player)
			{
				if(empty($player->team))
					$this->_players[$id]->team = Server::TEAM_SPEC;
			}
		}
		
		//Finally, we open the game log file
		Leelabot::message('Opening game log file...');
		$this->openLogFile();
		
		//Showing current server status
		Leelabot::message('Current server status :');
		Leelabot::message('	Server name : $0', array($this->serverInfo['sv_hostname']));
		Leelabot::message('	Gametype : $0', array(Server::getGametype()));
		Leelabot::message('	Map : $0', array($this->serverInfo['mapname']));
		Leelabot::message('	Number of players : $0', array(count($this->_players)));
		Leelabot::message('	Server version : $0', array($this->serverInfo['version']));
		Leelabot::message('	Matchmode : $0', array(Leelabot::$instance->intl->translate($this->serverInfo['g_matchmode'] ? 'On' : 'Off')));
		
		return TRUE;
	}
	
	/** Exec a step of parsing.
	 * This function executes a step of parsing, i.e. reads the log, parses the read lines and calls the adapted events.
	 * 
	 * \return TRUE if all gone correctly, FALSE otherwise.
	 */
	public function step()
	{
		$data = fread($this->_logfile['fp'], 1024);
		if($data)
		{
			$data = explode("\n", trim($data, "\n"));
			foreach($data as $line)
			{
				echo '['.$this->_name.'] '.$line."\n";
			}
		}
	}
	
	/** Opens the log file.
	 * This function opens the log file.
	 * 
	 * \return TRUE if log loaded correctly, FALSE otherwise.
	 */
	public function openLogFile()
	{
		if(!($this->_logfile['fp'] = fopen($this->_logfile['location'], 'r')))
			return FALSE;
		
		fseek($this->_logfile['fp'], 0, SEEK_END);
		
		return TRUE;
	}
}


