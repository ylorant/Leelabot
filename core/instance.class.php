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
	private $_defaultLevel; ///< Default level for joining players
	
	public $serverInfo; ///< Holds server info.
	public $players; ///< Holds players data.
	public $scores; ///< Current score (for round-based games).
	public $pluginVars = array(); ///< Custom-set plugin vars.
	
	/** Constructor for the class.
	 * This is the constructor, it sets the vars to their default values (for most of them, empty values).
	 * 
	 * \return TRUE.
	 */
	public function __construct(&$main)
	{
		$this->_leelabot = $main;
		$this->_plugins = array();
		$this->_defaultLevel = 0;
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
	
	/** Get the active plugins for this server.
	 * This returns the plugins which have to be processed by the plugin manager.
	 * 
	 * \return The active plugins list, in an array.
	 */
	public function getPlugins()
	{
		return $this->_plugins;
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
	
	/** Gets the name of the current server.
	 * This function returns the name of the server.
	 * 
	 * \return The server's name.
	 */
	public function getName()
	{
		return $this->_name;
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
			case 'sftp':
				if(!preg_match('#^(.+):(.+)@(.+)/(.+)$#isU', $logfile[1], $matches))
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
				break;
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
				case 'DefaultLevel':
					$this->_defaultLevel = $value;
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
		
		//Sending startup Event for plugins
		$this->_leelabot->plugins->callServerEvent('StartupGame');
		
		Leelabot::message('Gathering server info...');
		$this->serverInfo = RCon::serverInfo();
		print_r($this->serverInfo);
		
		Leelabot::message('Gathering server players...');
		$status = RCon::status();
		
		$this->players = array();
		foreach($status['players'] as $id => $player)
		{
			Leelabot::message('Gathering info for player $0 (Slot $1)...', array($player['name'], $id));
			$playerData = array();
			if(!($dump = RCon::dumpUser($id)))
			{
				Leelabot::message('Cannot retrieve info for player $0.', array($player['name']), E_WARNING);
				continue;
			}
			
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
			$playerData['level'] = $this->_defaultLevel;
			
			$playerData['team'] = Server::TEAM_SPEC;
			$playerData['begin'] = FALSE;
			$this->players[$id] = new Storage($playerData);
			$this->players[$id]->other = $dump;
			
			$data = array_merge($dump, $player);
			
			$this->_leelabot->plugins->callServerEvent('ClientConnect', $id);
			$this->_leelabot->plugins->callServerEvent('ClientUserinfo', $data);
		}
		
		//Getting team repartition for players
		if(count($this->players) > 0)
		{
			Leelabot::message('Gathering teams...');
			//Red team
			if(!($redteam = RCon::redTeamList()))
				Leelabot::message('Cannot retrieve red team list');
			else
			{
				foreach($redteam as $id)
				{
					$this->players[$id]->team = Server::TEAM_RED;
					$this->players[$id]->begin = TRUE;
					$playerData = array('team' => 'red', 't' => Server::TEAM_RED, 'n' => $this->players[$id]->name);
					$this->_leelabot->plugins->callServerEvent('ClientUserInfoChanged', $playerData);
					$this->_leelabot->plugins->callServerEvent('ClientBegin', $id);
				}
			}
			
			//Blue team
			if(!($blueteam = RCon::blueTeamList()))
				Leelabot::message('Cannot retrieve blue team list');
			else
			{
				foreach($blueteam as $id)
				{
					$this->players[$id]->team = Server::TEAM_BLUE;
					$this->players[$id]->begin = TRUE;
					$this->_leelabot->plugins->callServerEvent('ClientUserInfoChanged', array('team' => 'red', 't' => Server::TEAM_BLUE));
					$this->_leelabot->plugins->callServerEvent('ClientBegin', $id);
				}
			}
			
			//Finally, we init scoreboard and virtually start a game (for the plugins).
			$this->scores = array(1 => 0, 2 => 0);
			$this->_leelabot->plugins->callServerEvent('InitGame', $this->serverInfo);
		}
		
		//Finally, we open the game log file
		if(!$this->openLogFile())
			return FALSE;
		
		//Showing current server status
		Leelabot::message('Current server status :');
		Leelabot::message('	Server name : $0', array($this->serverInfo['sv_hostname']));
		Leelabot::message('	Gametype : $0', array(Server::getGametype()));
		Leelabot::message('	Map : $0', array($this->serverInfo['mapname']));
		Leelabot::message('	Number of players : $0', array(count($this->players)));
		Leelabot::message('	Server version : $0', array($this->serverInfo['version']));
		Leelabot::message('	Matchmode : $0', array($this->_leelabot->intl->translate($this->serverInfo['g_matchmode'] ? 'On' : 'Off')));
		
		return TRUE;
	}
	
	/** Disconnects the server.
	 * This function disconnect properly the bot from the server, by sending fake events to the plugin to simulate client disconnections and server disconnection
	 * before closing the link.
	 * 
	 * \return Nothing.
	 */
	public function disconnect()
	{
		Leelabot::message('Disconnecting from server $0', array($this->_name));
		
		//Setting the server as current server
		RCon::setServer($this);
		
		Leelabot::message('Sending ClientDisconnect events to plugins', array(), E_DEBUG);
		foreach($this->clients as $id => $client)
		{
			$this->_leelabot->plugins->callServerEvent('ClientDisconnect', $id);
			unset($this->players[$id]);
		}
		
		//Calling the ShutdownGame event
		$this->_leelabot->plugins->callServerEvent('ShutdownGame');
		
		//Finally, we close the links to the server and we ask to the main class to delete the instance
		ftp_close($this->_logfile['ftp']);
		fclose($this->_logfile['fp']);
		$this->_leelabot->unloadServer($this->_name);
	}
	
	/** Executes a step of parsing.
	 * This function executes a step of parsing, i.e. reads the log, parses the read lines and calls the adapted events.
	 * 
	 * \return TRUE if all gone correctly, FALSE otherwise.
	 */
	public function step()
	{
		$data = $this->readLog();
		if($data)
		{
			$data = explode("\n", trim($data, "\n"));
			foreach($data as $line)
			{
				//Parsing line for event and arguments
				$line =substr($line, 7); //Remove the time (todo : see if elapsed time for the map is useful (more than when we count it ourselves))
				Leelabot::printText('['.$this->_name.'] '.$line);
				$line = explode(':',$line, 2);
				
				if(isset($line[1]))
					$line[1] = trim($line[1]);
				
				switch($line[0])
				{
					//A client connects
					case 'ClientConnect':
						$id = intval($line[1]);
						$this->players[$id] = new Storage(array('id' => $id, 'begin' => FALSE, 'level' => $this->_defaultLevel, 'time' => time()));
						Leelabot::message('Client connected : $0', array($id), E_DEBUG);
						$this->_leelabot->plugins->callServerEvent('ClientConnect', $id);
						break;
					//A client has disconnected
					case 'ClientDisconnect':
						$id = intval($line[1]);
						$this->_leelabot->plugins->callServerEvent('ClientDisconnect', $id);
						Leelabot::message('Client disconnected : $0', array($id), E_DEBUG);
						unset($this->players[$id]);
						break;
					//The client has re-sent his personal info (like credit name, weapons, weapmodes, card number)
					case 'ClientUserinfo':
						list($id, $infostr) = explode(' ', $line[1], 2);
						$userinfo = Server::parseInfo($infostr);
						
						Leelabot::message('Client send user info : $0', array($id), E_DEBUG);
						Leelabot::message('	Name : $0', array($userinfo['name']), E_DEBUG);
						Leelabot::message('	IP : $0', array($userinfo['ip']), E_DEBUG);
						
						$this->_leelabot->plugins->callServerEvent('ClientUserinfo', array($id, $userinfo));
						
						//Basically it's a copypasta of the code user above for initial data storage
						if(isset($userinfo['characterfile']))
						{
							$playerData['isBot'] = TRUE;
							Leelabot::message('	Is a bot.', array(), E_DEBUG);
						}
						else
						{
							$playerData['isBot'] = FALSE;
							$address = explode(':', $userinfo['ip']);
							$playerData['addr'] = $address[0];
							$playerData['port'] = $address[1];
							$playerData['guid'] = $userinfo['cl_guid'];
						}
						$playerData['name'] = preg_replace('#^[0-9]#', '', $userinfo['name']);
						$playerData['id'] = $id;
						
						$this->players[$id] = Storage::merge($this->players[$id], $playerData);
						if(!isset($this->players[$id]->other))
							$this->players[$id]->other = $userinfo;
						else
							$this->players[$id]->other = array_merge($this->players[$id]->other, $userinfo);
						break;
					//Change in client userinfo, useful for gathering teams
					case 'ClientUserinfoChanged':
						list($id, $infostr) = explode(' ', $line[1], 2);
						$userinfo = Server::parseInfo($infostr);
						Leelabot::message('Client has send changed user info : $0', array($id), E_DEBUG);
						Leelabot::message('	Name : $0', array($userinfo['n']), E_DEBUG);
						Leelabot::message('	Team : $0', array(Server::getTeamName($userinfo['t'])), E_DEBUG);
						
						$this->_leelabot->plugins->callServerEvent('ClientUserinfoChanged', array($id, $userinfo));
						$this->players[$id]->team = $userinfo['t'];
						$this->players[$id]->other['cg_rgb'] = $userinfo['a0'].' '.$userinfo['a1'].' '.$userinfo['a2'];
						$this->players[$id]->name = preg_replace('#^[0-9]#', '', $userinfo['n']);
						break;
					//Player start to play
					case 'ClientBegin':
						$id = intval($line[1]);
						Leelabot::message('Client has begun : $0', array($id), E_DEBUG);
						
						$this->_leelabot->plugins->callServerEvent('ClientBegin', $id);
						$this->players[$id]->begin = TRUE;
						break;
					//New round, map info is re-sended
					case 'InitRound':
						$serverinfo = Server::parseInfo($line[1]);
						Leelabot::message('New round started', array(), E_DEBUG);
						
						$this->_leelabot->plugins->callServerEvent('InitRound', $serverinfo);
					//New map, with new info
					case 'InitGame':
						if($line[0] == 'InitGame')
						{
							$serverinfo = Server::parseInfo($line[1]);
							Leelabot::message('New map started : $0', array($serverinfo['mapname']), E_DEBUG);
							
							$this->_leelabot->plugins->callServerEvent('InitGame', $serverinfo);
							$this->scores = array(1 => 0, 2 => 0);
						}
						
						if(!empty($this->serverInfo))
							$this->serverInfo = array_merge($this->serverInfo, $serverinfo);
						else
							$this->serverInfo = $serverinfo;
						break;
					//The game has ended. Usually, next to that line are written the scores, but we just don't care about them
					case 'Exit':
						Leelabot::message('Map ended', array(), E_DEBUG);
						$this->_leelabot->plugins->callServerEvent('Exit', $line[1]);
						break;
					//Survivor round has ended, with the winner
					case 'SurvivorWinner':
						Leelabot::message('Round ended, winner : $0', array($line[1]), E_DEBUG);
						$winner = Server::getTeamNumber($line[1]);
						$this->_leelabot->plugins->callServerEvent('SurvivorWinner', $winner);
						if($winner)
							$this->scores[$winner]++;
						break;
					//The server goes down (it occurs also with a map change)
					case 'ShutdownGame':
						Leelabot::message('The server is going down', array(), E_DEBUG);
						$this->_leelabot->plugins->callServerEvent('ShutdownGame');
						break;
					//One player kills another, probably the most common action that will occur ?
					case 'Kill':
						$kill = explode(':', $line[1]);
						$kill = explode(' ', $kill[0]);
						$this->_leelabot->plugins->callServerEvent('Kill', $kill);
						break;
					//One player hit another, OMG FLOOD OF GAME LOG ! 
					case 'Hit':
						$hit = explode(':', $line[1]);
						$hit = explode(' ', $hit[0]);
						$this->_leelabot->plugins->callServerEvent('Hit', $hit);
						break;
					//Player message
					case 'say':
						$message = explode(' ', $line[1], 2);
						$id = intval($message[0]);
						$contents = explode(':', $message[1], 2);
						$contents = substr($contents[1], 1);
						Leelabot::message('$0 sended a message : $1',array(Server::getPlayer($id)->name, $contents), E_DEBUG);
						$this->_leelabot->plugins->callServerEvent('Say',array($id, $contents));
						
						//We check if it's a command
						if($contents[0] == '!')
						{
							$contents = substr($contents, 1);
							$args = explode(' ', $contents);
							$command = array_shift($args);
							Leelabot::message('Command catched : !$0', array($command), E_DEBUG);
							$this->_leelabot->plugins->callCommand($command, $id, $args);
						}
						break;
				}
				
			}
		}
		
		//Before returning, we execute all routines
		$this->_leelabot->plugins->callAllRoutines();
		
		return TRUE;
	}
	
	/** Reads the log.
	 * This function reads all the remaining log since last read (paying no attention to line returns). It also downloads it from an FTP server if specified.
	 * 
	 * \return The read data if averything passed correctly, FALSE otherwise. If no data has been read, it returns an empty string.
	 */
	public function readLog()
	{
		switch($this->_logfile['type'])
		{
			case 'ftp':
				//If we have finished to download the log, we read it and restart downloading
				if($this->_logfile['state'] == FTP_FINISHED)
				{
					$data = '';
					//We read only if the pointer has changed, i.e. something has been downloaded.
					if($this->_logfile['pointer'] != ftell($this->_logfile['fp']))
					{
						fseek($this->_logfile['fp'], $this->_logfile['pointer']); //Reset file pointer to last read position (ftp_nb_fget moves the pointer)
						$data = '';
						$read = NULL;
						while($read === NULL || $read)
						{
							$read = fread($this->_logfile['fp'], 1024);
							$data .= $read;
						}
						$this->_logfile['pointer'] = ftell($this->_logfile['fp']); //Save new pointer position
					}
					
					//Calculation of new global pointer and sending command
					$totalpointer = $this->_logfile['pointer'] + $this->_logfile['origpointer'];
					$this->_logfile['state'] = ftp_nb_fget($this->_logfile['ftp'], $this->_logfile['fp'], $this->_logfile['location'], FTP_BINARY, $totalpointer);
					
					return $data;
				}
				elseif($this->_logfile['state'] == FTP_FAILED) //Precedent reading has failed
				{
					Leelabot::message('Can\'t read the remote FTP log anymore.', array(), E_WARNING);
					return FALSE;
				}
				else
					$this->_logfile['state'] = ftp_nb_continue($this->_logfile['ftp']);
				break;
			case 'file':
				$data = '';
				$read = NULL;
				while($read === NULL || $read)
				{
					$read = fread($this->_logfile['fp'], 1024);
					$data .= $read;
				}
				
				return $data;
				break;
		}
	}
	
	/** Opens the log file.
	 * This function opens the log file. If the log is on an FTP server, it will login, try to get its size, and open the local buffer file. If the size
	 * 
	 * \return TRUE if log loaded correctly, FALSE otherwise.
	 */
	public function openLogFile()
	{
		switch($this->_logfile['type'])
		{
			case 'file':
				Leelabot::message('Opening local game log file...');
				if(!($this->_logfile['fp'] = fopen($this->_logfile['location'], 'r')))
				{
					Leelabot::message('Can\'t open local log file : $0.', array($this->_logfile['location']), E_WARNING);
					return FALSE;
				}
		
				fseek($this->_logfile['fp'], 0, SEEK_END);
				break;
			case 'ftp':
				Leelabot::message('Connecting to FTP server...');
				//Connecting to FTP server
				if(strpos($this->_logfile['server'], ':'))
					list($address, $port) = explode(':', $this->_logfile['server']);
				else
					list($address, $port) = array($this->_logfile['server'], 21);
				if(!($this->_logfile['ftp'] = ftp_connect($address, $port)))
				{
					Leelabot::message('Can\'t connect to the log server.', array(), E_WARNING);
					return FALSE;
				}
				
				ftp_set_option($this->_logfile['ftp'], FTP_AUTOSEEK, false);
				
				//Login
				if(!ftp_login($this->_logfile['ftp'], $this->_logfile['login'], $this->_logfile['password']))
				{
					Leelabot::message('Can\'t log in to the server.', array(), E_WARNING);
					return FALSE;
				}
				
				//Creating the buffer and getting end of remote log (to avoid entire log download)
				Leelabot::message('Initializing online log read...');
				$this->_logfile['fp'] = fopen('php://memory', 'r+');
				$this->_logfile['state'] = FTP_FINISHED;
				if(!($this->_logfile['origpointer'] = ftp_size($this->_logfile['ftp'], $this->_logfile['location'])))
				{
					//If we can't get the size, we try to download the whole file into another temp file, and check its size
					Leelabot::message('Can\'t get actual log size. Trying to download whole file (might be slow)...');
					
					$tmpfile = fopen('php://memory', 'r+');
					if(!ftp_get($this->_logfile['ftp'], $tmpfile, $this->_logfile['location'], FTP_BINARY))
					{
						Leelabot::message('Can\'t download log file.', array(), E_WARNING);
						return FALSE;
					}
					
					$stat = stat($tmpfile);
					$this->_logfile['origpointer'] = $stat['size'];
					fclose($tmpfile);
				}
				$this->_logfile['pointer'] = 0;
				break;
		}
		
		return TRUE;
	}
	
	/** Gets a file from the server.
	 * This function gets the contents of a file from the server, with the protocole set to read the log (so it also works on remote servers).
	 * 
	 * \param $file File name.
	 * 
	 * \return A string containing the file's contents, or FALSE if an error happened.
	 */
	public function fileGetContents($file)
	{
		switch($this->_logfile['type'])
		{
			case 'file':
				return file_get_contents($file);
				break;
			case 'ftp':
				$buffer = fopen('php://memory', 'r+');
				if(!ftp_fget($this->_logfile['ftp'], $buffer, $file, FTP_BINARY))
					return FALSE;
				
				fseek($buffer, 0);
				$str = '';
				while(!feof($buffer))
					$str .= fgets($buffer);
				
				fclose($buffer);
				return $str;
				break;
		}
	}
	
	/** Writes to a file on the server.
	 * This function writes the given string to a file on the server, using the appropriate writing method (so it also works on remote servers).
	 * 
	 * \param $file The file to write to.
	 * \param $content The content to write.
	 * 
	 * \return TRUE if the file wrote correctly, or FALSE if anything unexpected happened.
	 */
	public function filePutContents($file, $content)
	{
		switch($this->_logfile['type'])
		{
			case 'file':
				return file_put_contents($file, $content);
				break;
			case 'ftp':
				$buffer = fopen('php://memory', 'r+');
				fputs($buffer, $content);
				fseek($buffer, 0);
				$ret = ftp_fput($this->_logfile['ftp'], $file, $buffer, FTP_BINARY);
				fclose($buffer);
				if(!$ret)
					return FALSE;
				else
					return TRUE;
				break;
		}
	}
}


