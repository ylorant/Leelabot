<?php
/**
 * \file plugins/adminbase.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 1.2
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
	
	private $_mapUt4List = array('casa', 'kingdom', 'turnpike', 'abbey', 'prague', 'mandolin', 'uptown', 'algiers', 'austria', 'maya', 'tombs', 'elgin', 'oildepot', 'swim', 'harbortown', 'ramelle', 'toxic', 'sanc', 'riyadh', 'ambush', 'eagle', 'suburbs', 'crossing', 'subway', 'tunis', 'thingley'); ///< List of common urban terror maps
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's initialization, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		//Urban Terror commands level
		$this->setCommandLevel('die', 100);
		$this->setCommandLevel('reload', 100);
		$this->setCommandLevel('rcon', 100);
		$this->setCommandLevel('sysinfo', 100);
		$this->setCommandLevel('mode', 50);
		$this->setCommandLevel('pause', 50);
		$this->setCommandLevel('cfg', 50);
		$this->setCommandLevel('set', 50);
		$this->setCommandLevel('exec', 50);
		$this->setCommandLevel('whois', 10);
		$this->setCommandLevel('shuffle', 10);
		$this->setCommandLevel('shuffleteams', 10);
		$this->setCommandLevel('swap', 10);
		$this->setCommandLevel('slap', 10);
		$this->setCommandLevel('nuke', 10);
		$this->setCommandLevel('force', 10);
		$this->setCommandLevel('say', 10);
		$this->setCommandLevel('restart', 10);
		$this->setCommandLevel('veto', 10);
		$this->setCommandLevel('mute', 10);
		$this->setCommandLevel('map', 10);
		$this->setCommandLevel('nextmap', 10);
		$this->setCommandLevel('bigtext', 10);
		$this->setCommandLevel('kickall', 10);
		$this->setCommandLevel('list', 10);
		$this->setCommandLevel('cyclemap', 10);
		$this->setCommandLevel('kick', 10);
			
		//IRC commands level (0:all , 1:voice, 2:operator)
		if($this->_plugins->listenerExists('irc'))
		{
			$this->_plugins->setEventLevel('irc', 'kick', 2);
			$this->_plugins->setEventLevel('irc', 'kickall', 2);
			$this->_plugins->setEventLevel('irc', 'slap', 2);
			$this->_plugins->setEventLevel('irc', 'mute', 2);
			$this->_plugins->setEventLevel('irc', 'say', 2);
			$this->_plugins->setEventLevel('irc', 'bigtext', 2);
			$this->_plugins->setEventLevel('irc', 'map', 2);
			$this->_plugins->setEventLevel('irc', 'nextmap', 2);
			$this->_plugins->setEventLevel('irc', 'cyclemap', 2);
			$this->_plugins->setEventLevel('irc', 'restart', 2);
			$this->_plugins->setEventLevel('irc', 'reload', 2);
		}
		
		/* NOTE : The command list above is not in alphabetical order */
	}
	
	/** Sysinfo command. Returns data about PHP.
	 * This function returns informations about current memory consumption of the bot, and the current PHP version.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandSysinfo($id, $command)
	{
		
		RCon::tell($id, "Memory consumption: $0 used / $1 allowed to php.", array($this->convert(memory_get_usage()), $this->convert(memory_get_usage(TRUE))));
		RCon::tell($id, "Cpu usage: $0", array(Leelabot::getCpuUsage()));
		RCon::tell($id, "PHP version : $0", array(phpversion()));
		
	}
	
	private function convert($size)
	{
		$unit=array('b','kb','mb','gb','tb','pb');
		return round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
	}
	
	/** Bigtext command. Sends a message for all players.
	 * This function is bound to the !bigtext command. It sends a message for all players, displayed on the center of screen, 
	 * like flag captures.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandBigtext($id, $command)
	{
		if(!empty($command[0]))
		{
			$text = implode(' ', $command);
			RCon::bigtext('"'.$text.'"');
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Exec command. Executes a configuration file.
	 * This function is bound to the !cfg command. It executes a .cfg file on the server.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	
	public function CommandCfg($id, $command)
	{
		if(!empty($command[0]))
			RCon::exec($command[0]);
		else
			Rcon::tell($id, 'Missing parameters.');
	}
	
	/** Cyclemap command. Go to the next map.
	 * This function is bound to the !cyclemap command. It changes the map, usually following the cyclemap file.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandCyclemap($id, $command)
	{
		Rcon::cyclemap();
	}
	
	/** Kill the bot
	 * This function is bound to the !die command. Kills the bot.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandDie($id, $command)
	{
		foreach(ServerList::getList() as $server)
			ServerList::getServerRCon($server)->bigtext('Shutting Down...');
	}
	
	/** Forceteam command. Force a player in a team.
	 * This function is bound to the !force command. Forces a player to join a defined team. Available teams are : red, blue, spectator.
	 * 	 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandForce($id, $command)
	{
		if(!empty($command[0]) && !empty($command[1]))
		{
			$target = Server::searchPlayer($command[0]);
			if($target === FALSE)
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
				if(in_array($command[1], array('spec', 'spect')))
					$command[1] = 'spectator';
				
				if(in_array($command[1], array('red', 'blue', 'spectator'))) 
					RCon::forceteam($target.' '.$command[1]);
				else
					Rcon::tell($id, 'Invalid parameter. Available teams : red, blue, spectator.');
			}
		}
		else
			Rcon::tell($id, 'Missing parameters.');
	}
	
	/** Kick command. Kicks a player from the server.
	 * This function is bound to the !kick command. It kicks a player from the server, according to the name given in first parameter.
	 * It does not needs the complete in-game name to work, a search is performed with the given data.
	 * It will fail if there is more than 1 person on the server corresponding to the given mask.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandKick($id, $command)
	{
		if(!empty($command[0]))
		{
			$target = Server::searchPlayer($command[0]);
			if($target === FALSE)
				RCon::tell($id, "No player found.");
			elseif(is_array($target))
			{
				$players = array();
				foreach($target as $p)
					$players[] = Server::getPlayer($p)->name;
				RCon::tell($id, 'Multiple players found : $0', array(join(', ', $players)));
			}
			else
				RCon::kick($target);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Kickall command. Kick players from the server.
	 * This function is bound to the !kickall command. It kick all players who match the characters sent. It does not needs the complete
	 * in-game name to work, a search is performed with the given data.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandKickAll($id, $command)
	{
		if(!empty($command[0]))
		{
			$target = Server::searchPlayer($command[0]);
			if($target === FALSE)
				RCon::tell($id, "No player found.");
			elseif(is_array($target))
			{
				$players = array();
				foreach($target as $p)
					RCon::kick($p);
			}
			else
				RCon::kick($target);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Gives the list of players on the server.
	 * This function is bound to the !list command. This function shows the list of players with their id.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandList($id, $command)
	{
		$list = '';
		$players = Server::getPlayerList();
		
		foreach($players as $p)
		{
			$list .= '['.$p->id.'] '.$p->name.', '; 
		}
		
		Rcon::tell($id, '$listofplayers', array('listofplayers' => $list));
	}
	
	/** Mute command. Mutes a player in the server.
	 * This function is bound to the !mute command. It mute a player from the server, according to the name given in first parameter. It does not needs the complete
	 * in-game name to work, a search is performed with the given data. It will ail if there is more than 1 person on the server corresponding with the given mask.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandMute($id, $command)
	{
		if(!empty($command[0]))
		{
			$target = Server::searchPlayer($command[0]);
			if($target === FALSE)
				RCon::tell($id, "No player found.");
			elseif(is_array($target))
			{
				$players = array();
				foreach($target as $p)
					$players[] = Server::getPlayer($p)->name;
				RCon::tell($id, 'Multiple players found : $0', array(join(', ', $players)));
			}
			else
				RCon::mute($target);
		}
		else
			Rcon::tell($id, 'Missing parameters.');
	}
	
	/** Map command. Change the actual map. (reload the server)
	 * This function is bound to the !map command. He change map with the name of the map given by the player. For the official map you can give the name of the map without "ut4_".
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandMap($id, $command)
	{
		if(isset($command[0]))
		{
			if(in_array($command[0], $this->_mapUt4List))
				RCon::map('"ut4_'.$command[0].'"');
			else
				RCon::map('"'.$command[0].'"');
		}
		else
			Rcon::tell($id, 'Missing parameters.');
	}
	
	/** Set g_nextmap command. Change the next map.
	 * This function is bound to the !nextmap command. He set the next map with the name of the map given by the player. For the official map you can give the name of the map without "ut4_".
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandNextmap($id, $command)
	{
		if(isset($command[0]))
		{
			if(in_array($command[0], $this->_mapUt4List))
				RCon::set('g_nextmap "ut4_'.$command[0].'"');
			else
				RCon::set('g_nextmap "'.$command[0].'"');
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** set g_gametype command. Change the gamtype with reload
	 * This function is bound to the !mode command. He set the gamtype and send reload command.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandMode($id, $command)
	{
		switch(strtolower($command[0]))
		{
			case 'ts':
			case 'team survivor':
				$gametype = 4;
				break;
			case 'ctf':
			case 'capture the flag':
				$gametype = 7;
				break;
			case 'tdm':
			case 'team deathmatch':
				$gametype = 3;
				break;
			case 'ffa':
			case 'free for all':
				$gametype = 0;
				break;
			case 'cah':
			case 'capture and hold':
				$gametype = 6;
				break;
			case 'bomb':
			case 'bomb mode':
				$gametype = 8;
				break;
			case 'ftl':
			case 'follow the leader':
				$gametype = 5;
				break;
			default:
				$gametype = FALSE;
				break;
		}
		if($gametype !== FALSE)
		{
			Rcon::set('g_gametype '.$gametype);
			Rcon::tell($id,'New Gametype : '.$command[0]);
			Rcon::reload();
		}
		else
		{
			Rcon::tell($id, 'Gametype inconnu.');
		}
	}
	
	/** Nuke command. Nukes a player from the server.
	 * This function is bound to the !nuke command. It nukes a player from the server, according to the name given in first parameter. It does not needs the complete
	 * in-game name to work, a search is performed with the given data. It will ail if there is more than 1 person on the server corresponding with the given mask.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandNuke($id, $command)
	{
		if(!empty($command[0]))
		{
			$target = Server::searchPlayer($command[0]);
			if($target === FALSE)
				RCon::tell($id, "No player found.");
			elseif(is_array($target))
			{
				$players = array();
				foreach($target as $p)
					$players[] = Server::getPlayer($p)->name;
				RCon::tell($id, 'Multiple players found : $0', array(join(', ', $players)));
			}
			else
				RCon::nuke($target);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Cyclemap command. Make a pause.
	 * This function is bound to the !pause command. If one or more players are causing problems so we can put a pause in the game to kick them.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandPause($id, $command)
	{
		Rcon::pause();
	}
	
	/** Reload command. Reload the game.
	 * This function is bound to the !reload command. It reload the server.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandReload($id, $command)
	{
		Rcon::reload();
	}
	
	/** Restart command. Restart the game.
	 * This function is bound to the !restart command. It restart the round.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandRestart($id, $command)
	{
		Rcon::restart();
	}
	
	/** Say command. Send a message for all.
	 * This function is bound to the !say command. He send a message for all, displayed on the chat of game.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandSay($id, $command)
	{
		if(!empty($command[0]))
		{
			$text = implode(' ', $command);
			RCon::say($text);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** set command. Set variable.
	 * This function is bound to the !cfg command. Set variable on the server.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	
	public function CommandSet($id, $command)
	{
		if(!empty($command[0]) && !empty($command[1]))
		{
			$cmd = $command;
			array_shift($cmd);
			$command[1] = implode(' ', $cmd);
			
			RCon::set($command[0].' "'.$command[1].'"');
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Shuffleteams command. Shuffle teams, with restart.
	 * This function is bound to the !shuffleteams command. Shuffle teams, with restart.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandShuffleTeams($id, $command)
	{
		Rcon::shuffleteams();
	}
	
	
	/** Shuffle teams, without restart.
	 * This function is bound to the !shuffle command. Shuffle teams, without restart.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandShuffle($id, $command)
	{
		RCon::shuffle();
	}
	
	/** Slap command. Slaps a player from the server.
	 * This function is bound to the !slap command. It slaps a player from the server, according to the name given in first parameter. It does not needs the complete
	 * in-game name to work, a search is performed with the given data. It will ail if there is more than 1 person on the server corresponding with the given mask.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandSlap($id, $command)
	{
		if(!empty($command[0]))
		{
			$target = Server::searchPlayer($command[0]);
			if($target === FALSE)
				RCon::tell($id, "No player found.");
			elseif(is_array($target))
			{
				$players = array();
				foreach($target as $p)
					$players[] = Server::getPlayer($p)->name;
				RCon::tell($id, 'Multiple players found : $0', array(join(', ', $players)));
			}
			else
				RCon::slap($target);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Swapteams command. Swap the teams.
	 * This function is bound to the !swap command. Swap the teams.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandSwap($id, $command)
	{
		Rcon::swapteams();
	}
	
	/** Veto command. Cancel a vote.
	 * This function is bound to the !veto command. It cancels a vote launch by a player.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandVeto($id, $command)
	{
		Rcon::veto();
	}
	
	
	/** Gives information about a player
	 * This function is bound to the !whois command. It gives the name, ip, level, host of the player specified in argument.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandWhois($id, $command)
	{
		if(!empty($command[0]))
		{
			$target = Server::searchPlayer($command[0]);
			if($target === FALSE)
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
				$target = Server::getPlayer($target);
				Rcon::tell($id, 'Whois ^2$name ^3> IP : $addr, Level : $level, Host : $host', array('name' => $target->name, 'addr' => $target->addr, 'level' => $target->level, 'host' => gethostbyaddr($target->addr)));
			}
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Sends directly an RCon command to the server.
	 * This function is bound to the !rcon command. It sends directly an RCon command to the server, without altering it.
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandRCon($id, $command)
	{
		RCon::send(join(' ', $command));
	}
	
	// Webservice methods //
	
	public function WSMethodKick($server, $id)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		if(ServerList::getServer($server)->getPlayer($id) == NULL)
			return array('success' => false, 'error' => 'No player found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->kick($id)));
	}
	
	public function WSMethodCfg($server, $cfg)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->exec($cfg)));
	}
	
	public function WSMethodCyclemap($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->cyclemap()));
	}
	
	public function WSMethodDie()
	{
		foreach(ServerList::getList() as $server)
			ServerList::getServerRCon($server)->bigtext('Shutting Down...');
		$this->_main->stop();
		
		return array('success' => 'true');
	}
	
	public function WSMethodForce($server, $player, $team)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		$target = ServerList::getServer($server)->getPlayer($player);
		if($target == NULL)
			return array('success' => false, 'error' => 'No player found');
		else
		{
			if(in_array($team, array('spec', 'spect')))
				$team = 'spectator';
			
			if(in_array($team, array('red', 'blue', 'spectator'))) 
				return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->forceteam($target->id.' '.$team)));
			else
				return array('success' => false, 'error' => 'Invalid parameter. Available teams : red, blue, spectator.');
		}
	}
	
	public function WSMethodKickAll($server, $mask)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		$server = ServerList::getServer($server);
		$target = $server->searchPlayer($mask);
		if($target === FALSE)
			return array('success' => false, 'error' => 'No player found.');
		elseif(is_array($target))
		{
			$players = array();
			$kick = true;
			foreach($target as $p)
				$kick = $kick && RCon::kick($p);
			
			return array('success' => Leelabot::boolString($kick), 'data' => $target);
		}
		else
			return array('success' => Leelabot::boolString(RCon::kick($target)));
	}
	
	public function WSMethodPlayerList($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		$list = array();
		$server = ServerList::getServer($server);
		$players = $server->getPlayerList();
		
		foreach($players as $p)
			$list[] = array('id' => $p->id, 'name' => $p->name, 'team' => $p->team);
		
		return array('success' => true, 'data' => $list);
	}
	
	public function WSMethodMap($server, $map)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->map($map)));
	}
	
	public function WSMethodMode($server, $mode)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		if($mode > 8 || $mode < 0)
			return array('success' => false, 'error' => 'Invalid gametype');
		
		ServerList::getServerRCon($server)->set('g_gametype', intval($mode));
	}
	
	public function WSMethodMute($server, $id)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		if(ServerList::getServer($server)->getPlayer($id) == NULL)
			return array('success' => false, 'error' => 'No player found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->mute($id)));
	}
	
	public function WSMethodNextmap($server)
	{
		if(!($server = ServerList::serverEnabled($server)))
			return array('success' => false, 'error' => 'Server not found');
		
		if($server->getPlayer($id) == NULL)
			return array('success' => false, 'error' => 'No player found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->nextmap()));
	}
	
	public function WSMethodNuke($server, $id)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		if(ServerList::getServer($server)->getPlayer($id) == NULL)
			return array('success' => false, 'error' => 'No player found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->nuke($id)));
	}
	
	public function WSMethodPause($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->pause()));
	}
	
	public function WSMethodReload($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->reload()));
	}
	
	public function WSMethodRestart($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->restart()));
	}
	
	public function WSMethodSay($server, $message)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->say($message)));
	}
	
	public function WSMethodSet($server, $var, $value)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->set($var, $value)));
	}
	
	public function WSMethodShuffleTeams($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->shuffle(TRUE)));
	}
	
	public function WSMethodShuffle($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->shuffle()));
	}
	
	public function WSMethodSlap($server, $id)
	{
		
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		if(ServerList::getServer($server)->getPlayer($id) == NULL)
			return array('success' => false, 'error' => 'No player found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->slap($id)));
	}
	
	public function WSMethodSwap($server)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->swapteams()));
	}
	
	public function WSMethodVeto($server)
	{
		
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->veto()));
	}
	
	public function WSMethodWhois($server, $id)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		if(($player = ServerList::getServer($server)->getPlayer($id)) == NULL)
			return array('success' => false, 'error' => 'No player found');
		
		$data = array(	'name' => $player->name,
						'addr' => $player->addr,
						'level' => $player->level,
						'host' => gethostbyaddr($player->addr)
					);
		
		return array('success' => true, 'data' => $data);
	}
	
	public function WSMethodRcon($server, $command)
	{
		if(!ServerList::serverEnabled($server))
			return array('success' => false, 'error' => 'Server not found');
		
		return array('success' => Leelabot::boolString(ServerList::getServerRCon($server)->send($command)));
	}
	
	// IRC STUFF //
	
	public function IrcKick($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
				$kick = $cmd[2];
			else
				$kick = $cmd[1];
		
			if(isset($kick))
			{
				$target = Server::searchPlayer(trim($kick));
				
				if(!$target)
				{
					LeelaBotIrc::sendMessage("Unknown player");
				}
				elseif(is_array($target))
				{
					$players = array();
					foreach($target as $p)
						$players[] = Server::getPlayer($p)->name;
					LeelaBotIrc::sendMessage("Multiple players found : ".join(', ', $players));
				}
				else
				{
					$rcon->kick($target);
					LeelaBotIrc::sendMessage(Server::getPlayer($target)->name." was kicked.");
				}
			}
			else
			{
				LeelaBotIrc::sendMessage("Player name missing");
			}
		}
	}
	
	public function IrcKickAll($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
				$kick = $cmd[2];
			else
				$kick = $cmd[1];
		
			if(isset($kick))
			{
				$target = Server::searchPlayer(trim($kick));
				
				if(!$target)
				{
					LeelaBotIrc::sendMessage("Unknown player");
				}
				elseif(is_array($target))
				{
					$players = array();
					foreach($target as $p)
					{
						$rcon->kick($p);
						$players[] = Server::getPlayer($p)->name;
					}
					LeelaBotIrc::sendMessage(join(', ', $players)." are kicked.");
				}
				else
				{
					$rcon->kick($target);
					LeelaBotIrc::sendMessage(Server::getPlayer($target)->name." was kicked.");
				}
			}
			else
			{
				LeelaBotIrc::sendMessage("Player name missing");
			}
		}
	}
	
	public function IrcSlap($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
				$slap = $cmd[2];
			else
				$slap = $cmd[1];
		
			if(isset($slap))
			{
				$target = Server::searchPlayer(trim($slap));
				
				if(!$target)
				{
					LeelaBotIrc::sendMessage("Unknown player");
				}
				elseif(is_array($target))
				{
					$players = array();
					foreach($target as $p)
						$players[] = Server::getPlayer($p)->name;
					LeelaBotIrc::sendMessage("Multiple players found : ".join(', ', $players));
				}
				else
				{
					$rcon->slap($target);
					LeelaBotIrc::sendMessage(Server::getPlayer($target)->name." was slapped.");
				}
			}
			else
			{
				LeelaBotIrc::sendMessage("Player name missing");
			}
		}
	}
	
	public function IrcMute($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
				$mute = $cmd[2];
			else
				$mute = $cmd[1];
		
			if(isset($mute))
			{
				$target = Server::searchPlayer(trim($mute));
				
				if(!$target)
				{
					LeelaBotIrc::sendMessage("Unknown player");
				}
				elseif(is_array($target))
				{
					$players = array();
					foreach($target as $p)
						$players[] = Server::getPlayer($p)->name;
					LeelaBotIrc::sendMessage("Multiple players found : ".join(', ', $players));
				}
				else
				{
					$rcon->mute($target);
					LeelaBotIrc::sendMessage(Server::getPlayer($target)->name." was muted.");
				}
			}
			else
			{
				LeelaBotIrc::sendMessage("Player name missing");
			}
		}
	}
	
	public function IrcSay($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
			{
				$envoi = explode(' ', $message[2], 3);
				$i = 2;
			}
			else
			{
				$envoi = explode(' ', $message[2], 2);
				$i = 1;
			}
			
			if(isset($envoi[$i]))
				$rcon->say(LeelaBotIrc::standardize(rtrim($envoi[$i])));
		}
	}
	
	public function IrcBigtext($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
			{
				$envoi = explode(' ', $message[2], 3);
				$i = 2;
			}
			else
			{
				$envoi = explode(' ', $message[2], 2);
				$i = 1;
			}
			
			if(isset($envoi[$i]))
				$rcon->bigtext(LeelaBotIrc::standardize(rtrim($envoi[$i])));
		}
	}
	
	public function IrcMap($pseudo, $channel, $cmd, $message)
	{
		$serverlist = ServerList::getList();
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
				$map = $cmd[2];
			else
				$map = $cmd[1];
			
			if(isset($map))
			{
				if(in_array($map, $this->_mapUt4List))
					$rcon->map('"ut4_'.$map.'"');
				else
					$rcon->map('"'.$map.'"');
				
				LeelaBotIrc::sendMessage("Map changed !");
			}
			else
			{
				LeelaBotIrc::sendMessage("What's name of the map ?");
			}
		}
	}
	
	public function IrcNextMap($pseudo, $channel, $cmd, $message)
	{
		$serverlist = ServerList::getList();
		$server = LeelaBotIrc::nameOfServer($cmd[1]);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$serverlist = ServerList::getList();
			
			if(in_array($cmd[1], $serverlist))
				$map = $cmd[2];
			else
				$map = $cmd[1];
			
			if(isset($map))
			{
				if(in_array($map, $this->_mapUt4List))
					$rcon->set('g_nextmap "ut4_'.$map.'"');
				else
					$rcon->set('g_nextmap "'.$map.'"');
					
				LeelaBotIrc::sendMessage("Next map changed !");
			}
			else
			{
				LeelaBotIrc::sendMessage("What's name of the map ?");
			}
		}
	}
	
	public function IrcCyclemap($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1], FALSE);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$rcon->cyclemap();
		}
	}
	
	public function IrcRestart($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1], FALSE);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$rcon->restart();
		}
	}
	
	public function IrcReload($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[1], FALSE);
		
		if($server !== false)
		{
			$rcon = ServerList::getServerRCon($server);
			$rcon->reload();
		}
	}
}

$this->addPluginData(array(
'name' => 'adminbase',
'className' => 'PluginAdminBase',
'display' => 'Admin base plugin',
'autoload' => TRUE));
