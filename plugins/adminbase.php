<?php
/**
 * \file plugins/clientbase.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 1
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

	private $_mapUt4List = array('casa', 'kingdom', 'turnpike', 'abbey', 'prague', 'mandolin', 'uptown', 'algiers', 'austria', 'maya', 'tombs', 'elgin', 'oildepot', 'swim', 'harbortown', 'ramelle', 'toxic', 'sanc', 'riyadh', 'ambush', 'eagle', 'suburbs', 'crossing', 'subway', 'tunis', 'thingley');
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		$this->setCommandLevel('!kick', 10);
		
		/* NOTE : The command list is in alphabetical order */
	}
	
	/** Bigtext command. Send a message for all.
	 * This function is bound to the !bigtext command. He send a message for all, displayed on the center of screen like flag captures.
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
	
	/** Exec command. Execute an configuration.
	 * This function is bound to the !cfg command. It execute and .cfg file
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	
	public function CommandCfg($id, $command)
	{
		if(!empty($command[0]))
		{
			RCon::exec($command[0]);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Cyclemap command. Go to nextmap.
	 * This function is bound to the !cyclemap command. It changes the map, usually the following file cyclemap.txt.
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
	 * This function is bound to the !die command. Kill the bot
	 * 
	 * \param $id The game ID of the player who executed the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandDie($id, $command)
	{
		Rcon::bigtext('Shutting Down... By !');
		sleep(2);
		die();
	}
	
	/** Forceteam command. Force a player on the other team.
	 * This function is bound to the !force command. Force a player on the other team. Available teams : red, blue, spectator.
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
				if(in_array($command[1], array('spec', 'spect')))
					$command[1] = 'spectator';
				
				if(in_array($command[1], array('red', 'blue', 'spectator'))) 
					RCon::forceteam($target.' '.$command[1]);
				else
					Rcon::tell($id, 'Invalid parameter. Available teams : red, blue, spectator.');
			}
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
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
		if(!empty($command[0]))
		{
			$target = Server::searchPlayer($command[0]);
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
				RCon::kick($target);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
	
	/** Kick command. Kick players from the server.
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
			if(!$target)
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
	
	/** Take the list of players.
	 * This function is bound to the !list command. This function send the list of players with their id.
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
	
	/** mute command. Mute a player in the server.
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
				RCon::mute($target);
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
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
		{
			Rcon::tell($id, 'Missing parameters.');
		}
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
			RCon::say('"'.$text.'"');
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
		$players = Server::getPlayerList();
		$lastTeam = rand(Server::TEAM_BLUE, Server::TEAM_RED);
		
		shuffle($players);
		
		foreach($players as $player)
		{
			if($player->team != Server::TEAM_SPEC)
			{
				if($lastTeam == Server::TEAM_RED)
				{
					RCon::forceteam($player->id.' red');
					$lastTeam = Server::TEAM_BLUE;
				}
				elseif($lastTeam == Server::TEAM_BLUE)
				{
					RCon::forceteam($player->id.' blue');
					$lastTeam = Server::TEAM_RED;
				}
			}
		}
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
	
	
	/** Take information about an players
	 * This function is bound to the !whois command. It give name, ip, level, hote oh one player.
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
				$target = Server::getPlayer($target);
				Rcon::tell($id, 'Whois ^2$name ^3> IP : $addr, Level : $level, Host : $host', array('name' => $target->name, 'addr' => $target->addr, 'level' => $target->level, 'host' => gethostbyaddr($target->addr)));
			}
		}
		else
		{
			Rcon::tell($id, 'Missing parameters.');
		}
	}
}

$this->addPluginData(array(
'name' => 'adminbase',
'className' => 'PluginAdminBase',
'display' => 'Admin base plugin',
'autoload' => TRUE));
