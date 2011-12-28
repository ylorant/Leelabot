<?php
/**
 * \file plugins/clientbase.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.9
 * \brief Basic rights plugin for leelabot. It manages simple rights, with ease of configuration.
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
 * This file contains the plugin basicrights, managing the rights on the bot in a simple way. With this management, each user can have their own right level. This level
 * is shared on all servers, and all levels must be modified one by one.
 */

class PluginBasicRights extends Plugin
{
	public $rights = array();
	
	public function init()
	{
		
		if(!isset($this->config['RightsFile']) || (!is_file($this->_main->getConfigLocation().'/'.$this->config['RightsFile']) && !touch($this->_main->getConfigLocation().'/'.$this->config['RightsFile'])))
		{
			Leelabot::message("Can't load rights file. Rights will not be saved", array(), E_WARNING);
			if(!isset($this->config['FirstPasswd']))
				Leelabot::message("There is no initial password for super-user. Rights management will not work.", array(), E_WARNING);
			return FALSE;
		}
		
		$this->config['RightsFile'] = $this->_main->getConfigLocation().'/'.$this->config['RightsFile'];
		$this->loadRights($this->config['RightsFile']);
		
		if(empty($this->rights) && !isset($this->config['FirstPasswd']))
			Leelabot::message("There is no initial password for super-user. Rights management will not work.", array(), E_WARNING);
		
		if(!isset($this->config['FirstPasswd']))
			$this->deleteCommand('setadmin');
	}
	
	public function CommandSetadmin($id, $command)
	{
		if(!isset($command[0]))
		{
			RCon::tell($id, 'Not enough parameters');
			return TRUE;
		}
		
		$player = Server::getPlayer($id);
		
		//If the password is wrong, we send an error message and we log info for player.
		if($command[0] != $this->config['FirstPasswd'])
		{
			RCon::tell($id, 'Invalid password');
			Leelabot::message('Invalid super-user right granting attempt from :', array(), E_WARNING);
			Leelabot::message('	Name: $0', array($player->name));
			Leelabot::message('	GUID: $0', array($player->guid));
			Leelabot::message('	IP: $0', array($player->ip));
			return TRUE;
		}
		
		//The password is good, so we register the player, we notify him and we unload the command.
		$this->rights[$player->name] = array();
		$this->rights[$player->name]['GUID'] = array(Server::getName() => $player->guid);
		$this->rights[$player->name]['IP'] = $player->ip;
		$this->saveRights($this->config['RightsFile']);
	}
	
	public function loadRights($filename)
	{
		$contents = file_get_contents($filename);
		$this->rights = $this->_main->parseINIStringRecursive($contents);
		
		return TRUE;
	}
	
	public function saveRights($filename)
	{
		return file_put_contents($filename, $this->_main->generateINIStringRecursive($this->rights));
	}
}

$this->addPluginData(array(
'name' => 'basicrights',
'className' => 'PluginBasicRights',
'display' => 'Basic rights plugin',
'autoload' => TRUE));
