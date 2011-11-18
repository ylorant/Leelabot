<?php
/**
 * \file plugins/stats.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \version 0.1
 * \brief Statistics plugin for Leelabot. It allows to have game stats for each player.
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
 * This file contains a complete statistics system for players in the game.
 * 
 * Commands in game :
 * !stats => get stats to player
 * !statsreset => resets stats(for admins)
 * !awards => get current awards 
 */

/*
Infos sur les kills/hits/flags dans le log :
Numéros des types de hits dans le log :
0 = HS					1 = Helmet				2 = Torso
3 = Kevlar				4 = Arms				5 = Legs
6 = Body
Numéros des types de Flags dans le log :
1 = Flag retourné		2 = Capture flag		0 = Flag laché
Numéros des types de Kills dans le log :
1 = Water				3 = Lava				6 = Lemming
7 = Suicide (/kill)		9 = Hurt				10 = Change team
12 = knife				13 = lancer de knife	14 = Berreta
15 = DE					16 = Spas				17 = UMP
18 = MP5K				19 = lr300				20 = G36
21 = PSG1				22 = HK69 explosion		23 = Bled
24 = Boot O'f Passion	25 = HE					28 = SR8
30 = AK103				31 = HE Sploded			32 = Slap
35 = Negev				34 = Nuke				37 = HK69 hit
38 = M4					40 = Stomped
*/


/**
 * \brief Plugin stats class.
 * This class contains the methods and properties needed by the stats plugin. It contains all the statistics system to players on the server.
 */
class PluginStats extends Plugin
{
	private $_awards = TRUE; // Awards toggle.
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		if(!isset($this->config))
		{
			$this->config = array();
		}
		
		// What kind of stats will be displayed
		if(isset($this->config['ShowStats']))
			$this->config['ShowStats'] = explode(',', $this->config['ShowStats']);
		else
			$this->config['ShowStats'] = array('hits', 'kills', 'deaths', 'streaks', 'heads', 'caps', 'ratio');
		
		// What kind of awards will be displayed
		if(isset($this->config['ShowAwards']))
			$this->config['ShowAwards'] = explode(',', $this->config['ShowAwards']);
		else
			$this->config['ShowAwards'] = array('hits', 'kills', 'deaths', 'streaks', 'heads', 'caps', 'ratio');
			
		// Display flag captures on top
		if(isset($this->config['DisplayCaps']))
			$this->config['DisplayCaps'] = Leelabot::parseBool($this->config['DisplayCaps']);
		else
			$this->config['DisplayCaps'] = TRUE;
			
		// Display headshots on top (every 5 heads)
		if(isset($this->config['DisplayHeads']))
			$this->config['DisplayHeads'] = Leelabot::parseBool($this->config['DisplayHeads']);
		else
			$this->config['DisplayHeads'] = TRUE;
			
		// Display streaks on top (only if is the best streaks)
		if(isset($this->config['DisplayStreaks']))
			$this->config['DisplayStreaks'] = Leelabot::parseBool($this->config['DisplayStreaks']);
		else
			$this->config['DisplayStreaks'] = TRUE;
			
		// Default verbosity for the players
		if(isset($this->config['StatsVerbosity']) && in_array($this->config['StatsVerbosity'], array('0','1','2','3')))
			$this->config['StatsVerbosity'] = $this->config['StatsVerbosity']; // stupid line :D (but usefull)
		else
			$this->config['StatsVerbosity'] = 2;
			
		// Allow player to change their verbosity 
		if(isset($this->config['AllowPlayerVerbosity']))
			$this->config['AllowPlayerVerbosity'] = Leelabot::parseBool($this->config['AllowPlayerVerbosity']);
		else
			$this->config['AllowPlayerVerbosity'] = FALSE;
	}
	
	public function disableStatsReset()
	{
		$this->_disableStatsReset++;
	}
	
	public function enableStatsReset()
	{
		$this->_disableStatsReset--;
	}
	
	public function SrvEventStartupGame()
	{
		//Stats of players
		Server::set('stats', array());
		
		//Stats config of players
		Server::set('statsConfig', array());
		
		//Awards of game
		Server::set('awards', array('hits' => array(NULL,0), 'kills' => array(NULL,0), 'deaths' => array(NULL,0), 'streaks' => array(NULL,0), 'heads' => array(NULL,0), 'caps' => array(NULL,0), 'ratio' => array(NULL,0)));
		
		//Ratio list
		Server::set('ratioList', array());
		
		//If other plugin want to disable stats reset
		Server::set('disableStatsReset', 0);
	}
	
	public function SrvEventInitGame($serverinfo)
	{
		//And Finally stats to zero except if the other plugins don't want.
		if(!Server::get('disableStatsReset'))
			$this->_statsInit();
	}
	
	
	public function SrvEventExit()
	{
		$_stats = Server::get('stats');
		
		if($this->_main->clientMsg != '!restart')
		{
			foreach($_stats as $id => $curent)
			{
				if($this->_statsConfig[$id]['verbosity'] >= 1)
					$this->_showStatsMsg($id);
			}
			
			if(!empty($this->showAwards))
				$this->_showAwardsMsg();
		}
		
		//Stats to zero except if the other plugins don't want.
		if(!Server::get('disableStatsReset'))
			$this->_statsInit();
	}
	
	//Event serveur : Connect (On initialise les stats pour ce joueur)
	public function SrvEventClientConnect($id)
	{
		$_stats = Server::get('stats');
		$_statsConfig = Server::get('statsConfig');
		$_ratioList = Server::get('ratioList');
		
		$_stats[$id] = array();
		$_stats[$id]['hits'] = 0;
		$_stats[$id]['kills'] = 0;
		$_stats[$id]['deaths'] = 0;
		$_stats[$id]['streaks'] = 0;
		$_stats[$id]['curstreak'] = 0;
		$_stats[$id]['heads'] = 0;
		$_stats[$id]['caps'] = 0;
		$_stats[$id]['ratio'] = 0;
		
		$_ratioList[$id] = 0;
		
		$_statsConfig[$id] = array();
		$_statsConfig[$id]['verbosity'] = $this->config['StatsVerbosity'];
		
		Server::set('stats', $_stats);
		Server::set('statsConfig', $_statsConfig);
		Server::set('ratioList', $_ratioList);
	}
	
	//Event serveur : Disconnect (on détruit les stats et les paramètres pour ce joueur) et on recalcul les awards
	public function SrvEventClientDisconnect($id)
	{
		$_stats = Server::get('stats');
		$_awards = Server::get('awards');
		$_ratioList = Server::get('ratioList');
		
		unset($_stats[$id]);
		unset($_ratioList[$id]);
		
		$awards = array('hits' => array(NULL,0), 'kills' => array(NULL,0), 'deaths' => array(NULL,0), 'streaks' => array(NULL,0), 'heads' => array(NULL,0), 'caps' => array(NULL,0), 'ratio' => array(NULL,0));
		
		$stat = array('hits', 'kills', 'deaths', 'streaks', 'heads', 'caps', 'ratio');
		
		foreach($_stats as $id => $player)
		{
			foreach($stat as $statname)
			{
				if($player[$statname] > $awards[$statname][1])
				{
					$awards[$statname][0] = $id;
					$awards[$statname][1] = $player[$statname];
				}
			}
		}
		
		$_awards = $awards;
		
		if(count($_ratioList))
		{
			arsort($_ratioList);
			$keys = array_keys($_ratioList);
			
			$_awards['ratio'][1] = $_ratioList[$keys[0]];
			$_awards['ratio'][0] = $keys[0];
		}
		
		Server::set('stats', $_stats);
		Server::set('awards', $_awards);
		Server::set('ratioList', $_ratioList);
	}
	
	public function SrvEventHit($hit)
	{
		$player0 = Server::getPlayer($hit[0]);
		$player1 = Server::getPlayer($hit[1]);
		
		// Si les 2 joueurs sont pas dans la même équipe.
		if($player0->team != $player1->team)
		{
			$_stats = Server::get('stats');
			$_awards = Server::get('awards');
			
			$_stats[$hit[1]]['hits']++;
			
			if($hit[2] <= 1)
			{
				$_stats[$hit[1]]['heads']++;
				
				if($this->config['DisplayHeads'] AND ($_stats[$hit[1]]['heads']/5) == 0)
				{
					if($player1->team == 1) $color = '^1';
					elseif($player1->team == 2) $color = '^4';
					Rcon::topMessage('^3Headshots : $playercolor$playername ^2$heads', array('playercolor' => $color, 'playername' => $player1->name, 'heads' => $_stats[$hit[1]]['heads']));
				}
			
				if($_stats[$hit[1]]['heads'] > $_awards['heads'][1])
				{
					$_awards['heads'][0] = $hit[1];
					$_awards['heads'][1] = $_stats[$hit[1]]['heads'];
				}
			}
			
			//Gestion des awards
			if($_stats[$hit[1]]['hits'] > $_awards['hits'][1])
			{
				$_awards['hits'][0] = $hit[1];
				$_awards['hits'][1] = $_stats[$hit[1]]['hits'];
			}
		
			Server::set('awards', $_awards);
			Server::set('stats', $_stats);
		}
	}
	
	//Event serveur : Kill (on gère les kills avec ajout des kills-deaths en fonction du type de kill)
	public function SrvEventKill($kill)
	{
		$_stats = Server::get('stats');
		
		//Switch d'analyse du type de mort
		switch($kill[2])
		{
			//Tous les kills n'impliquant qu'une personne (slapped, nuked, lemming...), on ne rajoute qu'une death
			case '1':
			case '3':
			case '6':
			case '7':
			case '9':
			case '31':
			case '32':
			case '34':
				$_stats[$kill[1]]['deaths']++;
				break;
			//Au changement d'équipe, on ne fait rien
			case '10':
				break;
			//Le reste, on ajoute un kill au tueur, puis une death au mort
			default:
				$player0 = Server::getPlayer($kill[0]);
				$player1 = Server::getPlayer($kill[1]);
		
				// Si les 2 joueurs sont pas dans la même équipe.
				if($player0->team != $player1->team)
				{
					$_awards = Server::get('awards');
					$_ratioList = Server::get('ratioList');
					$_statsConfig = Server::get('statsConfig');
					
					$_stats[$kill[0]]['kills']++;
					$_stats[$kill[0]]['curstreak']++;
					if($_stats[$kill[0]]['curstreak'] > $_stats[$kill[0]]['streaks'])
						$_stats[$kill[0]]['streaks'] = $_stats[$kill[0]]['curstreak'];
					$_stats[$kill[1]]['deaths']++;
					$_stats[$kill[1]]['curstreak'] = 0;
					
					//Gestion des awards
					if($_stats[$kill[0]]['kills'] > $_awards['kills'][1])
					{
						$_awards['kills'][0] = $kill[0];
						$_awards['kills'][1] = $_stats[$kill[0]]['kills'];
					}
					if($_stats[$kill[0]]['streaks'] > $_awards['streaks'][1])
					{
						$_awards['streaks'][0] = $kill[0];
						$_awards['streaks'][1] = $_stats[$kill[0]]['streaks'];
				
						if($this->config['DisplayStreaks'])
						{
							if($player0->team == 1) $color = '^1';
							elseif($player0->team == 2) $color = '^4';
							Rcon::topMessage('^3New Streaks : $playercolor$playername ^2$streaks', array('playercolor' => $color, 'playername' => $player0->name, 'streaks' => $_stats[$kill[0]]['streaks']));
						}
					}
					
					if($_stats[$kill[1]]['deaths'] > $_awards['deaths'][1])
					{
						$_awards['deaths'][0] = $kill[1];
						$_awards['deaths'][1] = $_stats[$kill[1]]['deaths'];
					}
						
					
					if($_stats[$kill[0]]['deaths'] == 0)
						$ratio = $_stats[$kill[0]]['kills'];
					else
						$ratio = $_stats[$kill[0]]['kills'] / $_stats[$kill[0]]['deaths'];
						
					if($_stats[$kill[1]]['deaths'] == 0)
						$dratio = $_stats[$kill[1]]['kills'];
					else
						$dratio = $_stats[$kill[1]]['kills'] / $_stats[$kill[1]]['deaths'];
					
					$_ratioList[$kill[0]] = $ratio;
					$_ratioList[$kill[1]] = $dratio;
					
					arsort($_ratioList);
					$keys = array_keys($_ratioList);
					
					$_awards['ratio'][1] = $_ratioList[$keys[0]];
					$_awards['ratio'][0] = $keys[0];
					
					//Affichage des stats ou pas selon 
					if($_statsConfig[$kill[1]]['verbosity'] >= 2)
						$this->_showStatsMsg($kill[1]);
					if($_statsConfig[$kill[0]]['verbosity'] >= 3)
						$this->_showStatsMsg($kill[0]);
						
					Server::set('awards', $_awards);
					Server::set('ratioList', $_ratioList);
				}
				
			break;
		}
		
		Server::set('stats', $_stats);
	}
	
	//Event serveur : Flag (si il capture, on ajoute 1 au compteur de capture du joueur)
	public function SrvEventFlag($flag)
	{
		if(Server::getServer()->serverInfo['g_gametype'] == 7)
		{
			if($flag[1] == 2) //Si c'est une capture
			{
				$_stats = Server::get('stats');
				$_awards = Server::get('awards');
				
				$_stats[$flag[0]]['caps']++;
				
				if($this->config['DisplayCaps'])
				{
					$player = Server::getPlayer($flag[0]);
					
					if($player->team == 1) $color = '^1';
					elseif($player->team == 2) $color = '^4';
					Rcon::topMessage(' $playercolor$playername : ^2$caps ^3caps', array('playercolor' => $color, 'playername' => $player->name, 'caps' => $_stats[$flag[0]]['caps']));
				}
				
				//Gestion des awards
				if($_stats[$flag[0]]['caps'] > $_awards['caps'][1])
				{
					$_awards['caps'][0] = $flag[0];
					$_awards['caps'][1] = $_stats[$flag[0]]['caps'];
				}
				
				Server::set('awards', $_awards);
				Server::set('stats', $_stats);
			}
		}
	}
	
	//Event client : !stats (on affiche juste les stats du joueur ayant appelé la commande)
	public function CommandStats($id, $cmd)
	{
		$this->_showStatsMsg($id);
	}
	
	//Event client : !awards (on affiche juste les awards)
	public function CommandAwards($id, $cmd)
	{
		$this->_showAwardsMsg($id);
	}
	
	//Event client : !statsreset (On réinitialise les stats)
	public function CommandStatsReset($id, $cmd)
	{
		$this->_statsInit();
	}
	
	
	//Event client : !statscfg (on configure les stats selon les différents paramètres)
	public function CommandStatsCfg($id, $cmd)
	{
		if($cmd[0])
		{
			$_statsConfig = Server::get('statsConfig');
			
			switch($cmd[0])
			{
				case 'verbosity':
				case 'v':
					if(is_numeric($cmd[1]) && $cmd[1] <= 3 && $cmd[1] >= 0 )
						$this->_statsConfig[$id]['verbosity'] = $cmd[1];
					else
						Rcon::tell($id, 'You must enter a number between 0 and 3.');
					break;
			}
			
			Server::set('statsConfig', $_statsConfig);
		}
		else
		{
			Rcon::tell($id, 'You must enter the name of the configuration.');
		}
	}
	
	private function _showAwardsMsg($player = NULL)
	{
		$buffer = array();
		
		$_awards = Server::get('awards');
		
		//On affiche l'award uniquement si il est activé dans la config
		foreach($this->config['ShowAwards'] as $award)
		{
			if(in_array($award, $this->config['ShowAwards']) && ($award != 'caps' || Server::getServer()->serverInfo['g_gametype'] == 7)) //On affiche les hits uniquement si la config des stats le permet
			{
				if($_awards[$award][0] !== NULL)
					$buffer[] = ucfirst($award).' : ^2'.Server::getPlayer($_awards[$award][0])->name;
				else
					$buffer[] = ucfirst($award).' : ^7Personne';
			}
		}
		
		if($player === NULL)
			Rcon::say('Awards : $awards', array('awards' => join('^3 - ', $buffer)));
		else
			Rcon::tell($player, 'Awards : $awards', array('awards' => join('^3 - ', $buffer)));
	}
	
	//Affiche le message de statistiques
	private function _showStatsMsg($user, $admin = NULL)
	{
		$buffer = array();
		
		$_stats = Server::get('stats');
		$_awards = Server::get('awards');
		
		//Gestion du ratio pour éviter la division par zéro
		if($_stats[$user]['deaths'] != 0)
			$ratio = $_stats[$user]['kills'] / $_stats[$user]['deaths'];
		else
			$ratio = $_stats[$user]['kills'];
		
		$aratio = round($_awards['ratio'][1], 2);
		
		//Gestion du ratio (changement de couleur selon le ratio)
		$ratio = round($ratio,2);
		if($ratio >=1)
			$ratioColor = '^2';
		else
			$ratioColor = '^1';
		
		//Gestion des awards (plus précisément de la couleur en cas d'award ou non)
		foreach($this->config['ShowStats'] as $stat)
		{
			if(in_array($stat, $this->config['ShowStats']))
			{
				if($_awards[$stat][0] == $user && in_array($stat, $this->config['ShowAwards']))
					$statColor = '^6';
				else
					$statColor = '^3';
			
				if(in_array($stat, $this->config['ShowAwards']))
					$statAward = '^4/'.$_awards[$stat][1];
				else
					$statAward = '';
				if($stat != 'ratio' && ($stat != 'caps' || Server::getServer()->serverInfo['g_gametype'] == 7))
					$buffer[] = $statColor.ucfirst($stat).' : ^2'.$_stats[$user][$stat].$statAward;
				elseif($stat != 'caps' || Server::getServer()->serverInfo['g_gametype'] == 7)
					$buffer[] = $statColor.ucfirst($stat).' : '.$ratioColor.$ratio.$statAward;
			}
		}
		
		if($admin !== NULL) $user = $admin;
		
		//On affiche enfin les stats
		Rcon::tell($user, '$stats', array('stats' => join('^3 - ', $buffer)));
	}
	
	//Stats to zero ...
	private function _statsInit()
	{
		$_stats = Server::get('stats');
		$_awards = Server::get('awards');
		
		//... of every players.
		foreach($_stats as &$curStat)
		{
			$curStat['hits'] = 0;
			$curStat['kills'] = 0;
			$curStat['deaths'] = 0;
			$curStat['ratio'] = 0;
			$curStat['caps'] = 0;
			$curStat['streaks'] = 0;
			$curStat['heads'] = 0;
		}
		
		//Awards to zero
		$_awards = array('hits' => array(NULL,0), 'kills' => array(NULL,0), 'deaths' => array(NULL,0), 'streaks' => array(NULL,0), 'heads' => array(NULL,0), 'caps' => array(NULL,0), 'ratio' => array(NULL,0));
		
		Server::set('stats', $_stats);
		Server::set('awards', $_awards);
	}
}

return $this->initPlugin(array(
'name' => 'stats',
'className' => 'PluginStats',
'autoload' => TRUE));