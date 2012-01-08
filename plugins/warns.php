<?php
/**
 * \file plugins/warns.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \version 1.0
 * \brief Warns plugin for Leelabot. It allows give warning to bad player avec good player can forgive bad player :D
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
 * This file contains warns plugin.
 * 
 * Commands in game :
 * !clearwarns => clear warnings of one player
 */

/**
 * \brief Plugin warns class.
 * This class contains the methods and properties needed by the warns plugin. 
 */
class PluginWarns extends Plugin
{
	//Bad Words
	private $_badwords = array();
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		// Got an warning on teamkill
		if(isset($this->config['TeamKills']))
			$this->config['TeamKills'] = Leelabot::parseBool($this->config['TeamKills']);
		else
			$this->config['TeamKills'] = TRUE;
			
		// Got an warning on teamhit
		if(isset($this->config['TeamHits']))
			$this->config['TeamHits'] = Leelabot::parseBool($this->config['TeamHits']);
		else
			$this->config['TeamHits'] = FALSE;
		
		// Got an warning if player say an bad word
		if(!isset($this->config['BadWordsFile']) || (!is_file($this->_main->getConfigLocation().'/'.$this->config['BadWordsFile']) && !touch($this->_main->getConfigLocation().'/'.$this->config['BadWordsFile'])))
		{
			if(isset($this->config['BadWords']))
			{
				$this->config['BadWords'] = Leelabot::parseBool($this->config['BadWords']);
				$this->_badwords = explode('\n', file_get_contents($this->_main->getConfigLocation().'/'.$this->config['BadWordsFile']));
			}
			else
				$this->config['BadWords'] = TRUE;
		}
		else
		{
			if(isset($this->config['BadWords']))
			{
				$this->config['BadWords'] = Leelabot::parseBool($this->config['BadWords']);
				
				if($this->config['BadWords'])
					Leelabot::message("The BadWordsFile configuration isn't set. The bot can't load BadWords warning.", array(), E_WARNING);
			}
			
			$this->config['BadWords'] = FALSE;
		}
			
		// Clear warning on InitGame
		if(isset($this->config['ClearOnInit']))
			$this->config['ClearOnInit'] = Leelabot::parseBool($this->config['ClearOnInit']);
		else
			$this->config['ClearOnInit'] = TRUE;
			
		// Kick on [WarnsKick] warns (must be a positive number)
		if(!(isset($this->config['WarnsKick']) && is_numeric($this->config['WarnsKick']) && $this->config['WarnsKick'] > 0))
			$this->config['WarnsKick'] = 3;
			
		// Kick player after [SecondsBeforeKick]  (must be a positive number or 0)
		if(!(isset($this->config['SecondsBeforeKick']) && is_numeric($this->config['SecondsBeforeKick']) && $this->config['SecondsBeforeKick'] >= 0))
			$this->config['WarnsKick'] = 60;
		
		//Warns of players
		Server::set('warns', array());
		Server::set('forgive', array());
		
		//
		//And we delete useless event
		//
			
		if(!$this->config['TeamKills'])
			$this->deleteServerEvent('Kill');
			
		if(!$this->config['TeamHits'])
			$this->deleteServerEvent('Hit');
		
		if(!$this->config['BadWords'])
			$this->deleteServerEvent('Say');
	}
	
	public function destroy()
	{
		Server::set('warns', NULL);
		Server::set('forgive', NULL);
	}
	
	public function RoutineWarns()
	{
		$_warns = Server::get('warns');
		
		foreach($_warns as $player => &$warns)
		{
			if($warns['num'] >= $this->config['WarnsKick'] && time() >= ($warns['last']+$this->config['SecondsBeforeKick']))
			{
				Rcon::tell($player, 'You have $warns warnings. You will be kicked', array('warns' => $warns['num']));
				$this->_clearWarn($player);
				usleep(500000);
				Rcon::kick($player);
			}
		}
	}
	
	private function _addWarn($warned, $victim = FALSE)
	{
		$_warns = Server::get('warns');
		$_warns[$warned]['num']++;
		$_warns[$warned]['last'] = time();
		Server::set('warns', $_warns);
		
		if($victim !== FALSE)
		{
			$_forgive = Server::get('forgive');
			$_forgive[$victim][time()] = $warned;
			Server::set('forgive', $_forgive);
		}
		
		return $_warns[$warned]['num'];
	}
	
	private function _forgive($player, $warned)
	{
		$_warns = Server::get('warns');
		$_forgive = Server::get('forgive');
		
		if(count($_forgive[$player]))
		{
			ksort($_forgive[$player]);
			$warned = array_shift($_forgive[$player]);
			
			$_warns[$warned]['num']--;
			$_warns[$warned]['last'] = time();
			
			Server::set('warns', $_warns);
			Server::set('forgive', $_forgive);
		
			return $_warns[$warned]['num'];
		}
		else
		{
			return FALSE;
		}
	}
	
	private function _clearWarn($warned)
	{
		$_warns = Server::get('warns');
		$_forgive = Server::get('forgive');
		
		$_warns[$warned]['num'] = 0;
		
		foreach($_forgive as &$victim)
			foreach($victime as $time => $badman)
				if($badman == $warned)
					unset($victim[$time]);
					
		Server::set('warns', $_warns);
		Server::set('forgive', $_forgive);
	}
	
	public function CommandFp($id, $cmd)
	{
		if(!empty($cmd[0]))
		{
			$target = Server::searchPlayer($cmd[0]);
			if(!$target)
				RCon::tell($id, "No player found.");
			elseif(is_array($target))
			{
				$players = array();
				foreach($target as $p)
					$players[] = Server::getPlayer($p)->name;
				RCon::tell($id, 'Multiple players found : $0', array(join(', ', $players)));
			}
			else
			{
				if($this->_forgive($id, $target))
					Rcon::say($id, '$player has forgiven $target.', array('player' => Server::getPlayer($id)->name, 'target' => Server::getPlayer($target)->name));
				else
					RCon::tell($id, 'No one to forgive.');
			}
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	public function CommandClearWarns($id, $cmd)
	{
		if(!empty($cmd[0]))
		{
			$target = Server::searchPlayer($cmd[0]);
			if(!$target)
				RCon::tell($id, "No player found.");
			elseif(is_array($target))
			{
				$players = array();
				foreach($target as $p)
					$players[] = Server::getPlayer($p)->name;
				RCon::tell($id, 'Multiple players found : $0', array(join(', ', $players)));
			}
			else
			{
				$this->_clearWarn($target);
				Rcon::tell($id, 'The warnings of $player are cleared.', array('player' => Server::getPlayer($target)->name));
				Rcon::tell($target, "Your warnings are cleared.");
			}
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	public function SrvEventClientConnect($id)
	{
		$_warns = Server::get('warns');
		$_warns[$id] = array('num' => 0, 'last' => 0);
		Server::set('warns', $_warns);
	}
	
	public function SrvEventClientDisconnect($id)
	{
		$_warns = Server::get('warns');
		unset($_warns[$id]);
		Server::set('warns', $_warns);
	}
	
	public function SrvEventKill($killer, $killed, $type, $weapon = NULL)
	{
		$killer = Server::getPlayer($killer);
		$killed = Server::getPlayer($killed);
		
		if($killer->team != $killed->team && !in_array($type, array('1', '3', '6', '7', '9', '10', '31', '32', '34')))
		{
			$warns = $this->_addWarn($killer->id, $killed->id);
			Rcon::say('Warning : $killer was team killed $killed ! (He has $warns warnings)', array('killer' => $killer->id, 'killed' => $killed->id, 'warns' => $warns));
			Rcon::tell($killed->id, 'Please type !fp if you want to forgive him.');
		}
	}
	
	public function SrvEventHit($player, $shooter, $partOfBody, $weapon)
	{
		$shooter = Server::getPlayer($shooter);
		$player = Server::getPlayer($player);
		
		if($shooter->team != $player->team)
		{
			$warns = $this->_addWarn($shooter->id, $player->id);
			Rcon::say('Warning : $shooter has team hit $player ! (He has $warns warnings)', array('shooter' => $shooter->id, 'player' => $player->id, 'warns' => $warns));
			Rcon::tell($player->id, 'Please type !fp if you want to forgive him.');
		}
	}
	
	public function SrvEventSay($id, $contents)
	{
		if($contents[0] != '!')
		{
			$addwarn = FALSE;
			
			foreach($this->_badwords as $word)
			{
				if(strpos(strtolower($contents), $word) !== FALSE)
					$addwarn = TRUE;
			}
			
			if($addwarn)
			{
				$warns = $this->_addWarn($id);
				Rcon::tell($id, 'Please be careful with your language. You have $warns warnings', array('warns' => $warns));
			}
		}
	}
	
	public function SrvEventInitGame($serverinfo)
	{
		if($this->config['ClearOnInit'])
			Server::set('warns', array());
			Server::set('forgive', array());
	}
}

$this->addPluginData(array(
'name' => 'warns',
'className' => 'PluginWarns',
'display' => 'Warning Plugin',
'autoload' => TRUE));