<?php
/**
 * \file plugins/stats.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \version 1.0
 * \brief IRC plugin for Leelabot. It allows to have an IRC bot.
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
 * This file contains a one IRC Bot for all servers.
 * 
 */

/**
 * \brief Plugin irc class.
 * This class contains the methods and properties needed by the IRC plugin. It contains the IRC Bot.
 */
class PluginIrc extends Plugin
{
	//Private vars
	private $_socket; // Socket of bot
	private $_connected = FALSE; // if bot connected to irc
	private $_configured = FALSE; 
	
	private $_pseudo;
	private $_channel;
	private $_cmd;
	private $_message;
	
	private $_cmdIrc = array(); // Irc cmds for irc clients
	
	private $_mapUt4List = array('casa', 'kingdom', 'turnpike', 'abbey', 'prague', 'mandolin', 'uptown', 'algiers', 'austria', 'maya', 'tombs', 'elgin', 'oildepot', 'swim', 'harbortown', 'ramelle', 'toxic', 'sanc', 'riyadh', 'ambush', 'eagle', 'suburbs', 'crossing', 'subway', 'tunis', 'thingley');
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		//Config
		if(isset($this->config['Server']) && isset($this->config['Port']) && isset($this->config['Nick']) && isset($this->config['User']) && isset($this->config['Channels']) && isset($this->config['MainChannel']) && isset($this->config['MessageMode']) && in_array($this->config['MessageMode'], array('notice', 'chanmsg', 'privmsg')))
		{
			$this->config['Channels'] = explode(',', $this->config['Channels']);
			
			//Autospeak configuration
			$this->configureAutospeak();
			
			//The bot is now configured
			$this->_configured = TRUE;
			
			//Connection
			$this->_connect();
			
			//IRC commands
			$this->_addCmd('!help', 'CmdHelp', '!help <commande>', 'Permet d\'avoir de l\'aide sur une commande.', 0);
			$this->_addCmd('!status', 'CmdStatus', '!status [<server>]', 'Permet d\'avoir les infos sur la partie actuel.', 0);
			$this->_addCmd('!players', 'CmdPlayers', '!players [<server>]', 'Permet d\'avoir la liste des joueurs présent sur le serveur.', 0);
			$this->_addCmd('!stats', 'CmdStats', '!stats <joueur> <server>', 'Permet d\'avoir les stats d\'un joueur.', 1);
			$this->_addCmd('!awards', 'CmdAwards', '!awards [<server>]', 'Permet d\'avoir les awards actuel.', 0);
			$this->_addCmd('!urt', 'CmdUrt', '!urt [<server>] <message>', 'Permet d\'envoyer un message sur urt.', 1);
			$this->_addCmd('!kick', 'CmdKick', '!kick [<server>] <joueur>', 'Permet d\'avoir de kicker un joueur.', 2);
			$this->_addCmd('!kickall', 'CmdKickAll', '!kickall [<server>] <letters>', 'Permet d\'avoir de kicker plusieurs joueurs contenant l\'ensemble des lettres.', 2);
			$this->_addCmd('!slap', 'CmdSlap', '!slap [<server>] <joueur>', 'Permet d\'avoir de slapper un joueur.', 2);
			$this->_addCmd('!mute', 'CmdMute', '!mute [<server>] <joueur>', 'Permet d\'avoir de muter un joueur.', 2);
			$this->_addCmd('!say', 'CmdSay', '!say [<server>] <message>', 'Permet de faire parler le bot sur urt.', 2);
			$this->_addCmd('!bigtext', 'CmdBigtext', '!bigtext [<server>] <message>', 'Permet d\'envoyer un message en bigtext.', 2);
			$this->_addCmd('!map', 'CmdMap', '!map [<server>] <mapname>', 'Permet de changer la map courante.', 2);
			$this->_addCmd('!nextmap', 'CmdNextMap', '!nextmap [<server>] <mapname>', 'Permet de changer la map suivante.', 2);
			$this->_addCmd('!cyclemap', 'CmdCyclemap', '!cyclemap [<server>]', 'Permet de faire un cyclemap.', 2);
			$this->_addCmd('!restart', 'CmdRestart', '!restart [<server>]', 'Permet de faire un restart.', 2);
			$this->_addCmd('!reload', 'CmdReload', '!reload [<server>]', 'Permet de faire un reload.', 2);
			$this->_addCmd('!serverlist', 'CmdServerList', '!serverlist', 'Permet d\'obtenir la liste des servers.', 2);
			
			//Irc bot main routine
			$this->changeRoutineTimeInterval('RoutineIrcMain', 0);
			
			//Adding event listener
			$this->_plugins->addEventListener('irc', 'Irc');
		}
		else
		{
			Leelabot::message('The irc bot isn\'t configured !', array(), E_WARNING);
		}
	}
	
	private function configureAutospeak()
	{
		// Autospeak IRC <=> URT
		if(isset($this->config['AutoSpeak']))
		{
			if(is_array($this->config['AutoSpeak']))
			{
				$serverlist = ServerList::getList();
				
				$autospeak = array();
				
				foreach($this->config['AutoSpeak'] as $name => $server)
				{
					if(is_array($server) and count($server))
					{
						$autospeak[$name] = array();
						
						foreach($server as $channel => $mode)
						{
							$channel = '#'.$channel;
							
							if(in_array($channel, $this->config['Channels']))
							{
								if(is_numeric($mode) && in_array($mode, array(0, 1, 2, 3)))
								{
									$autospeak[$name][$channel] = $mode;
								}
								else
								{
									$autospeak[$name][$channel] = 0;
									Leelabot::message('The Autospeak.$0 configuration for $1 is invalid. Default values was set (0).', array($name, $channel), E_WARNING);
								}
							}
							else
							{
								Leelabot::message('In Autospeak.$0 configuration, $1 was not recognized.', array($name, $channel), E_WARNING);
							}
						}
					}
					else
					{
						foreach($this->config['Channels'] as $channel)
						{
							$autospeak[$name][$channel] = 0;
						}
						
						Leelabot::message('The Autospeak.$0 configuration is invalid. Default values was set (0 for all chans).', array($name), E_WARNING);
					}
				}
				
				$this->config['AutoSpeak'] = $autospeak;
			}
			elseif(is_numeric($this->config['AutoSpeak']) && in_array($this->config['AutoSpeak'], array(0, 1, 2, 3)))
			{
				$this->config['AutoSpeak'] = $this->config['AutoSpeak'];
			}
			else
			{
				Leelabot::message('The Autospeak configuration was not recognized !', array(), E_WARNING);
				$this->config['AutoSpeak'] = 0;
			}
		}
		else
		{
			$this->config['AutoSpeak'] = 0;
		}
	}
	
	public function destroy()
	{
		$this->_disconnect('Plugin Unload');
		
		return TRUE;
	}
	
	/////////////////////////////////////////////
	// Private functions for IRC connection    //
	/////////////////////////////////////////////
	
	private function _connect()
	{
		if($this->_configured)
		{
			if($this->_socket = fsockopen($this->config['Server'], $this->config['Port'], $errno, $errstr, 10))
			{
				stream_set_blocking($this->_socket, 0);
				
				$this->_connected = TRUE;
				
				$this->_send("USER".str_repeat(' '.$this->config['User'], 4));
				$this->_send("NICK ".$this->config['Nick']);
				
				Leelabot::message('The IRC bot "$0" is connected to $1:$2', array($this->config['Nick'], $this->config['Server'], $this->config['Port']));
			}
			else
			{
				$this->_connected = FALSE;
				Leelabot::message('The IRC connection has failed. We will try again in a few seconds.', array());
			}
		}
	}
	
	private function _disconnect($reason = '')
	{
		if($this->_configured)
		{
			if($this->_connected)
			{
				$this->_send("QUIT : bye ! ".$reason);
				usleep(500000);
				fclose($this->_socket);
				$this->_connected = FALSE;
				
				Leelabot::message('The IRC bot is disconnect.');
			}
		}
	}
	
	private function _send($commande)
	{
		if($this->_connected)
		{
			fputs($this->_socket, $commande."\r\n");
		}
	}
	
	private function _get()
	{
		if($this->_connected)
		{
			$return = fgets($this->_socket, 1024);
			
			if($return) // On lit les données du serveur
			{
				return $return;
			}
			elseif($return === FALSE && $this->_connected = true)
			{
				if(feof($this->_socket))
				{
					$this->_connected = false; 
			    }
			}
		}
		else
		{
			$this->_connect();
		}
	}
	
	/////////////////////////////////////////////
	// Fonctions Public en rapport avec IRC    //
	/////////////////////////////////////////////
	
	public function privmsg($dest, $message)
	{
		$this->_send('PRIVMSG '.$dest.' :'.$message);
	}
	
	public function notice($dest, $message)
	{
		$this->_send('NOTICE '.$dest.' :'.$message);
	}
	
	public function join($chan)
	{
		$this->_send('JOIN '.$chan);
	}
	
	public function part($chan)
	{
		$this->_send('PART '.$chan);
	}
	
	public function isRights($name, $chan, $droit)
	{
		$r = $this->rights($name, $chan);
		
		if($droit == '@')
		{
			if($r == $droit)
				return true;
			else
				return false;
		}
		elseif($droit == '+')
		{
			if($r == $droit OR $r == '@')
				return true;
			else
				return false;
		}
		else
		{
			return true;
		}
	}
	
	public function rights($name, $chan)
	{
		$this->_send('NAMES '.$chan.'');
		$continue = TRUE;
		while($continue)
		{
			$ret = rtrim($this->_get());
			if($ret)
			{
				$data = explode(':',$ret);
				$cmd = explode(' ',$data[1]);
				if($cmd[1] == '353')
					$reponse = $ret;
				elseif($cmd[1] == '366')
					$continue = FALSE;
			}
		}
		
		$result = explode(' ', $reponse);
		array_shift($result); 
		array_shift($result); 
		array_shift($result);
		array_shift($result); 
		array_shift($result); 
		array_walk($result, 'trim');
		
		$result[0] = substr($result[0], 1);
		
		if(in_array('@'.$name, $result) == TRUE)
			return '@';
		elseif(in_array('+'.$name, $result) == TRUE)
			return '+';
		else
			return '';
	}
	
	public function sendMessage($message)
	{				
		if($this->config['MessageMode'] == 'privmsg')
			$this->privmsg($this->_pseudo, $message);
		elseif($this->config['MessageMode'] == 'chanmsg')
			$this->privmsg($this->_channel, $message);
		elseif($this->config['MessageMode'] == 'notice')
			$this->notice($this->_pseudo, $message);
	}
	
	/////////////////////////////////////////////
	// Fonctions privés du bot IRC             //
	/////////////////////////////////////////////
	
	private function _addCmd($cmd, $fonction, $usage, $text, $droits = 0)
	{
		// $droits (0:rien, 1:voice, 2:op)
		if(!array_key_exists($cmd, $this->_cmdIrc))
		{
			$this->_cmdIrc[$cmd] = array(
			'cmd' => $cmd,
			'func' => $fonction,
			'use' => $usage,
			'text' => $text,
			'r' => $droits
			);
			
			return true;
		}
		else
		{
			return false;
		}
	}
	
	private function _deleteCmd($cmd)
	{
		if(array_key_exists($cmd, $this->_cmdIrc))
		{
			unset($this->_cmdIrc[$cmd]);
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/////////////////////////////////////////////
	// Fonctions utiles                        //
	/////////////////////////////////////////////
	
	//Vire les couleurs des messages UrT (merki SRWieZ :D)
	public function _rmColor($string)
	{
		$result = $string;
		$result = preg_replace ("/\^x....../i", "", $result); // remove OSP's colors (^Xrrggbb)
		$result = preg_replace ("/\^./", "", $result); // remove Q3 colors (^2) or OSP control (^N, ^B etc..)
		return $result;
	}
	
	//Fonction normalisant le texte envoyé (uniquement les accents)
	public function normaliser($string)
	{
        $a = 'âäàéèëêîïûüç';
        $b = 'aaaeeeeiiuuc'; 
        $string = utf8_decode($string);     
        $string = strtr($string, utf8_decode($a), $b);
        return utf8_encode($string); 
	}
	
	/////////////////////////////////////////////
	// Boucle principale                       //
	/////////////////////////////////////////////
	
	//Exécutions à chaque fin de boucle, qu'il y ait un message transmis par le serveur ou non
	public function RoutineIrcMain()
	{
		$donnees = $this->_get();
		if($donnees) //Si le serveur nous a envoyé quelque chose
		{
			$commande = explode(' ',$donnees);
			$message = explode(':',$donnees,3);
			$pseudo = explode('!',$message[1]);
			$pseudo = $pseudo[0];
			
			if(isset($commande[1]) && rtrim($commande[0]) == 'PING')
					$this->_send('PONG :'.$message[1]);
			
			// For crazy IRC server
			if(isset($commande[1]))
			{
				if($commande[1] == '001') //Si le serveur nous envoie qu'on vient de se connecter au réseau, on joint les canaux puis on exécute la liste d'auto-perform
				{
					if(isset($this->config['AutoPerform']))
					{
						foreach($this->config['AutoPerform'] as $command)
							$this->_send($command);
					}
					
					$this->join(implode(',', $this->config['Channels']));
						
					Leelabot::message('The bot has join $0', array(implode(',', $this->config['Channels'])));
				}
				
				if($commande[1] == '433')
				{
					$this->config['Nick'] = $this->config['Nick'].'_';
					$this->_send("NICK ".$this->config['Nick']);
					
					Leelabot::message('The nickname has changed for $0', array($this->config['Nick']));
				}
				
				if($commande[1] == 'PRIVMSG') //Si c'est un message
				{
					$this->_pseudo = $pseudo;
					$this->_channel = $commande[2];
					
					if($message[2][0] == '!') //Si c'est une commande
					{
						$cmd = explode(' ',trim($message[2]));
						$cmd[0] = trim($cmd[0]);
						$this->_cmd = $cmd;
						$this->_message = $message;
						
						echo substr($cmd[0], 1)."\n";
						$this->_plugins->callEvent('irc', substr($cmd[0], 1), $this);
					}
					else
					{
						$irc2urt = $this->normaliser(rtrim($message[2]));
						$pseudo = explode(' ',$message[1]);
						$pseudo = explode('!',$pseudo[0]);
						$pseudo = $pseudo[0];
								
						$serverlist = ServerList::getList();
						$channel = $this->_channel;
						
						if(is_array($this->config['AutoSpeak']))
						{
							foreach($serverlist as $server)
							{
								if(isset($this->config['AutoSpeak'][$server][$channel]) && in_array($this->config['AutoSpeak'][$server][$channel], array(1, 3)))
								{
									$rcon = ServerList::getServerRCon($server);
									$rcon->say('^4IRC : <$nick> $message', array('nick' => $pseudo, 'message' => $irc2urt));
								}
							}
						}
						elseif(is_numeric($this->config['AutoSpeak']))
						{
							if(in_array($this->config['AutoSpeak'], array(1, 3)))
							{
								
								foreach($serverlist as $server)
								{
									$rcon = ServerList::getServerRCon($server);
									$rcon->say('^4IRC : <$nick> $message', array('nick' => $pseudo, 'message' => $irc2urt));
								}
							}
						}
					}
				}
			}
		}
	}
	
	
	
	/////////////////////////////////////////////
	// Commandes Partie Urt                    //
	/////////////////////////////////////////////
	
	public function CommandIrcco($player, $args)
	{
		$this->_send('NAMES '.$this->config['MainChannel']);
		$continue = TRUE;
		while($continue)
		{
			$ret = rtrim($this->_get());
			if($ret)
			$data = explode(':',$ret);
			$cmd = explode(' ',$data[1]);
			if($cmd[1] == '353')
				$nicks .= ' '.$data[2];
			elseif($cmd[1] == '366')
				$continue = FALSE;
		}
		
		$nicks = str_replace(array('@','+','~'), array('','',''), $nicks);
		
		Rcon::tell($player, 'People connected to IRC : $nicks', array('nicks' => $nicks));
	}
	
	
	
	public function CommandIrc($player, $args)
	{
		$server = Server::getName();
		$nick = $this->_rmColor(Server::getPlayer($player)->name);
		$message = $this->_rmColor(implode(' ', $args));
		
		if(is_array($this->config['AutoSpeak']))
		{
			foreach($this->config['Channels'] as $channel)
			{
				if(isset($this->config['AutoSpeak'][$server][$channel]) && in_array($this->config['AutoSpeak'][$server][$channel], array(0, 3)))
				{
					$this->privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
				}
			}
		}
		elseif(is_numeric($this->config['AutoSpeak']))
		{
			if(in_array($this->config['AutoSpeak'], array(0, 3)))
			{
				foreach($this->config['Channels'] as $channel)
				{
					$this->privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
				}
			}
		}
	}
	
	//Event serveur : IRC (envoie sur IRC tout ce qui se dit)
	public function SrvEventSay($id, $contents)
	{
		if($contents[0] != '!')
		{
			$nick = $this->_rmColor(Server::getPlayer($id)->name);
			$message = $this->_rmColor($contents);
			$server = Server::getName();
				
			if(is_array($this->config['AutoSpeak']))
			{
				foreach($this->config['Channels'] as $channel)
				{
					if(isset($this->config['AutoSpeak'][$server][$channel]) && in_array($this->config['AutoSpeak'][$server][$channel], array(1, 2)))
					{
						$this->privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
					}
				}
			}
			elseif(is_numeric($this->config['AutoSpeak']))
			{
				if(in_array($this->config['AutoSpeak'], array(1, 2)))
				{
					foreach($this->config['Channels'] as $channel)
					{
						$this->privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
					}
				}
			}
		}
	}
	
	/////////////////////////////////////////////
	// Fonctions IRC	                       //
	/////////////////////////////////////////////
	private function IrcHelp($_ircplugin)
	{
		$cmd = $_ircplugin->_cmd;
		
		$r = $_ircplugin->rights(trim($_ircplugin->_pseudo), $_ircplugin->config['MainChannel']);
		
		if(!isset($cmd[1])) //Si on ne demande pas une commande précise, on affiche la liste
		{
			$list = array();
			
			foreach($_ircplugin->_cmdIrc as $cmds)
			{
				if($cmds['r'] == 1)
				{
					if($r == '+' OR $r == '@')
						$list[] = $cmds['cmd'];
				}
				elseif($cmds['r'] == 2)
				{
					if($r == '@')
						$list[] = $cmds['cmd'];
				}
				else
				{
					$list[] = $cmds['cmd'];
				}
			}
			
			$_ircplugin->sendMessage('List : '.join(', ', $list).'.');
		}
		else //Sinon on affiche l'aide d'une commande
		{
			$cmd[1] = str_replace('!','',$cmd[1]);
			$cmd[1] = '!'.$cmd[1];
			
			if(array_key_exists($cmd[1], $_ircplugin->_cmdIrc))
			{
				$_ircplugin->sendMessage('Usage : '.$_ircplugin->_cmdIrc[$cmd[1]]['use']);
				$_ircplugin->sendMessage($_ircplugin->_cmdIrc[$cmd[1]]['text']);
			}
			else
			{
				$_ircplugin->sendMessage("This command doesn't exist.");
			}
		}
		
	}
	
	private function IrcUrt($_ircplugin)
	{
		$cmd = $_ircplugin->_cmd;
		$message = $_ircplugin->_message;
		$server = $_ircplugin->_nameOfServer(1);
		
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
				$rcon->say('^4IRC : <$nick> $message', array('nick' => $_ircplugin->_pseudo, 'message' => $_ircplugin->normaliser(rtrim($envoi[$i]))));
		}
	}
	
	private function IrcServerList($_ircplugin)
	{
		if(!$_ircplugin->_verifyClientLevel('operator'))
			return FALSE;
		
		$serverlist = ServerList::getList();
		$this->sendMessage("Servers : ".join(', ', $serverlist));
	}
	
	// Stats plugin event
	public function StatsShowAwards($awards)
	{
		$buffer = array();
		
		foreach($awards as $award => $player)
		{
				if($player !== NULL)
					$buffer[] = "\037".ucfirst($award)."\037".' : '.Server::getPlayer($player)->name;
				else
					$buffer[] = "\037".ucfirst($award)."\037".' : nobody';
		}
		
		$this->privmsg($this->config['MainChannel'], "\002Awards :\002 ".join(' | ', $buffer));
	}
	
	public function _nameOfServer($cmdkey, $otherargs = TRUE)
	{
		$cmd = $this->_cmd;
		$serverlist = ServerList::getList();
		$server = FALSE;
		
		if(isset($cmd[$cmdkey]))
		{
			if(in_array($cmd[$cmdkey], ServerList::getList()))
			{
				$server = $cmd[$cmdkey];
			}
			else
			{
				if($otherargs)
				{
					if(count($serverlist) == 1)
						$server = Server::getName();
					else
						$this->sendMessage("Please specify the server : ".join(', ', $serverlist));
				}
				else
				{
					$this->sendMessage("This server doesn't exist. Available Servers : ".join(', ', $serverlist));
				}
			}
		}
		else
		{
			if(count($serverlist) == 1)
				$server = Server::getName();
			else
				$this->sendMessage("Please specify the server : ".join(', ', $serverlist));
		}
		
		return $server;
	}
						
	public function _verifyClientLevel($levelwant)
	{
		if($levelwant == 'voice')
		{
			if($this->isRights(trim($pseudo), $this->config['MainChannel'], '+'))
			{
				return true;
			}
			else
			{
				$this->sendMessage('Vous devez être "voice" pour utiliser cette commande.');
				return false;
			}
		}
		elseif($levelwant == 'operator')
		{
			if($this->isRights(trim($pseudo), $this->config['MainChannel'], '@'))
			{
				return true;
			}
			else
			{
				$this->sendMessage('Vous devez être "operateur" pour utiliser cette commande.');
				return false;
			}
		}
		else
		{
			return NULL;
		}
	}
}

$this->addPluginData(array(
'name' => 'irc',
'className' => 'PluginIrc',
'display' => 'IRC Plugin',
'dependencies' => array('stats'),
'autoload' => TRUE));
