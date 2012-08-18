<?php
/**
 * \file plugins/stats.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \version 1.2
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
		// What kind of stats will be displayed
		if(isset($this->config['ShowStats']))
		{
			if(!is_array($this->config['ShowStats'])) // Only if is the first load of plugin
				$this->config['ShowStats'] = explode(',', $this->config['ShowStats']);
		}
		else
			$this->config['ShowStats'] = array('hits', 'kills', 'deaths', 'streaks', 'heads', 'caps', 'ratio', 'round');
		
		// What kind of awards will be displayed
		if(isset($this->config['ShowAwards']))
		{
			if(!is_array($this->config['ShowAwards'])) // Only if is the first load of plugin
				$this->config['ShowAwards'] = explode(',', $this->config['ShowAwards']);
		}
		else
			$this->config['ShowAwards'] = array('hits', 'kills', 'deaths', 'streaks', 'heads', 'caps', 'ratio', 'round');
			
		// Display flag captures on top
		if(isset($this->config['DisplayCaps']))
		{
			if(!is_bool($this->config['DisplayCaps'])) // Only if is the first load of plugin
				$this->config['DisplayCaps'] = Leelabot::parseBool($this->config['DisplayCaps']);
		}
		else
			$this->config['DisplayCaps'] = TRUE;
			
		// Display headshots on top (every 5 heads)
		if(isset($this->config['DisplayHeads']))
		{
			if(!is_bool($this->config['DisplayHeads'])) // Only if is the first load of plugin
				$this->config['DisplayHeads'] = Leelabot::parseBool($this->config['DisplayHeads']);
		}
		else
			$this->config['DisplayHeads'] = TRUE;
			
		// Display streaks on top (only if is the best streaks)
		if(isset($this->config['DisplayStreaks']))
		{
			if(!is_bool($this->config['DisplayStreaks'])) // Only if is the first load of plugin
				$this->config['DisplayStreaks'] = Leelabot::parseBool($this->config['DisplayStreaks']);
		}
		else
			$this->config['DisplayStreaks'] = TRUE;
			
		// Default verbosity for the players and only if is the first load of plugin
		if(!(isset($this->config['StatsVerbosity']) && is_numeric($this->config['StatsVerbosity']) && in_array(intval($this->config['StatsVerbosity']), array(0,1,2,3))))
			$this->config['StatsVerbosity'] = 2;
		
		
		// Allow player to change their verbosity 
		if(isset($this->config['AllowPlayerVerbosity']))
		{
			if(!is_bool($this->config['AllowPlayerVerbosity'])) // Only if is the first load of plugin
				$this->config['AllowPlayerVerbosity'] = Leelabot::parseBool($this->config['AllowPlayerVerbosity']);
		}
		else
			$this->config['AllowPlayerVerbosity'] = FALSE;
			
		//IRC commands level (0:all , 1:voice, 2:operator)
		if($this->_plugins->listenerExists('irc'))
		{
			$this->_plugins->setEventLevel('irc', 'stats', 1);
			$this->_plugins->setEventLevel('irc', 'awards', 0);
		}
			
		//Adding event listener
		$this->_plugins->addEventListener('stats', 'Stats');
			
		// We browse all servers for variables initialization
		$servers = ServerList::getList();
		foreach($servers as $serv)
		{
			// Take server instance
			$server = ServerList::getServer($serv);
		
			// Variables needed by stats plugin
			$this->_initVars($server);
			
			// Initialize players variables
			$_stats = $server->get('stats');
			$_statsConfig = $server->get('statsConfig');
			$_ratioList = $server->get('ratioList');
		
			$players = array_keys($server->getPlayerList());
			foreach($players as $id)
			{
				$_stats[$id] = array(
					'hits' => 0,
					'kills' => 0,
					'deaths' => 0,
					'streaks' => 0,
					'curstreak' => 0,
					'heads' => 0,
					'caps' => 0,
					'ratio' => 0,
					'round' => 0);
				$_ratioList[$id] = 0;
				$_statsConfig[$id] = array('verbosity' => $this->config['StatsVerbosity']);
			}
				
			$server->set('stats', $_stats);
			$server->set('statsConfig', $_statsConfig);
			$server->set('ratioList', $_ratioList);
		}
	}
	
	public function destroy()
	{
		// We browse all servers
		$servers = ServerList::getList();
		foreach($servers as $serv)
		{
			$server = ServerList::getServer($serv);
			$rcon = ServerList::getServerRCon($serv);
			
			$rcon->say("Stats stopped by plugin unload.");
			
			$server->set('stats', NULL);
			$server->set('statsConfig', NULL);
			$server->set('awards', NULL);
			$server->set('ratioList', NULL);
			$server->set('disableStatsReset', NULL);
		}
	}
	
	private function _initVars($server = NULL)
	{
		if($server === NULL)
			$server = Server::getInstance();
		
		//Stats of players
		$server->set('stats', array());
		
		//Stats config of players
		$server->set('statsConfig', array());
		
		//Awards of game
		$server->set('awards', array('hits' => array(NULL,0), 'kills' => array(NULL,0), 'deaths' => array(NULL,0), 'streaks' => array(NULL,0), 'heads' => array(NULL,0), 'caps' => array(NULL,0), 'ratio' => array(NULL,0) 'round' => array(NULL,0));
		
		//Ratio list
		$server->set('ratioList', array());
		
		//If other plugin want to disable stats reset
		$server->set('disableStatsReset', 0);
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
		$this->_initVars();
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
		$_statsConfig = Server::get('statsConfig');
		
		foreach($_stats as $id => $curent)
		{
			if($_statsConfig[$id]['verbosity'] >= 1)
				$this->_showStatsMsg($id);
		}
		
		if(!empty($this->config['ShowAwards']))
		{
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
		
		$_stats[$id] = array(
			'hits' => 0,
			'kills' => 0,
			'deaths' => 0,
			'streaks' => 0,
			'curstreak' => 0,
			'heads' => 0,
			'caps' => 0,
			'ratio' => 0,
			'round' => 0);
		$_ratioList[$id] = 0;
		$_statsConfig[$id] = array('verbosity' => $this->config['StatsVerbosity']);
		
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
		
		$awards = array('hits' => array(NULL,0), 'kills' => array(NULL,0), 'deaths' => array(NULL,0), 'streaks' => array(NULL,0), 'heads' => array(NULL,0), 'caps' => array(NULL,0), 'ratio' => array(NULL,0), 'round' => array(NULL,0));
		
		$stat = array('hits', 'kills', 'deaths', 'streaks', 'heads', 'caps', 'ratio', 'round');
		
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
	
	public function SrvEventHit($player, $shooter, $partOfBody, $weapon)
	{
		$player = Server::getPlayer($player);
		$shooter = Server::getPlayer($shooter);
		
		// Si les 2 joueurs sont pas dans la même équipe.
		if($player->team != $shooter->team)
		{
			$_stats = Server::get('stats');
			$_awards = Server::get('awards');
			
			$_stats[$shooter->id]['hits']++;
			
			if($partOfBody <= 1)
			{
				$_stats[$shooter->id]['heads']++;
				
				if($this->config['DisplayHeads'] AND ($_stats[$shooter->id]['heads']%5) == 0)
				{
					if($shooter->team == 1) $color = '^1';
					elseif($shooter->team == 2) $color = '^4';
					else $color = '';
					Rcon::topMessage('^3Headshots : $playercolor$playername ^2$heads', array('playercolor' => $color, 'playername' => $shooter->name, 'heads' => $_stats[$shooter->id]['heads']));
				}
			
				if($_stats[$shooter->id]['heads'] > $_awards['heads'][1])
				{
					$_awards['heads'][0] = $shooter->id;
					$_awards['heads'][1] = $_stats[$shooter->id]['heads'];
				}
			}
			
			//Gestion des awards
			if($_stats[$shooter->id]['hits'] > $_awards['hits'][1])
			{
				$_awards['hits'][0] = $shooter->id;
				$_awards['hits'][1] = $_stats[$shooter->id]['hits'];
			}
		
			Server::set('awards', $_awards);
			Server::set('stats', $_stats);
		}
	}
	
	//Event serveur : Kill (on gère les kills avec ajout des kills-deaths en fonction du type de kill)
	public function SrvEventKill($killer, $killed, $type, $weapon = NULL)
	{
		$_stats = Server::get('stats');
		
		$killer = Server::getPlayer($killer);
		$killed = Server::getPlayer($killed);
		
		//Switch d'analyse du type de mort
		switch($type)
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
				$_stats[$killed->id]['deaths']++;
				Server::set('stats', $_stats);
				break;
			//Au changement d'équipe, on ne fait rien
			case '10':
				break;
			//Le reste, on ajoute un kill au tueur, puis une death au mort
			default:
		
				// Si les 2 joueurs sont pas dans la même équipe.
				if($killer->team != $killed->team)
				{
					$_awards = Server::get('awards');
					$_ratioList = Server::get('ratioList');
					$_statsConfig = Server::get('statsConfig');
					
					$_stats[$killer->id]['kills']++;
					$_stats[$killer->id]['curstreak']++;
					if($_stats[$killer->id]['curstreak'] > $_stats[$killer->id]['streaks'])
						$_stats[$killer->id]['streaks'] = $_stats[$killer->id]['curstreak'];
					
					$_stats[$killed->id]['deaths']++;
					$_stats[$killed->id]['curstreak'] = 0;
					
					Server::set('stats', $_stats);
					
					//Gestion des awards
					if($_stats[$killer->id]['kills'] > $_awards['kills'][1])
					{
						$_awards['kills'][0] = $killer->id;
						$_awards['kills'][1] = $_stats[$killer->id]['kills'];
					}
					if($_stats[$killer->id]['streaks'] > $_awards['streaks'][1] && $_stats[$killer->id]['streaks'] > 1)
					{
						$_awards['streaks'][0] = $killer->id;
						$_awards['streaks'][1] = $_stats[$killer->id]['streaks'];
				
						if($this->config['DisplayStreaks'])
						{
							if($killer->team == 1) $color = '^1';
							elseif($killer->team == 2) $color = '^4';
							else $color = '';
							Rcon::topMessage('^3New Streaks : $playercolor$playername ^2$streaks', array('playercolor' => $color, 'playername' => $killer->name, 'streaks' => $_stats[$killer->id]['streaks']));
						}
					}
					
					if($_stats[$killed->id]['deaths'] > $_awards['deaths'][1])
					{
						$_awards['deaths'][0] = $killed->id;
						$_awards['deaths'][1] = $_stats[$killed->id]['deaths'];
					}
						
					
					if($_stats[$killer->id]['deaths'] == 0)
						$ratio = $_stats[$killer->id]['kills'];
					else
						$ratio = $_stats[$killer->id]['kills'] / $_stats[$killer->id]['deaths'];
						
					if($_stats[$killed->id]['deaths'] == 0)
						$dratio = $_stats[$killed->id]['kills'];
					else
						$dratio = $_stats[$killed->id]['kills'] / $_stats[$killed->id]['deaths'];
						
					
					$_ratioList[$killer->id] = $ratio;
					$_ratioList[$killed->id] = $dratio;
					
					arsort($_ratioList);
					$keys = array_keys($_ratioList);
					
					$_awards['ratio'][1] = $_ratioList[$keys[0]];
					$_awards['ratio'][0] = $keys[0];
					
					//Affichage des stats ou pas selon 
					if($_statsConfig[$killed->id]['verbosity'] >= 2)
						$this->_showStatsMsg($killed->id);
					if($_statsConfig[$killer->id]['verbosity'] >= 3)
						$this->_showStatsMsg($killer->id);
						
					Server::set('awards', $_awards);
					Server::set('ratioList', $_ratioList);
				}
				
			break;
		}
		
	}
	
	//Event serveur : Flag (si il capture, on ajoute 1 au compteur de capture du joueur)
	public function SrvEventFlag($player, $flagaction)
	{
		$player = Server::getPlayer($player);
		
		if(Server::getServer()->serverInfo['g_gametype'] == 7)
		{
			if($flagaction == 2) //Si c'est une capture
			{
				$_stats = Server::get('stats');
				$_awards = Server::get('awards');
				
				$_stats[$player->id]['caps']++;
				
				if($this->config['DisplayCaps'])
				{
					if($player->team == 1) $color = '^1';
					elseif($player->team == 2) $color = '^4';
					else $color = '';
					Rcon::topMessage(' $playercolor$playername : ^2$caps ^3caps', array('playercolor' => $color, 'playername' => $player->name, 'caps' => $_stats[$player->id]['caps']));
				}
				
				//Gestion des awards
				if($_stats[$player->id]['caps'] > $_awards['caps'][1])
				{
					$_awards['caps'][0] = $player->id;
					$_awards['caps'][1] = $_stats[$player->id]['caps'];
				}
				
				Server::set('awards', $_awards);
				Server::set('stats', $_stats);
			}
		}
	}
	
	//Event client : !stats (on affiche juste les stats du joueur ayant appelé la commande)
	public function CommandStats($id, $cmd)
	{
		$player = Server::getPlayer($id);
		
		if(!isset($cmd[0]))
		{
			$this->_showStatsMsg($player->id);
		}
		else
		{
			if($player->level >= 80)
			{
				$target = Server::getPlayer(Server::searchPlayer($cmd[0]));
				
				if($target === FALSE)
					Rcon::tell($player->id, 'Unknow player');
				elseif(is_array($target))
					$this->_showStatsMsg($target[0]->id, $player->id);
			}
			else
			{
				Rcon::tell($player->id, 'This command does not take parameters.');
			}
		}
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
		$eventAwards = array();
		
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
				
				$eventAwards[$award] = $_awards[$award];
			}
		}
		
		if($player === NULL) // End of game
		{
			Rcon::say('Awards : $awards', array('awards' => join('^3 - ', $buffer)));
			$this->_plugins->callEventSimple('stats', 'showawards', $eventAwards);
		}
		else
		{
			Rcon::tell($player, 'Awards : $awards', array('awards' => join('^3 - ', $buffer)));
		}
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
		$ratio = round($ratio, 2);
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
					$statAward = '^4/'.round($_awards[$stat][1], 2);
				else
					$statAward = '';
					
				// TODO : refaire cette condition et inclure $aratio
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
			$curStat['curstreak'] = 0;
			$curStat['heads'] = 0;
		}
		
		//Awards to zero
		$_awards = array('hits' => array(NULL,0), 'kills' => array(NULL,0), 'deaths' => array(NULL,0), 'streaks' => array(NULL,0), 'heads' => array(NULL,0), 'caps' => array(NULL,0), 'ratio' => array(NULL,0), 'round' => array(NULL,0));
		
		Server::set('stats', $_stats);
		Server::set('awards', $_awards);
	}
	
	public function IrcAwards($pseudo, $channel, $cmd, $message)
	{
		$serverlist = ServerList::getList();
		$actual = Server::getName();
	
		if(isset($cmd[1]) && in_array($cmd[1], $serverlist))
		{
			Server::setServer($this->_main->servers[$cmd[1]]);
			$this->_printAwards($cmd[1]);
		}
		else
		{
			foreach($serverlist as $server)
			{
				Server::setServer($this->_main->servers[$server]);
				$this->_printAwards($server);
			}
		}
	
		Server::setServer($this->_main->servers[$actual]);
	}
	
	private function _printAwards($server)
	{
		$buffer = array();
		$_awards = Server::get('awards');
		
		$serverinfo = Server::getServer()->serverInfo;
		
		foreach($this->config['ShowAwards'] as $award)
		{
				
			if($_awards[$award][0] !== NULL)
			{
				$player = Server::getPlayer($_awards[$award][0]);
				
				if($serverinfo['g_gametype'] != '0')
				{
					if($player->team == 1)
						$color = "\00304";
					elseif($player->team == 2)
						$color = "\00302";
					elseif($player->team == 3)
						$color = "\00314";
				}
				else
					$color = "\00308";
				
				$buffer[] = $_awards[$award][1]." ".$award.' : '.$color.$player->name."\017";
			}
			else
				$buffer[] = $award.' : nobody';
		}
		
		LeelaBotIrc::sendMessage("\002".LeelaBotIrc::rmColor($serverinfo['sv_hostname'])." (awards) :\002 ".join(' | ', $buffer));
	}
	
	// TODO Afficher Stats avec foreach sur $this->config['ShowStats']
	public function IrcStats($pseudo, $channel, $cmd, $message)
	{
		$server = LeelaBotIrc::nameOfServer($cmd[2], FALSE);
		$actual = Server::getName();
		
		if(isset($cmd[1])) //Il faut un paramètre : le joueur
		{
			if($server !== false)
			{
				Server::setServer($this->_main->servers[$server]);
				
				$target = Server::searchPlayer(trim($cmd[1]));
				
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
					$buffer = array();
					
					$_stats = Server::get('stats');
					$_awards = Server::get('awards');
					$player = Server::getPlayer($target);
					
					if($_stats[$player->id]['deaths'] != 0)
						$ratio = $_stats[$player->id]['kills'] / $_stats[$player->id]['deaths'];
					else
						$ratio = $_stats[$player->id]['kills'];
						
					if(in_array('hits', $this->config['ShowStats'])) //Gestion des hits en fonction de la configuration du plugin de stats
						$hits = "\037Hits\037 : ".$_stats[$player->id]['hits']." - ";
					if(Server::getServer()->serverInfo['g_gametype'] == 7) //Gestion des caps uniquement en CTF
						$caps = " - \037Caps\037 : ".$_stats[$player->id]['caps'];
						
					LeelaBotIrc::sendMessage("\002Stats de ".$player->name."\002 : ".$hits."\037Kills\037 : ".$_stats[$player->id]['kills']." - \037Deaths\037 : ".$_stats[$player->id]['deaths']." - \037Ratio\037 : ".$ratio.$caps." - \037Streaks\037 : ".$_stats[$player->id]['streaks']);

				}
				
				Server::setServer($this->_main->servers[$actual]);
			}
		}
		else
		{
			LeelaBotIrc::sendMessage("Player name missing");
		}
	}
}

$this->addPluginData(array(
'name' => 'stats',
'className' => 'PluginStats',
'display' => 'Stats Plugin',
'autoload' => TRUE));
