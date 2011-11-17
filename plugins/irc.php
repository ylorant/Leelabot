<?php
/**
 * \file plugins/stats.php
 * \author Deniz Eser <srwiez@gmail.com>
 * \version 0.1
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
 * \brief Plugin stats class.
 * This class contains the methods and properties needed by the IRC plugin. It contains the IRC Bot.
 */
class PluginIrc extends Plugin
{
	//Private vars
	private $_socket; // Socket of bot
	private $_connected = FALSE; // if bot connected to irc
	
	private $_pseudo;
	private $_channel;
	private $_cmd;
	private $_message;
	
	private $_cmdIrc = array(); // Irc cmds for irc clients
	
	/** Init function. Loads configuration.
	 * This function is called at the plugin's creation, and loads the config from main config data (in Leelabot::$config).
	 * 
	 * \return Nothing.
	 */
	public function init()
	{
		//Config
		if($this->config && isset($this->config['Server']) && isset($this->config['Port']) && isset($this->config['Nick']) && isset($this->config['User']) && isset($this->config['Channels']) && isset($this->config['MainChannel']) && isset($this->config['MessageMode']))
		{
			//Connection
			$this->_connect();
			
			//IRC commands
			$this->_addCmd('!help', 'CmdHelp', '!help <commande>', 'Permet d\'avoir de l\'aide sur une commande.', 0);
			$this->_addCmd('!status', 'CmdStatus', '!status', 'Permet d\'avoir les infos sur la partie actuel.', 0);
			$this->_addCmd('!players', 'CmdPlayers', '!players', 'Permet d\'avoir la liste des joueurs présent sur le serveur.', 0);
			$this->_addCmd('!stats', 'CmdStats', '!stats <joueur>', 'Permet d\'avoir les stats d\'un joueur.', 1);
			$this->_addCmd('!awards', 'CmdAwards', '!awards', 'Permet d\'avoir les awards actuel.', 0);
			$this->_addCmd('!urt', 'CmdUrt', '!urt <message>', 'Permet d\'envoyer un message sur urt.', 1);
			$this->_addCmd('!kick', 'CmdKick', '!kick <joueur>', 'Permet d\'avoir de kicker un joueur.', 2);
			$this->_addCmd('!kickall', 'CmdKickAll', '!kickall <lettres>', 'Permet d\'avoir de kicker plusieurs joueurs contenant l\'ensemble des lettres.', 2);
			$this->_addCmd('!slap', 'CmdSlap', '!slap <joueur>', 'Permet d\'avoir de slapper un joueur.', 2);
			$this->_addCmd('!mute', 'CmdMute', '!mute <joueur>', 'Permet d\'avoir de muter un joueur.', 2);
			$this->_addCmd('!say', 'CmdSay', '!say <message>', 'Permet de faire parler le bot sur urt.', 2);
			$this->_addCmd('!bigtext', 'CmdBigtext', '!bigtext <message>', 'Permet d\'envoyer un message en bigtext.', 2);
			$this->_addCmd('!map', 'CmdMap', '!map <mapname>', 'Permet de changer la map courante.', 2);
			$this->_addCmd('!nextmap', 'CmdNextMap', '!nextmap <mapname>', 'Permet de changer la map suivante.', 2);
			$this->_addCmd('!cyclemap', 'CmdCyclemap', '!cyclemap', 'Permet de faire un cyclemap.', 2);
			$this->_addCmd('!restart', 'CmdRestart', '!restart', 'Permet de faire un restart.', 2);
			$this->_addCmd('!reload', 'CmdReload', '!reload', 'Permet de faire un reload.', 2);
			
			$this->changeRoutineTimeInterval('RoutineIrcMain', 0);
		}
		else
		{
			Leelabot::message('The irc bot isn\'t configured !', array(), E_WARNING);
		}
	}
	
	/////////////////////////////////////////////
	// Private functions for IRC connection    //
	/////////////////////////////////////////////
	
	private function _connect()
	{
		if($this->_socket = fsockopen($this->config['Server'], $this->config['Port']))
		{
			stream_set_blocking($this->_socket, 0);
			
			$this->_connected = TRUE;
			
			$this->_send("USER".str_repeat(' '.$this->config['User'], 4));
			$this->_send("NICK ".$this->config['Nick']);
			
			Leelabot::message('The bot "$0" is connected to $1:$2', array($this->config['Nick'], $this->config['Server'], $this->config['Port']));
		}
		else
		{
			$this->_connected = TRUE;
			Leelabot::message('The connection has failed. We will try again in a few seconds.', array());
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
			if($return = fgets($this->_socket,1024)) // On lit les données du serveur
			{
				return $return;
			}
			else
			{
				// TODO $this->_connected = false; (timeout, server connection close, etc..)
			}
		}
		else
		{
			return FALSE;
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
			$data = explode(':',$ret);
			$cmd = explode(' ',$data[1]);
			if($cmd[1] == '353')
				$reponse = $ret;
			elseif($cmd[1] == '366')
				$continue = FALSE;
		}
		$result = explode(' ', $reponse);
		array_shift($result); 
		array_shift($result); 
		array_shift($result);
		array_shift($result); 
		array_shift($result); 
		array_walk($result, 'trim');
		
		if(in_array('@'.$name, $result) == TRUE OR in_array(':@'.$name, $result) == TRUE)
			return '@';
		elseif(in_array('+'.$name, $result) == TRUE OR in_array(':+'.$name, $result) == TRUE)
			return '+';
		else
			return '';
	}
	
	public function sendMessage($message)
	{				
		if($this->_messageMode == 'privmsg')
			$this->privmsg($this->_pseudo, $message);
		elseif($this->_messageMode == 'chanmsg')
			$this->privmsg($this->_channel, $message);
		elseif($this->_messageMode == 'notice')
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
			
			if(rtrim($commande[0]) == 'PING')
				$this->_send('PONG :'.$message[1]);

			if($commande[1] == '001') //Si le serveur nous envoie qu'on vient de se connecter au réseau, on joint les canaux puis on exécute la liste d'auto-perform
			{
				if(isset($this->config['AutoPerform']))
				{
					foreach($this->config['AutoPerform'] as $command)
						$this->_send($command);
				}
				
				$this->join($this->config['Channels']);
					
				Leelabot::message('The bot has join $0', array($this->config['Channels']));
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
					
					if(array_key_exists($cmd[0], $this->_cmdIrc))
					{
						if($this->_cmdIrc[$cmd[0]]['r'] == 1)
						{
							if($this->isRights(trim($pseudo), $this->config['MainChannel'], '+'))
							{
								$this->{$this->_cmdIrc[$cmd[0]]['func']}();
							}
							else
							{
								$this->sendMessage('Vous devez être "voice" pour utiliser cette commande.');
							}
						}
						elseif($this->_cmdIrc[$cmd[0]]['r'] == 2)
						{
							if($this->isRights(trim($pseudo), $this->config['MainChannel'], '@'))
							{
								$this->{$this->_cmdIrc[$cmd[0]]['func']}();
							}
							else
							{
								$this->sendMessage('Vous devez être "operateur" pour utiliser cette commande.');
							}
						}
						else
						{
							$this->{$this->_cmdIrc[$cmd[0]]['func']}();
						}
					}
				}
				else
				{
					if(($this->config['AutoSpeak'] == 1 OR $this->config['AutoSpeak'] == 3) AND $this->_channel == $this->config['MainChannel'])
					{
						$irc2urt = $this->normaliser(rtrim($message[2]));
						$pseudo = explode(' ',$message[1]);
						$pseudo = explode('!',$pseudo[0]);
						$pseudo = $pseudo[0];
						
						$servers = ServerList::getList();
						
						foreach($servers as $server)
						{
							$rcon = ServerList::getServerRCon($server);
							$rcon->say('^4IRC : <$nick> $message', array('nick' => $pseudo, 'message' => $irc2urt));
						}
					}
				}
			}
		}
			
		return TRUE;
	}
	
	
	
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
		if($this->_autoSpeak == 0 OR $this->_autoSpeak == 3)
		{
			$nick = $this->_rmColor(Server::getPlayer($id)->name);
			$message = $this->_rmColor(impode(' ', $args));
			$this->privmsg($this->config['MainChannel'], "\002[".Server::getName()."] <".$nick."> :\002 ".$message);
		}
	}
	
	//Event serveur : IRC (envoie sur IRC tout ce qui se dit)
	public function SrvEventSay($id, $contents)
	{
		if($contents[0] != '!' && (isset($this->config['AutoSpeak']) OR in_array($this->config['AutoSpeak'], array(1, 2))))
		{
			$nick = $this->_rmColor(Server::getPlayer($id)->name);
			$message = $this->_rmColor($contents);
			$this->privmsg($this->config['MainChannel'], "\002[".Server::getName()."] <".$nick."> :\002 ".$message);
		}
	}
}

return $this->initPlugin(array(
'name' => 'irc',
'className' => 'PluginIrc',
'autoload' => TRUE));