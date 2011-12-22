<?php
/**
 * 
 * \file plugins/dummy.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Dummy plugin for leelabot. It is not very useful.
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
			$rcon->say('Hello '.$player->name.' !');
	}
	
	public function WSMethodPlayer($id)
	{
		return serialize(Storage::toArray(Server::getPlayer($id)));
	}
	
	public function WSMethodKick($server, $id)
	{
		return ServerList::getServerRCon($server)->kick($id);
	}
	
	public function WAPageHello()
	{
		return "Hello, world !";
	}
}

$this->addPluginData(array(
'name' => 'dummy',
'className' => 'PluginDummy',
'display' => 'Dummy plugin',
'dependencies' => array('adminbase'),
'autoload' => TRUE));
