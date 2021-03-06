<?php
/**
 * 
 * \file plugins/dummy.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Dummy plugin for leelabot. It is not very useful.
 * \depends adminbase
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
 * This file contains the dummy plugin made for testing Leelabot core. As all test files, this will NOT be further documented.
 */
class PluginDummy extends Plugin
{
	public function init()
	{
		
	}
	
	public function CommandReloadPlugin($id, $command)
	{
		$this->_plugins->unloadPlugin($command[0]);
		$this->_plugins->loadPlugin($command[0]);
	}
	
	public function SrvEventClientBegin($id)
	{
		$player = Server::getPlayer($id);
		if(!$player->begin)
		RCon::say('Hello '.$player->name);
	}
	
	public function SrvEventClientConnect($id)
	{
		Leelabot::message('Client connected : $0', array($id));
	}
	
	public function CommandShortRoutine($player, $command)
	{
		$this->changeRoutineTimeInterval('RoutineCheckPlayers', 1);
	}
	
	public function CommandHold($player, $command)
	{
		if(ServerList::serverEnabled($command[0]))
			ServerList::getServer($command[0])->hold();
	}
	
	public function CommandHello($id, $command)
	{
		$rcon = ServerList::getServerRCon(Server::getName());
		$player = Server::getPlayer($id);
		if(isset($command[0]))
		{
			$target = Server::getPlayer(Server::searchPlayer($command[0]));
			if($target === FALSE)
				$target = 'nobody';
			if(is_array($target))
				$target = $target[0];
			$rcon->say('Hello '.$player->name.', it seems you like '.$target->name.' !');
		}
		else
			$rcon->say('Hay '.$player->name.' !');
	}
	
	public function CommandSavePlayers($id, $command)
	{
		$players = array();
		foreach(Server::getPlayerList() as $pid => $player)
		{
			if(!isset($players[$player->name]))
				$players[$player->name] = $player;
			else
				$players[$player->name.'_'.$pid] = $player;
		}
		
		file_put_contents('playerdump.ini', $this->_main->generateINIStringRecursive($players));
	}
	
	public function CommandBroadcast($id, $command)
	{
		$servers = ServerList::getList();
		$message = join(" ", $command);
		
		foreach($servers as $server)
			ServerList::getServerRCon($server)->say($message, array(), FALSE);
	}
	
	public function WSMethodPlayer($id)
	{
		if(!is_null($player = Server::getPlayer($id)))
			return array('success' => true, 'data' => $player->toArray());
		return array('success' => false, 'error' => 'Unknown player ID');
	}
	
	public function WAPageIndex()
	{
		return "Hello, world !";
	}
	
	public function RightsAuthenticate($id, $auth)
	{
		RCon::tell($id, 'Hey, you\'re authed as $0 !', $auth);
	}
}

$this->addPluginData(array(
'name' => 'dummy',
'className' => 'PluginDummy',
'display' => 'Dummy plugin',
'dependencies' => array('adminbase'),
'autoload' => TRUE));
