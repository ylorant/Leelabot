<?php
/**
 * \file plugins/stats.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \version 0.1
 * \brief Statistics plugin for Leelabot. It allows to have game stats for each player.
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
 * This file contains a complete statistics system for players in the game.
 * 
 * Commands in game :
 * !stats => get stats to player
 * !statsreset => resets stats(for admins)
 * !awards => get current awards 
 */

/**
 * \brief Plugin stats class.
 * This class contains the methods and properties needed by the stats plugin. It contains all the statistics system to players on the server.
 */
class PluginStats extends Plugin
{
	private $_awards = TRUE; // Awards toggle.
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if(isset($this->_main->config['Plugin']['Stats']))
		{
			if(isset($this->_main->config['Plugin']['Stats']['Awards']))
				$this->_awards = Leelabot::parseBool($this->_main->config['Plugin']['Stats']['Awards']);
		}
	}
}

return $this->initPlugin(array(
'name' => 'stats',
'className' => 'PluginStats',
'autoload' => TRUE));