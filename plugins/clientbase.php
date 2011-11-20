<?php
/**
 * \file plugins/clientbase.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \version 0.9
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
 * !nextmap = return next map
 * !help = list of commands
 */

/**
 * \brief Plugin clientbase class.
 * This class contains the methods and properties needed by the clientbase plugin. It contains all the commands provided to standard clients on the server.
 */
class PluginClientBase extends Plugin
{
	private $_autoteams = TRUE; // AutoTeams toggle.
	private $_cyclemapfile = NULL; // Cyclemap file destination.
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if($this->config)
		{
			if(isset($this->config['AutoTeams']))
				$this->_autoteams = Leelabot::parseBool($this->config['AutoTeams']);
			
			if(isset($this->config['CycleMapFile']))
				$this->_cyclemapfile = $this->config['CycleMapFile'];
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
		if($this->_autoteams)
		{
			Server::getPlayer($id)->team = $data['t'];
			$this->_balance();
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
				if($level > $player->level)
					unset($list[$cmd]);
					
			$list = implode(', ', array_keys($list));
			Rcon::tell($player->id, 'List of commands : $list', array('list' => $list));
		}
		else
		{
			$cmdHelp = strtolower(str_replace('!', '', $command[0]));
			
			if(isset($this->_main->config['Commands']['Help']))
			{
				if(isset($this->_main->config['Commands']['Help'][$cmdHelp]))
				{
					Rcon::tell($player->id, '$cmd : $help', array('cmd' => '!'.$cmdHelp, 'help' => $this->_main->config['Commands']['Help'][$cmdHelp]));
					return true;
				}
			}
			
			Rcon::tell($player->id, 'Command $cmd have not description.', array('cmd' => '!'.$cmdHelp));
		}
	}
	
	/** !nextmap command. Return next map.
	 * This function is the command !nextmap. Return next map.
	 * 
	 * \param $player The player who send the command.
	 * \param $command The command's parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandNextmap($player, $command)
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
			if($this->_cyclemapfile !== NULL)
			{
		    	$content = Server::fileGetContents($this->_cyclemapfile);
		        
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
		// TODO
	}
}

return $this->initPlugin(array(
'name' => 'clientbase',
'className' => 'PluginClientBase',
'autoload' => TRUE));
