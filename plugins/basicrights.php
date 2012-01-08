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
		
		if(isset($this->config['Verbose']))
			$this->config['Verbose'] = Leelabot::parseBool($this->config['Verbose']);
		else
			$this->config['Verbose'] = FALSE;
		
		//Adding event listener
		$this->_plugins->addEventListener('rights', 'Rights');
		
		//Setting command !giverights level
		$this->setCommandLevel('giverights', 100);
	}
	
	public function destroy()
	{
		$this->_plugins->deleteEventListener('rights');
		
		return TRUE;
	}
	
	public function SrvEventClientConnect($id)
	{
		$player = Server::getPlayer($id);
		$player->auth = NULL;
	}
	
	public function SrvEventClientUserinfoChanged($id, $userinfo)
	{
		$player = Server::getPlayer($id);
		
		//We first wait for the client to begin playing at least one time before going further
		if(!$player->begin)
			return;
		
		//We check that the player is authed
		if(!$player->auth)
		{
			$this->SrvEventClientBegin($id);
			if(!$player->auth)
				return;
		}
		
		if(!in_array($userinfo['n'], $this->rights[$player->auth]['aliases']))
		{
			$this->rights[$player->auth]['aliases'][] = $player->name;
			$this->saveRights($this->config['RightsFile']);
			if($this->config['Verbose'])
				RCon::tell($id, 'Added new alias \'$0\' for your auth ($1)', array($userinfo['n'], $player->auth));
		}
	}
	
	public function SrvEventClientBegin($id)
	{
		$player = Server::getPlayer($id);
		
		foreach($this->rights as $authname => $auth)
		{
			if((in_array($player->name, $auth['aliases']) && $player->ip == $auth['IP'])
				|| (in_array($player->name, $auth['aliases']) && (isset($auth['GUID'][Server::getName()]) && $auth['GUID'][Server::getName()] == $player->guid))
				|| ($player->ip == $auth['IP'] && $player->guid == (isset($auth['GUID'][Server::getName()]) && $auth['GUID'][Server::getName()] == $player->guid)))
			{
				if($this->config['Verbose'])
					RCon::tell($id, 'You\'re now authed as $0', $authname);
					
				//Auth the player
				$player->auth = $authname;
				$player->level = $auth['level'];
				
				//Save player data modification
				if(!in_array($player->name, $auth['aliases']))
					$this->rights[$authname]['aliases'][] = $player->name;
				elseif($player->ip != $auth['IP'])
					$this->rights[$authname]['IP'] = $player->ip;
				elseif(!isset($auth['GUID'][Server::getName()]) || $auth['GUID'][Server::getName()] != $player->guid)
					$this->rights[$authname]['GUID'][Server::getName()] = $player->guid;
				else
					break;
				
				$this->saveRights($this->config['RightsFile']);
				break;
			}
		}
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
			Leelabot::message('	Name: $0', $player->name);
			Leelabot::message('	GUID: $0', $player->guid);
			Leelabot::message('	IP: $0', $player->ip);
			return TRUE;
		}
		
		//The password is good, so we register the player, we notify him and we unload the command.
		$this->rights[$player->name] = array();
		$this->rights[$player->name]['aliases'] = array($player->name);
		$this->rights[$player->name]['GUID'] = array(Server::getName() => $player->guid);
		$this->rights[$player->name]['IP'] = $player->ip;
		$this->rights[$player->name]['level'] = 100;
		$this->saveRights($this->config['RightsFile']);
		$this->deleteCommand('setadmin', $this);
		
		$this->_plugins->callEvent('rights', 'authenticate', $id, $player->name);
		
		$player->level = 100;
		$player->auth = $player->name;
		
		RCon::tell($id, 'You\'re now a super-user on this server. Your pair IP/aliases will be used to authenticate you on other servers.');
		RCon::tell($id, 'The !setadmin command is deactived for the current session. Make sure to remove the password from config.');
	}
	
	public function CommandGiveRights($id, $command)
	{
		if(!isset($command[1]))
		{
			RCon::tell($id, 'Not enough parameters');
			return TRUE;
		}
		
		$player = Server::searchPlayer($command[0]);
		
		if(!$player)
		{
			RCon::tell($id, 'Unknown player');
			return TRUE;
		}
		elseif(is_array($player))
		{
			RCon::tell($id, 'Multiple players found : $0', array(join(', ', $player)));
			return TRUE;
		}
		
		$level = intval($command[1]);
		
		//If the player is not authed, we create an entry for him in the rights file
		if(!$player->auth)
		{
			$this->rights[$player->name] = array();
			$this->rights[$player->name]['aliases'] = array($player->name);
			$this->rights[$player->name]['GUID'] = array(Server::getName() => $player->guid);
			$this->rights[$player->name]['IP'] = $player->ip;
			$this->rights[$player->name]['level'] = $level;
			$this->saveRights($this->config['RightsFile']);
			
			$player->auth = $player->name;
			
			if($this->config['Verbose'])
			{
				RCon::tell($id, 'Created auth user $0', $player->name);
				RCon::tell($player->id, 'You\'re now authed as $0', $player->name);
		}
		else
			$this->rights[$player->auth] = $level;
		
		$player->level = $level;
		
		RCon::tell($id, 'User $0 level set to $1', array($player->auth, $player->level));
		
		return TRUE;
	}
	
	public function loadRights($filename)
	{
		$contents = file_get_contents($filename);
		$this->rights = $this->_main->parseINIStringRecursive($contents);
		
		if(!$this->rights)
			$this->rights = array();
		
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
