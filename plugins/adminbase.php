<?php
/**
 * \file plugins/clientbase.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.9
 * \brief Admin base plugin for Leelabot. It allows to send most of the admin commands.
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
 * This file contains the plugin adminbase, holding most of basic admin commands.
 */

class PluginAdminBase extends Plugin
{
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		
	}
	
	/** Kick command. Kicks a player from the server.
	 * This function is bound to the !kick command. It kicks a player from the server, according to the name given in first parameter. It does not needs the complete
	 * in-game name to work, a search is performed with the given data. It will ail if there is more than 1 person on the server corresponding with the given mask.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandKick($id, $command)
	{
		$target = Server::searchPlayer($command[0]);
		if(!$target)
			RCon::tell($id, "No player found.");
		elseif(is_array($target))
		{
			$players = array();
			foreach($target as $p)
				$players[] = Server::getPlayer($p)->name;
			RCon::tell($id, "Multiple players found : $0", array(join(', ', $players)));
		}
		else
			RCon::kick($target);
	}
}

$this->addPluginData(array(
'name' => 'adminbase',
'className' => 'PluginAdminBase',
'display' => 'Admin base plugin',
'autoload' => TRUE));
