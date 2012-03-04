<?php
/**
 * 
 * \file plugins/logs.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Logs plugin for Leelabot. Allows the bot to log all activity on the server separately.
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
 * This file contains the dlog plugin, which will allow the bot to log actions in separate logs : connections, commands, chat...
 */
class PluginLogs extends Plugin
{
	private $_logFiles; ///< Log files list
	private $_mode; ///< Log open mode
	
	/** Plugin initialization.
	 * This function assures the plugin initialization when loaded. It opens the log files, and sets the behavior for the behavior of the plugin.
	 * If the plugin can't open the logs, its loading fails and an error is thrown.
	 */
	public function init()
	{
		$this->_logFiles = array();
		$logs = array();
		
		if(!isset($this->config['LogRoot']) || (!is_dir($this->config['LogRoot']) && !mkdir($this->config['LogRoot'])))
		{
			Leelabot::message('Can\'t store logs in user-defined directory, falling back to ./logs');
			$this->config['LogRoot'] = 'logs/';
			
			if(!is_dir('logs') && !mkdir('logs'))
			{
				Leelabot::message('Can\'t store logs in ./logs, unable to load plugin');
				return FALSE;
			}
		}
		else
			$this->config['LogRoot'] .= substr($this->config['LogRoot'], -1) == '/' ? '' : '/';
		
		if(isset($this->conig['EraseLog']) && Leelabot::parseBool($this->config['EraseLog']))
			$this->_mode = 'a+';
		else
			$this->_mode = 'w+';
	}
	
	/** The bot connects to a server, initialize the log.
	 * This function is triggered when the bot is being connected to a server. The corresponding log will be open if necessary.
	 * 
	 * \param $server The server which is being connected.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventStartupGame($server)
	{
		
			
		$logs = array();
		
		if(isset($this->config['CommandLog']))
		{
			if((!is_array($this->config['CommandLog']) && Leelabot::parseBool($this->config['CommandLog']))
				|| (isset($this->config['CommandLog'][$server]) && Leelabot::parseBool($this->config['CommandLog'][$server])))
				$logs['commands'] = fopen($this->config['LogRoot'].$server.'/commands.log', $this->_mode);
		}
		
		if(isset($this->config['ConnectionLog']))
		{
			if((!is_array($this->config['ConnectionLog']) && Leelabot::parseBool($this->config['ConnectionLog']))
				|| (isset($this->config['ConnectionLog'][$server]) && Leelabot::parseBool($this->config['ConnectionLog'][$server])))
				$logs['connection'] = fopen($this->config['LogRoot'].$server.'/connections.log', $this->_mode);
		}
		
		if(isset($this->config['ChatLog']))
		{
			if((!is_array($this->config['ChatLog']) && Leelabot::parseBool($this->config['ChatLog']))
				|| (isset($this->config['ChatLog'][$server]) && Leelabot::parseBool($this->config['ChatLog'][$server])))
				$logs['chat'] = fopen($this->config['LogRoot'].$server.'/chat.log', $this->_mode);
		}
		
		if(!empty($logs))
		{
			if(!is_dir($this->config['LogRoot'].$server))
			mkdir($this->config['LogRoot'].$server);
			
			$this->_logFiles[$server] = $logs;
		}
	}
	
	/** Plugin shutdown.
	 * This function is triggered when the plugin shuts down, it will ensure that all the logs are properly closed.
	 * 
	 * \return Nothing.
	 */
	public function destroy()
	{
		foreach($this->_logFiles as &$fp)
			fclose($fp);
	}
	
	/** Client authentication : notify it in the connection log.
	 * This function is triggered by the RightsAuthenticate event. It will logs the client authname and his level in the connection log.
	 * 
	 * \param $id The client ID.
	 * \param $authname The client authname
	 * 
	 * \return Nothing.
	 */
	public function RightsAuthenticate($id, $authname)
	{
		$player = Server::getPlayer($id);
		var_dump($player);
		$this->log('connection', 'Client '.$player->name.' <'.$player->uuid.'> authed:');
		$this->log('connection', "\t".'Auth name: '.$player->auth);
		$this->log('connection', "\t".'Level: '.$player->level);
		
	}
	
	/** Client connection : notify it in the connection log.
	 * This function is triggered by the ClientConnect event. It will log the client ID and his UUID in the connection log.
	 * 
	 * \param $id The client ID.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventClientConnect($id)
	{
		$player = Server::getPlayer($id);
		$this->log('connection', 'Client connected:');
		$this->log('connection', "\t".'ID: '.$id);
		$this->log('connection', "\t".'UUID: <'.$player->uuid.'>');
	}
	
	/** Client userinfo : notify it in the connection log.
	 * This function is triggered by the ClientUserinfo event. It will check that the client is not connected, and log his data in the
	 * connection log if necessary.
	 * 
	 * \param $id The player's ID.
	 * \param $userinfo The player's user info.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventClientUserinfo($id, $userinfo)
	{
		$player = Server::getPlayer($id);
		if(!$player->begin)
		{
			$address = explode(':', $userinfo['ip']);
			$this->log('connection', 'User data for <'.$player->uuid.'> :');
			$this->log('connection', "\t".'IP: '.$address[0]);
			$this->log('connection', "\t".'GUID: '.$userinfo['cl_guid']);
			$this->log('connection', "\t".'Name: '.$userinfo['name']);
			if(isset($userinfo['characterfile']))
				$this->log('connection', "\t".'Is a bot.');
		}
	}
	
	/** Banned client kick : notify it in the connection log.
	 * This function is triggered by the BanConnect event. It will notify in the log that the connecting client is banned and therefore 
	 * will be kicked.
	 * 
	 * \param $id The client ID.
	 * \param $banID The ban ID.
	 * 
	 * \return Nothing.
	 */
	public function BanConnect($id, $banID)
	{
		$player = Server::getPlayer($id);
		$this->log('connection', 'Player <'.$player->uuid.'> is banned with banID ['.$banID.']');
	}
	
	/** Client authentification : notify it in the connection log.
	 * This function is triggered by the RightsAuthenticate event. It will notify the connection log of the authname for the client, along with
	 * it right level.
	 * \param $id The client ID.
	 * \param $authname The client's authname.
	 * 
	 * \return Nothing.
	 */
	
	/** Client user info change : notify it in the connection log.
	 * This function is triggered by the ClientUserinfoChanged event. It will log his data in the connection log if necessary.
	 * 
	 * \param $id The player's ID.
	 * \param $userinfo The player's user info.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventClientUserinfoChanged($id, $userinfo)
	{
		$player = Server::getPlayer($id);
		$this->log('connection', 'Player '.$player->name.' <'.$player->uuid.'> changed :');
		$this->log('connection', "\tName: ".$userinfo['n']);
		$this->log('connection', "\tTeam: ".Server::getTeamName($userinfo['t']));
	}
	
	/** Client disconnection : notify it in the connection log.
	 * This function is triggered by the ClientDisconnect event. It will log his datat in the connection log.
	 * 
	 * \param $id The player's ID.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventClientDisconnect($id)
	{
		$player = Server::getPlayer($id);
		$this->log('connection', 'Player '.$player->name.' <'.$player->uuid.'> disconnected.');
	}
	
	/** Client message : notify it in the necessary logs.
	 * This function is triggered by the say event. It will log the message in the chat log, and if it's a command, it will log it in the
	 * commands log.
	 * 
	 * \param $id The ID of the client who sent a message.
	 * \param $message The message itself.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventSay($id, $message)
	{
		Leelabot::message('Logging say message');
		print_r($this->_logFiles);
		$player = Server::getPlayer($id);
		$this->log('chat', $player->name.' <'.$player->uuid.'>: '.$message);
		
		$cmd = explode(' ', $message);
		
		//Checking if the message is a command
		if(strlen($cmd[0]) && $cmd[0][0] == '!')
		{
			if($this->_plugins->eventExists('command', substr($cmd[0], 1)))
				$cmdlevel = $this->_plugins->getEventLevel('command', substr($cmd[0], 1));
			else
				$cmdlevel = '?';
			$this->log('commands', $player->name.' <'.$player->uuid.'>: '.$message);
			if(!empty($player->auth))
				$this->log('commands', "\t".'Authname: '.$player->auth);
			$this->log('commands', "\t".'Level check: '.$player->level.'/'.$cmdlevel);
			if($cmdlevel === '?')
				$this->log('commands', "\t".'Command not found.');
			elseif($cmdlevel > $player->level)
				$this->log('commands', "\t".'Access denied.');
			else
				$this->log('commands', "\t".'Access granted.');
		}
	}
	
	/** Logs the data into specific logs.
	 * This function logs the specified data into the right logfile, adding the date and a line return.
	 * 
	 * \param $log The log to write to.
	 * \param $text The text to write in the log.
	 * \param $server The server on which the data will be logged. Defaults to the current server.
	 * 
	 * \return TRUE if the text has been correctly logged, FALSE otherwise.
	 */
	public function log($log, $text, $server = NULL)
	{
		if($server === NULL)
			$server = Server::getName();
		
		if(!isset($this->_logFiles[$server]))
			return FALSE;
		
		if(!isset($this->_logFiles[$server][$log]))
			return FALSE;
		
		$ret = fputs($this->_logFiles[$server][$log], date(Locales::getDateTimeFormat()).' - '.$text."\n");
		
		return $ret !== FALSE;
	}
}

$this->addPluginData(array(
'name' => 'logs',
'className' => 'PluginLogs',
'display' => 'Logging plugin',
'dependencies' => array(),
'autoload' => TRUE));
