<?php
/**
 * \file plugins/dummy.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Dummy plugin for Leelabot.
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
		$this->changeRoutineTimeInterval('RoutineCheckPlayers', 50);
	}
	
	public function RoutineCheckPlayers()
	{
		echo date('h:i:s').' Check.'.PHP_EOL;
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
}

return $this->initPlugin(array(
'name' => 'dummy',
'className' => 'PluginDummy',
'autoload' => TRUE));
