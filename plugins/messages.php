<?php
/**
 * \file plugins/messages.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \version 1.0
 * \brief Sends messages every X secondes in chat
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
 * This file contains a routine that send messages.
 * 
 * Commands in game :
 * !messages <on|off|reload> // reload : make possible to modify the file and reload withn't restart of the bot.
 *  
 */

/**
 * \brief Plugin messages class.
 * This class contains the methods and properties needed by the messages plugin. 
 */
class PluginMessages extends Plugin
{
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if(empty($this->config))
		{
			Leelabot::message("Messages config was not found.", array(), E_WARNING);
		}
	}
	
	public function SrvEventStartupGame($server)
	{
		if(is_array($this->config[$server]) && count($this->config[$server]))
		{
			 // If messages was active on this server
			if(isset($this->config[$server]['Active']))
				$this->config[$server]['Active'] = Leelabot::parseBool($this->config[$server]['Active']);
			else
				$this->config[$server]['Active'] = FALSE;
				
			// Time in secondes 
			if(!(isset($this->config[$server]['EverySeconds']) && is_numeric($this->config[$server]['EverySeconds']) && $this->config[$server]['EverySeconds'] >= 0))
				$this->config[$server]['EverySeconds'] = 60;
				
			 // File where are stored the messages to send.
			if(empty($this->config[$server]['MessagesFile']) || (!is_file($this->_main->getConfigLocation().'/'.$this->config[$server]['MessagesFile']) && !touch($this->_main->getConfigLocation().'/'.$this->config[$server]['MessagesFile'])))
			{
				Leelabot::message("Can't load messages files. Messages will not be send.", array(), E_WARNING);
				$this->config[$server]['MessagesFile'] = NULL;
				$this->config[$server]['Active'] = FALSE;
			}
			else
			{
				$this->config[$server]['MessagesFile'] = $this->_main->getConfigLocation().'/'.$this->config[$server]['MessagesFile'];
			}
			
			// If everythings is ok, we can load the file.
			$this->_loadFileMessages($server);
			$server = ServerList::getServer($server);
			$server->set('lastmsg', end($server->get('msgs')));
			$server->set('lasttime', 0);
		}
		else
		{
			Leelabot::message("Messages config was not found for \"".$server."\" server.", array(), E_WARNING);
		}
	}
	
	/** Destroy function. Unloads the plugin properly.
	 * This function cleans properly the plugin when it is unloaded.
	 * 
	 * \return Nothing.
	 */
	public function destroy()
	{
		foreach($serverlist as $server)
		{
			$server = ServerList::getServer($server);
			$server->set('msgs', NULL);
			$server->set('lastmsg', NULL);
			$server->set('lasttime', NULL);
		}
	}
	
	public function RoutineMessages()
	{
		// We browse all servers
		$servers = ServerList::getList();
		foreach($servers as $serv)
		{
			$server = ServerList::getServer($serv);
			$rcon = ServerList::getServerRCon($serv);
			$_messages = $server->get('msgs');
			$count = count($_messages);
			
			if($this->config[$serv]['Active'] AND $count)
			{
				$_lasttime = $server->get('lasttime');
				$_lastmsg = $server->get('lastmsg');
				$time = time();
				
				if($time >= ($_lasttime+$this->config[$serv]['EverySeconds']))
				{
					$last = end($_messages);
					$first = reset($_messages);
					$msg = $first;
					
					// If $_lastmsg is at the end of $_messages, we take the first value of $_messages
					if($last !== $_lastmsg)
					{
						$while = TRUE;
						while($while)
						{
							if($msg == $_lastmsg)
								$while = FALSE;
							
							$msg = next($_messages);
						}
					}
					
					$rcon->say($msg, array(), FALSE);
					
					$server->set('lastmsg', $msg);
					$server->set('lasttime', $time);
				}
			}
		}
	}
	
	private function _loadFileMessages($serv= NULL)
	{
		if($serv === NULL)
			$server = Server::getInstance();
		else
			$server = ServerList::getServer($serv);
		
		$content = file_get_contents($this->config[$serv]['MessagesFile']);
		$server->set('msgs', explode("\n", $content));
	}
	
	private function _writeFileMessage($serv = NULL)
	{
		if($serv === NULL)
			$server = Server::getInstance();
		else
			$server = ServerList::getServer($serv);
		
		$_messages = $server->get('msgs');
		$content = file_put_contents($this->config[$serv]['MessagesFile'], implode("\n", $_messages));
	}
	
	private function _addMessage($msg, $serv = NULL)
	{
		if($serv === NULL)
			$server = Server::getInstance();
		else
			$server = ServerList::getServer($serv);
		
		$_messages = $server->get('msgs');
		$_messages[] = $msg;
		$server->set('msgs', $_messages);
	}
	
	private function _removeMessage($id, $serv = NULL)
	{
		if($serv === NULL)
			$server = Server::getInstance();
		else
			$server = ServerList::getServer($serv);
		
		$_messages = $server->get('msgs');
		unset($_messages[$id]);
		$server->set('msgs', $_messages);
	}
}

$this->addPluginData(array(
'name' => 'messages',
'className' => 'PluginMessages',
'display' => 'Messages Plugin',
'autoload' => TRUE));