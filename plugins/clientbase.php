<?php
/**
 * \file plugins/clientbase.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \version 0.1
 * \brief Plugin for Leelabot. He allow to send !teams (balance teams). (for the moment)
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
 * This file contain a lot of commands to client on server.
 * !teams = balance teams
 * !time = date & time
 * !nextmap = return nextmap 
 */
 
class PluginClientBase extends Plugin
{
	private $_autoteams = false;
	
	public function init()
	{
		// nothing..
	}
	
	public function SrvEventClientUserinfo($data)
	{
		if($this->_autoteams) $this->balance();
	}
	
	public function CommandTeams($player, $command)
	{
		$this->balance($player);
	}
	
	/** Balance teams
	 * This function balance the teams according to the order of arrival of the players. 
	 * He is executed in the new connections and when a player changes team (if autoteams is enabled on plugin config).
	 * He changes the team of only new players. 
	 * 
	 * \return nothing
	 * 
	 */
	private function balance($player = null)
	{
		// We take player count of each team.
		$teams_count = Server::getTeamCount();
		
		if($teams_count[Server::TEAM_RED] >= ($teams_count[Server::TEAM_BLUE]+2)) // If there are more players in red team than in blue team.
		{
			$too_many_player = floor(($teams_count[Server::TEAM_RED]-$teams_count[Server::TEAM_BLUE])/2);
			$teams_plus = Server::TEAM_RED;
			$teams_minus = Server::TEAM_BLUE;
			$balance = true;
		}
		elseif($teams_count[Server::TEAM_BLUE] >= ($teams_count[Server::TEAM_RED]+2)) // If there are more players in blue team than in red team.
		{
			$too_many_player = floor(($teams_count[Server::TEAM_BLUE]-$teams_count[Server::TEAM_RED])/2);
			$teams_plus = Server::TEAM_BLUE;
			$teams_minus = Server::TEAM_RED;
			$balance = true;
		}
		else // Else..
		{
			$balance = false;
		}
		
		// If the teams are unbalanced
		if($balance)
		{
			// Array for forceteam
			$teams_array = array('', 'red', 'blue');
			
			// We take player list
			$players = Server::getPlayerList();
			$last = array();
			
			foreach($players as $player)
			{
				if($player->team == $teams_plus)
					$last[$player->time] = $player->id];
			}
			
			// We sorts the players of this team compared to the time spend on the server.
			krsort($last);
			
			// Processing loop
			while($too_many_player > 0)
			{
				// We take the last player of this team
				$player = array_shift($last);
				
				// We change the team of this player
				RCon::forceteam($player, $teams_array[$teams_minus]);
				
				--$too_many_player;
			}
			
			RCon::say('Teams are now balanced!');
		}
		else
		{
			if($player !== null) RCon::tell($player, 'Teams are already balanced !');
		}
		
	}
}

return $this->initPlugin(array(
'name' => 'clientbase',
'className' => 'PluginClientBase',
'autoload' => TRUE));
