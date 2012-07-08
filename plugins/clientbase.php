<?php
/**
 * \file plugins/clientbase.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \version 1.2
 * \brief Client base plugin for Leelabot. It allows to send !teams, !time, !nextmap, !help.
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
 * This file contains a lot of commands for clients on the server (people who don't have admin rights).
 * And optionally, it allows to have an automatic balancing of teams.
 * !teams = balance teams
 * !time = date & time
 * !next = return next map
 * !help = list of commands
 */

/**
 * \brief Plugin clientbase class.
 * This class contains the methods and properties needed by the clientbase plugin. It contains all the commands provided to standard clients on the server.
 */
class PluginClientBase extends Plugin
{
	private $_ClientUserinfoChangedIgnore = array();
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if($this->config)
		{
			$serverlist = ServerList::getList();
			
			// 1 config per server
			foreach($serverlist as $server)
			{
				if(isset($this->config[$server]['AutoTeams']))
					$this->config[$server]['AutoTeams'] = Leelabot::parseBool($this->config[$server]['AutoTeams']);
			
				if(!isset($this->config[$server]['CycleMapFile']) OR !file_exists($this->config[$server]['CycleMapFile']))
					$this->config[$server]['CycleMapFile'] = FALSE;
			}
		}
			
		//IRC commands level (0:all , 1:voice, 2:operator)
		if($this->_plugins->listenerExists('irc'))
		{
			$this->_plugins->setEventLevel('irc', 'status', 0);
			$this->_plugins->setEventLevel('irc', 'players', 0);
			$this->_plugins->setEventLevel('irc', 'about', 0);
		}
	}
	
	/** ClientUserinfo event. Perform team balance if needed.
	 * This function is bound to the ClientUserinfo event. It performs a team balance if the AutoTeams is active (it can be activated in the config).
	 * 
	 * \param $data The client data.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventClientUserinfoChanged($id, $data)
	{
		$player = Server::getPlayer($id);
		
		$servername =  Server::getName();
		
		// Only if is a team change userInfoChanged
		if($this->config[$servername]['AutoTeams'] && $data['t'] != $player->team)
		{
			if(!array_key_exists($servername, $this->_ClientUserinfoChangedIgnore))
				$this->_ClientUserinfoChangedIgnore[$servername] = array();
			
			// Only if _balance() haven't add this player in ignore list
			if(!in_array($id, $this->_ClientUserinfoChangedIgnore[$servername])) 
			{
				$teams_count = Server::getTeamCount();
				
				if($player->team == Server::TEAM_SPEC) // If he join the game
				{
					if($data['t'] == Server::TEAM_BLUE)
						$otherteam = Server::TEAM_RED;
					elseif($data['t'] == Server::TEAM_RED)
						$otherteam = Server::TEAM_BLUE;
					
					// if player haven't use auto join and go to wrong team
					if($teams_count[$data['t']] > $teams_count[$otherteam])
					{
						$teams = array(1 => 'red', 2 => 'blue', 3 => 'spectator');
						Rcon::forceteam($player->id, $teams[$otherteam]);
						Rcon::tell($player->id, 'Please use autojoin.');
						return TRUE;
					}
					
				}
				elseif($player->team != Server::TEAM_SPEC && $data['t'] != Server::TEAM_SPEC) // If he change team (and don't go to spectator)
				{
					if($player->team == Server::TEAM_BLUE)
						$otherteam = Server::TEAM_RED;
					elseif($player->team == Server::TEAM_RED)
						$otherteam = Server::TEAM_BLUE;
					
					// If the player wants to go to the team with the highest number of players.
					if($teams_count[$otherteam] >= $teams_count[$player->team])
					{
						// No balance and force player in his team
						$teams = array(1 => 'red', 2 => 'blue', 3 => 'spectator');
						Rcon::forceteam($player->id, $teams[$player->team]);
						Rcon::tell($player->id, 'Please stay in your team.');
						return TRUE; 			
					}
				}
				else // If few players of the same team go to spec, we need to balance.
				{
					// we need to update user info else _balance can't see difference and he don't make his job
					$player = Server::setPlayerKey($id, 'team', $data['t']);
					
					// balance !
					$this->_balance();
				}
			}
			else
			{
				unset($this->_ClientUserinfoChangedIgnore[$servername][$id]);
			}
		}
	}
	
	/** !time command. Get time.
	 * This function is the command !time. It get date and time.
	 * 
	 * \param $player The player who send the command.
	 * \param $command The command's parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandTime($player, $command)
	{
		RCon::tell($player, date($this->_main->intl->getDateTimeFormat()));
	}
	
	/** !help command. Get help and list of commands.
	 * This function is the command !help. It get list of commands and help.
	 * 
	 * \param $player The player who send the command.
	 * \param $command The command's parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandHelp($player, $command)
	{
		$list = Plugins::getCommandList();
		$player = Server::getPlayer($player);
		
		if(empty($command[0]))
		{
			foreach($list as $cmd => $level)
			{
				unset($list[$cmd]);
				if($level <= $player->level)
					$list['!'.$cmd] = $level;
			}
					
			$list = implode(', ', array_keys($list));
			Rcon::tell($player->id, 'List of commands : $list', array('list' => $list));
		}
		else
		{
			$cmdHelp = strtolower(str_replace('!', '', $command[0]));
			
			//need into translate files for example : #id help_nextmap #to Return next map.
			Rcon::tell($player->id, '$cmd: '.Locales::translate('help_'.$cmdHelp), array('cmd' => '!'.$cmdHelp), FALSE);
		}
	}
	
	/** !nextmap command. Return next map.
	 * This function is the command !next. Return next map.
	 * 
	 * \param $player The player who send the command.
	 * \param $command The command's parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandNext($player, $command)
	{
		$nextmap = Rcon::g_nextmap();
		$nextmap = explode('"', $nextmap);
		$nextmap = trim(str_replace('^7', '', $nextmap[3]));
		
		if($nextmap != '')
		{
			RCon::tell($player, 'Nextmap is : $0', array($nextmap));
			return TRUE;
		}
		else
		{
			$servername =  Server::getName();
			if($this->config[$servername]['CycleMapFile'] !== NULL)
			{
		    	$content = Server::fileGetContents($this->config[$servername]['CycleMapFile']);
		        
		        if($content !== FALSE)
		        {
			    	$tab_maps = explode("\n",$content);        
			    	$current_map = $server->serverInfo['mapname'];    
			    	$index = 0;
			        
			    	while ($index <= count($tab_maps) - 1)
					{
						if($current_map == $tab_maps[$index])
							break;
						else
							$index++;
						if($tab_maps[$index] == '{')
							for(;$tab_maps[$index-1] != '}';$index++);
					}
					
					$index++;
					
					if($tab_maps[$index] == '{')
						for(;$tab_maps[$index-1] != '}';$index++);
					
					if(isset($tab_maps[$index]))
						$nextmap = $tab_maps[$index];
					else
						$nextmap = $tab_maps[0];
						
					RCon::tell($player, 'Nextmap is : $0', array($nextmap));
					return TRUE;
				}
			}
		}
		
		RCon::tell($player, "We don't know the next map");
	}
	
	/** !teams command. Forces a team balance.
	 * This function is the command !teams. It forces the team balance.
	 * 
	 * \param $player The player who send the command.
	 * \param $command The command's parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandTeams($player, $command)
	{
		$this->_balance($player);
	}
	
	/** Balances teams.
	 * This function balances the teams according to the arrival order of the players. 
	 * It is executed on new connections and when a player changes team (if AutoTeams is enabled in plugin config).
	 * It only changes the team of new players. 
	 * 
	 * \param $player The player who send the command. (if is player has send command)
	 * 	 
	 * \return Nothing.
	 */
	private function _balance($player = null)
	{
		//We take player count of each team.
		$teams_count = Server::getTeamCount();
		
		if($teams_count[Server::TEAM_RED] >= ($teams_count[Server::TEAM_BLUE]+2)) // If there are more players in red team than in blue team.
		{
			$too_many_player = floor(($teams_count[Server::TEAM_RED]-$teams_count[Server::TEAM_BLUE])/2);
			$src_team = Server::TEAM_RED;
			$dest_team = strtolower(Server::getTeamName(Server::TEAM_BLUE));
			$balance = TRUE;
		}
		elseif($teams_count[Server::TEAM_BLUE] >= ($teams_count[Server::TEAM_RED]+2)) // If there are more players in blue team than in red team.
		{
			$too_many_player = floor(($teams_count[Server::TEAM_BLUE]-$teams_count[Server::TEAM_RED])/2);
			$src_team = Server::TEAM_BLUE;
			$dest_team = strtolower(Server::getTeamName(Server::TEAM_RED));
			$balance = TRUE;
		}
		else
			$balance = FALSE;
		
		//If the teams are unbalanced
		if($balance)
		{
			//We get player list
			$players = Server::getPlayerList($src_team);
			$last = array();
			
			foreach($players as $player)
				$last[$player->time] = $player->id;
			
			//We sorts the players of the team by time spent on the server.
			krsort($last);
			
			//Processing loop
			while($too_many_player > 0)
			{
				//We take the last player of the team
				$player = array_shift($last);
				
				//Add on ClientUserinfoChanged ignore list
				$this->_ClientUserinfoChangedIgnore[$player] = $player;
				
				//We change the team of the player
				RCon::forceteam($player, $dest_team);
				
				--$too_many_player;
			}
			
			RCon::say('Teams are now balanced');
		}
		else
		{
			if($player !== NULL)
				RCon::tell($player, 'Teams are already balanced');
		}
		
	}
	
	/** Balances teams.
	 * This function balances the teams according to the arrival order of the players. 
	 * It is executed on new connections and when a player changes team (if AutoTeams is enabled in plugin config).
	 * It only changes the team of new players. 
	 * 
	 * \param $server The server that has requested the balance.
	 * 	 
	 * \return Nothing.
	 */
	public function WSMethodBalanceTeams($server)
	{
		$this->_balance();
	}
	
	public function WAPageIndex()
	{
		return "
		<h1>Clientbase plugin</h1>
		
		";
	}
	
	
	public function IrcAbout($pseudo, $channel, $cmd, $message)
	{
		LeelaBotIrc::sendMessage("Leelabot, created by linkboss and SRWieZ - ".LeelaBot::VERSION." - leelabot.com");
	}
	
	public function IrcInfos($pseudo, $channel, $cmd, $message)
	{
		$serverlist = ServerList::getList();
		$actual = Server::getName();
	
		if(isset($cmd[1]) && in_array($cmd[1], $serverlist))
		{
			Server::setServer($this->_main->servers[$cmd[1]]);
			$this->_printServerInfo($cmd[1]);
		}
		else
		{
			foreach($serverlist as $server)
			{
				Server::setServer($this->_main->servers[$server]);
				$this->_printServerInfo($server);
			}
		}
	
		Server::setServer($this->_main->servers[$actual]);
	}
	
	private function _printServerInfo($server)
	{
		$serverinfo = Server::getServer()->serverInfo;
		LeelaBotIrc::sendMessage("\002".LeelaBotIrc::rmColor($serverinfo['sv_hostname'])."\002 : ".$serverinfo['mapname']." | ".Server::getGametype($serverinfo['g_gametype'])." | ".count(Server::getPlayerList())." players");
	}
	
	public function IrcPlayers($pseudo, $channel, $cmd, $message)
	{
		$serverlist = ServerList::getList();
		
		$actual = Server::getName();
	
		if(isset($cmd[1]) && in_array($cmd[1], $serverlist))
		{
			Server::setServer($this->_main->servers[$cmd[1]]);
			$this->_printPlayers($cmd[1]);
		}
		else
		{
			foreach($serverlist as $server)
			{
				Server::setServer($this->_main->servers[$server]);
				$this->_printPlayers($server);
			}
		}
	
		Server::setServer($this->_main->servers[$actual]);
	}
	
	private function _printPlayers($server)
	{
		$playerlist = array();
		$nbplayers = 0;
		
		$serverinfo = Server::getServer()->serverInfo;
		
		foreach(Server::getPlayerList() as $curPlayer)
		{
			//Gestion de la couleur en fonction de l'équipe
			if($serverinfo['g_gametype'] != '0')
			{
				if($curPlayer->team == 1)
					$color = "\00304";
				elseif($curPlayer->team == 2)
					$color = "\00302";
				elseif($curPlayer->team == 3)
					$color = "\00314";
			}
			else
				$color = "\00308";
			$playerlist[] = "\002".$color.$curPlayer->name."\017";
			++$nbplayers;
		}
		
		if($nbplayers >0) LeelaBotIrc::sendMessage("\002".LeelaBotIrc::rmColor($serverinfo['sv_hostname'])." :\002  : ".join(', ', $playerlist));
		else LeelaBotIrc::sendMessage("\002".LeelaBotIrc::rmColor($serverinfo['sv_hostname'])." :\002 No one.");
	}
}

$this->addPluginData(array(
'name' => 'clientbase',
'className' => 'PluginClientBase',
'display' => 'Clienbase Plugin',
'autoload' => TRUE));
