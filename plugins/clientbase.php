<?php
/**
 * \file plugins/clientbase.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \version 0.1
 * \brief Client base plugin for Leelabot. It allows to send !teams (balance teams). (at the moment)
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
 * !teams = balance teams
 * !time = date & time
 * !nextmap = return next map
 */

/**
 * \brief Plugin clientbase class.
 * This class contains the methods and properties needed by the clientbase plugin. It contains all the commands provided to standard clients on the server.
 */
class PluginClientBase extends Plugin
{
	private $_autoteams = FALSE; ///< AutoTeams toggle.
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config). It sets the AutoTeams toggle.
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if(isset($this->_main->config['Plugin']['Clientbase']) && isset($this->_main->config['Plugin']['Clientbase']['AutoTeams']) && Leelabot::parseBool($this->_main->config['Plugin']['Clientbase']['AutoTeams']))
			$this->_autoteams = TRUE;
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
		RCon::tell($player, date($this->_main->Intl->getDateTimeFormat()));
	}
	
	/** !time command. Get time.
	 * This function is the command !time. It get date and time.
	 * 
	 * \param $player The player who send the command.
	 * \param $command The command's parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandNextmap($player, $command)
	{
		RCon::tell($player, 'Nextmap is : $0', array(Server::getServer()->serverinfo['g_nextmap']));
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
}

return $this->initPlugin(array(
'name' => 'clientbase',
'className' => 'PluginClientBase',
'autoload' => TRUE));
