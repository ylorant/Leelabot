<?php
/**
 * \file plugins/messages.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \version 0.1
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
 * \brief Plugin teamspeak class.
 * This class contains the methods and properties needed by the teamspeak plugin. 
 */
class PluginTeamspeak extends Plugin
{
	private $_channelId = NULL;
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if($this->config)
		{
			$serverlist = ServerList::getList();
			
			// 1 config per server
			foreach($serverlist as $server)
			{
				if(isset($this->config[$server]['Active'])) // If messages was active on this server
					$this->config[$server]['Active'] = Leelabot::parseBool($this->config[$server]['AutoTeams']);
				else
					$this->config[$server]['Active'] = FALSE;
				
				if(!empty($this->config[$server]['MessagesFile'])) // File where are stored the messages to send.
					$this->config[$server]['MessagesFile'] = NULL;
			}
		}
	}
	
	/** Destroy function. Unloads the plugin properly.
	 * This function cleans properly the plugin when it is unloaded.
	 * 
	 * \return Nothing.
	 */
	public function destroy()
	{
		// Nothing at this time
	}
}

$this->addPluginData(array(
'name' => 'messages',
'className' => 'PluginMessages',
'display' => 'Messages Plugin',
'autoload' => TRUE));