<?php
/**
 * \file plugins/stats.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \version 0.1
 * \brief Teamspeak plugin for Leelabot. It allows to look who is on teamspeak.
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
 * This file contains a connection to teamspeak for look client on this.
 * 
 * Commands in game :
 * !ts => get client list on teamspeak
 */

/**
 * \brief Plugin stats class.
 * This class contains the methods and properties needed by the teamspeak plugin. 
 */
class PluginTeamspeak extends Plugin
{
	private $_channelId = NULL; // Awards toggle.
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if(isset($this->config))
		{
			if(isset($this->config['ChooseChannel']))
				$this->_channelId = $this->config['ChooseChannel'];
		}
	}
}

$this->addPluginData(array(
'name' => 'teamspeak',
'className' => 'PluginTeamspeak',
'display' => 'Teamspeak Plugin',
'autoload' => TRUE));