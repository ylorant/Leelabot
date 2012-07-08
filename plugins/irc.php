<?php
/**
 * \file plugins/stats.php
 * \author Eser Deniz <srwiez@gmail.com>
 * \version 2.0
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
 * \brief IRC Connection class.
 * This class contains the methods and properties needed by the IRC bot.
 */

class LeelaBotIrc
{
	private static $_instance;
	
	private $_socket; // Socket of bot
	
	private $_connected = FALSE; // if bot connected to irc
	private $_configured = FALSE; // if configured
	private $_attempt = 0;
	
	private $_pseudo; // last person who spoke.
	private $_channel; // last channel where last person who spoke.
	
	public $config = array();

	private function __construct() {}

    private function __clone () {}
    
    public static function getInstance () {
        if (!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
    }
    
    public static function setConfig(&$config)
    {
		$instance = self::getInstance();
		$instance->config = $config;
	}
    
    public static function setConfigured($configured)
    {
		$instance = self::getInstance();
		$instance->_configured = $configured;
	}
	
	public static function connect()
	{
		$instance = self::getInstance();
		
		if($instance->_configured)
		{
			$instance->_socket = fsockopen($instance->config['Server'], $instance->config['Port'], $errno, $errstr, 10);
			
			if($instance->_socket !== FALSE && !$instance->_connected && ($instance->_attempt+20) <= time())
			{
				stream_set_blocking($instance->_socket, 0);
				
				$instance->_connected = TRUE;
				$instance->_attempt = time();
				
				$instance->send("USER".str_repeat(' '.$instance->config['User'], 4));
				$instance->send("NICK ".$instance->config['Nick']);
				
				Leelabot::message('The IRC bot "$0" is connected to $1:$2', array($instance->config['Nick'], $instance->config['Server'], $instance->config['Port']));
			}
			else
			{
				$instance->_connected = FALSE;
				Leelabot::message('The IRC connection has failed. We will try again in a few seconds.', array());
			}
		}
	}
	
	public static function disconnect($reason = 'bye !')
	{
		$instance = self::getInstance();
		
		if($instance->_configured)
		{
			if($instance->_connected)
			{
				$instance->send("QUIT :".$reason);
				usleep(500000);
				fclose($instance->_socket);
				$instance->_connected = FALSE;
				
				Leelabot::message('The IRC bot is disconnect.');
			}
		}
	}
	
	public static function send($command)
	{
		$instance = self::getInstance();
		
		if($instance->_connected)
		{
			fputs($instance->_socket, $command."\r\n");
		}
	}
	
	public static function get()
	{
		$instance = self::getInstance();
		
		if($instance->_connected)
		{
			$return = fgets($instance->_socket, 1024);
			
			if($return) // On lit les données du serveur
			{
				return $return;
			}
			elseif($return === FALSE)
			{
				if(feof($instance->_socket))
				{
					$instance->_connected = FALSE; 
					Leelabot::message('Server have close connection.');
			    }
			}
		}
		else
		{
			$instance->connect();
		}
	}
	
	
	public static function privmsg($dest, $message)
	{
		$instance = self::getInstance();
		$instance->send('PRIVMSG '.$dest.' :'.$message);
	}
	
	public static function notice($dest, $message)
	{
		$instance = self::getInstance();
		$instance->send('NOTICE '.$dest.' :'.$message);
	}
	
	public static function join($chan)
	{
		$instance = self::getInstance();
		$instance->send('JOIN '.$chan);
	}
	
	public static function part($chan)
	{
		$instance = self::getInstance();
		$instance->send('PART '.$chan);
	}
	
	public static function haveLevel($name, $chan, $right)
	{
		$instance = self::getInstance();
		
		$r = $instance->getLevel($name, $chan);
		
		if($r >= $right)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	public static function getLevel($name, $chan)
	{
		$instance = self::getInstance();
		
		$instance->send('NAMES '.$chan.'');
		$continue = TRUE;
		while($continue)
		{
			$ret = rtrim($instance->get());
			if($ret)
			{
				$data = explode(':',$ret);
				$cmd = explode(' ',$data[1]);
				if($cmd[1] == '353')
					$reply = $ret;
				elseif($cmd[1] == '366')
					$continue = FALSE;
			}
		}
		
		$result = explode(' ', $reply);
		array_shift($result); 
		array_shift($result); 
		array_shift($result);
		array_shift($result); 
		array_shift($result); 
		array_walk($result, 'trim');
		
		$result[0] = substr($result[0], 1);
		
		if(in_array('@'.$name, $result) == TRUE)
			return 2;
		elseif(in_array('+'.$name, $result) == TRUE)
			return 1;
		else
			return 0;
	}
    
    public static function setPseudo($pseudo)
    {
		$instance = self::getInstance();
		$instance->_pseudo = $pseudo;
	}
    
    public static function setChannel($channel)
    {
		$instance = self::getInstance();
		$instance->_channel = $channel;
	}
	
	public static function sendMessage($message)
	{
		$instance = self::getInstance();
		
		if($instance->config['MessageMode'] == 'privmsg')
			$instance->privmsg($instance->_pseudo, $message);
		elseif($instance->config['MessageMode'] == 'chanmsg')
			$instance->privmsg($instance->_channel, $message);
		elseif($instance->config['MessageMode'] == 'notice')
			$instance->notice($instance->_pseudo, $message);
	}
	
	//Vire les couleurs des messages UrT (merki SRWieZ :D)
	public static function rmColor($string)
	{
		$result = $string;
		$result = preg_replace ("/\^x....../i", "", $result); // remove OSP's colors (^Xrrggbb)
		$result = preg_replace ("/\^./", "", $result); // remove Q3 colors (^2) or OSP control (^N, ^B etc..)
		return $result;
	}
	
	//Fonction normalisant le texte envoyé (uniquement les accents)
	public static function standardize($string)
	{
        $a = 'âäàéèëêîïûüç';
        $b = 'aaaeeeeiiuuc'; 
        $string = utf8_decode($string);     
        $string = strtr($string, utf8_decode($a), $b);
        return utf8_encode($string); 
	}
	
	public static function nameOfServer($arg, $otherargs = TRUE)
	{
		$instance = self::getInstance();
		
		$serverlist = ServerList::getList();
		$server = false;
		
		if(isset($arg))
		{
			if(in_array($arg, ServerList::getList()))
				$server = $arg;
			else
			{
				if($otherargs)
				{
					if(count($serverlist) == 1)
						$server = Server::getName();
					else
						$instance->sendMessage("Please specify the server : ".join(', ', $serverlist));
				}
				else
					$instance->sendMessage("This server doesn't exist. Available Servers : ".join(', ', $serverlist));
			}
		}
		else
		{
			if(count($serverlist) == 1)
				$server = Server::getName();
			else
				$instance->sendMessage("Please specify the server : ".join(', ', $serverlist));
		}
		
		return $server;
	}
}



/**
 * \brief Plugin irc class.
 * This class contains the methods and properties needed by the IRC plugin.
 */

class PluginIrc extends Plugin
{
	//Private vars
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
			if(!is_array($this->config['Channels']))
				$this->config['Channels'] = explode(',', $this->config['Channels']);
			
			//Autospeak configuration
			if(isset($this->config['AutoSpeak']))
				$this->configureAutospeak();
			else
				$this->config['AutoSpeak'] = 0;
			
			//The bot is now configured
			LeelaBotIrc::setConfig($this->config);
			LeelaBotIrc::setConfigured(TRUE);
			
			//Connection
			LeelaBotIrc::connect();
			
			/*
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
			*/
			
			//Adding event listener
			$this->_plugins->addEventListener('irc', 'Irc');
			
			//IRC commands level (0:all , 1:voice, 2:operator)
			$this->_plugins->setEventLevel('irc', 'help', 0);
			$this->_plugins->setEventLevel('irc', 'serverlist', 0);
			$this->_plugins->setEventLevel('irc', 'urt', 1);
			
			//Irc bot main routine
			$this->changeRoutineTimeInterval('RoutineIrcMain', 0);
		}
		else
		{
			Leelabot::message('The irc bot isn\'t configured !', array(), E_WARNING);
		}
	}
	
	private function configureAutospeak()
	{
		// Autospeak IRC <=> URT
		if(!is_array($this->config['AutoSpeak']) && (!is_numeric($this->config['AutoSpeak']) || !in_array($this->config['AutoSpeak'], array(0, 1, 2, 3))))
		{
			Leelabot::message('The Autospeak configuration was not recognized !', array(), E_WARNING);
			$this->config['AutoSpeak'] = 0;
			return;
		}
			
		$autospeak = array();
		foreach($this->config['AutoSpeak'] as $name => $server)
		{
			if(is_array($server) && count($server))
			{
				$autospeak[$name] = array();
				foreach($server as $channel => $mode)
				{
					$channel = '#'.$channel;
					if(in_array($channel, $this->config['Channels']) && is_numeric($mode) && in_array($mode, array(0, 1, 2, 3)))
						$autospeak[$name][$channel] = $mode;
					elseif(in_array($channel, $this->config['Channels']))
					{
						$autospeak[$name][$channel] = 0;
						Leelabot::message('The Autospeak.$0 configuration for $1 is invalid. Default values was set (0).', array($name, $channel), E_WARNING);
					}	
					else
						Leelabot::message('In Autospeak.$0 configuration, $1 was not recognized.', array($name, $channel), E_WARNING);
				}
				continue;
			}
			
			foreach($this->config['Channels'] as $channel)
				$autospeak[$name][$channel] = 0;
				
			Leelabot::message('The Autospeak.$0 configuration is invalid. Default values was set (0 for all chans).', array($name), E_WARNING);
		}
		
		$this->config['AutoSpeak'] = $autospeak;
		
	}
	
	public function destroy()
	{
		LeelaBotIrc::disconnect('Plugin Unload');
		
		return TRUE;
	}
	
	/////////////////////////////////////////////
	// Boucle principale                       //
	/////////////////////////////////////////////
	
	//Exécutions à chaque fin de boucle, qu'il y ait un message transmis par le serveur ou non
	public function RoutineIrcMain()
	{
		$donnees = LeelaBotIrc::get();
		if($donnees) //Si le serveur nous a envoyé quelque chose
		{
			$commande = explode(' ',$donnees);
			$message = explode(':',$donnees,3);
			$pseudo = explode('!',$message[1]);
			$pseudo = $pseudo[0];
			LeelaBotIrc::setPseudo($pseudo);
			
			if(isset($commande[1]) && rtrim($commande[0]) == 'PING')
					LeelaBotIrc::send('PONG :'.$message[1]);
			
			// For crazy IRC server
			if(isset($commande[1]))
			{
				if($commande[1] == '001') //Si le serveur nous envoie qu'on vient de se connecter au réseau, on joint les canaux puis on exécute la liste d'auto-perform
				{
					if(isset($this->config['AutoPerform']))
					{
						foreach($this->config['AutoPerform'] as $command)
							LeelaBotIrc::send($command);
					}
					
					LeelaBotIrc::join(implode(',', $this->config['Channels']));
						
					Leelabot::message('The IRC bot has join $0', array(implode(',', $this->config['Channels'])));
				}
				
				if($commande[1] == '433')
				{
					$this->config['Nick'] = $this->config['Nick'].'_';
					LeelaBotIrc::setConfig($this->config);
					LeelaBotIrc::send("NICK ".$this->config['Nick']);
					
					Leelabot::message('The IRC nickname has changed for $0', array($this->config['Nick']));
				}
				
				if($commande[1] == 'PRIVMSG') //Si c'est un message
				{
					$channel = $commande[2];
					LeelaBotIrc::setChannel($channel);
					
					if($message[2][0] == '!') //Si c'est une commande
					{
						$cmd = explode(' ', trim($message[2]));
						$cmd[0] = substr($cmd[0], 1);
						
						$level = LeelaBotIrc::getLevel($pseudo, $this->config['MainChannel']);
						
						$return = $this->_plugins->callEvent('irc', $cmd[0], $level, NULL, $pseudo, $channel, $cmd, $message);
						
						if($return === Events::ACCESS_DENIED)
							LeelaBotIrc::sendMessage("You don't have enough rights.");
					}
					else
					{
						$irc2urt = LeelaBotIrc::standardize(rtrim($message[2]));
						$pseudo = explode(' ',$message[1]);
						$pseudo = explode('!',$pseudo[0]);
						$pseudo = $pseudo[0];
								
						$serverlist = ServerList::getList();
						
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
						elseif(is_numeric($this->config['AutoSpeak']) && in_array($this->config['AutoSpeak'], array(1, 3)))
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
	
	
	
	/////////////////////////////////////////////
	// Commandes Partie Urt                    //
	/////////////////////////////////////////////
	
	public function CommandIrcco($player, $args)
	{
		LeelaBotIrc::send('NAMES '.$this->config['MainChannel']);
		$continue = TRUE;
		while($continue)
		{
			$ret = rtrim(LeelaBotIrc::get());
			if($ret)
			{
				$data = explode(':',$ret);
				$cmd = explode(' ',$data[1]);
				
				if($cmd[1] == '353')
					$nicks .= ' '.$data[2];
				elseif($cmd[1] == '366')
					$continue = FALSE;
			}
		}
		
		$nicks = str_replace(array('@','+','~'), array('','',''), $nicks);
		
		Rcon::tell($player, 'People connected to IRC : $nicks', array('nicks' => $nicks));
	}
	
	
	
	public function CommandIrc($player, $args)
	{
		$server = Server::getName();
		$nick = LeelaBotIrc::rmColor(Server::getPlayer($player)->name);
		$message = LeelaBotIrc::rmColor(implode(' ', $args));
		
		if(is_array($this->config['AutoSpeak']))
		{
			foreach($this->config['Channels'] as $channel)
			{
				if(isset($this->config['AutoSpeak'][$server][$channel]) && in_array($this->config['AutoSpeak'][$server][$channel], array(0, 3)))
					LeelaBotIrc::privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
			}
		}
		elseif(is_numeric($this->config['AutoSpeak']))
		{
			if(in_array($this->config['AutoSpeak'], array(0, 3)))
			{
				foreach($this->config['Channels'] as $channel)
					LeelaBotIrc::privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
			}
		}
	}
	
	//Event serveur : IRC (envoie sur IRC tout ce qui se dit)
	public function SrvEventSay($id, $contents)
	{
		if($contents[0] != '!')
		{
			$nick = LeelaBotIrc::rmColor(Server::getPlayer($id)->name);
			$message = LeelaBotIrc::rmColor($contents);
			$server = Server::getName();
				
			if(is_array($this->config['AutoSpeak']))
			{
				foreach($this->config['Channels'] as $channel)
				{
					if(isset($this->config['AutoSpeak'][$server][$channel]) && in_array($this->config['AutoSpeak'][$server][$channel], array(1, 2)))
					{
						LeelaBotIrc::privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
					}
				}
			}
			elseif(is_numeric($this->config['AutoSpeak']))
			{
				if(in_array($this->config['AutoSpeak'], array(1, 2)))
				{
					foreach($this->config['Channels'] as $channel)
					{
						LeelaBotIrc::privmsg($channel, "\002[".$server."] <".$nick."> :\002 ".$message);
					}
				}
			}
		}
	}
	
	// Stats plugin event
	public function StatsShowAwards($awards)
	{
		$buffer = array();
		
		$serverinfo = Server::getServer()->serverInfo;
		
		foreach($awards as $award => $infos)
		{
			if($player !== NULL)
			{
				$player = Server::getPlayer($infos[0]);
				
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
				
				$buffer[] = $infos[1]." ".$award.' : '.$color.$player->name."\017";
			}
			else
				$buffer[] = $award.' : nobody';
		}
		
		LeelaBotIrc::privmsg($this->config['MainChannel'], "\002Awards on ".LeelaBotIrc::rmColor($serverinfo['sv_hostname'])." :\002 ".join(' | ', $buffer));
	}
	
	/////////////////////////////////////////////
	// Fonctions IRC	                       //
	/////////////////////////////////////////////
	public function IrcHelp($pseudo, $channel, $cmd, $message)
	{
		$level = LeelaBotIrc::getLevel(trim($pseudo), $this->config['MainChannel']);
		
		if(!isset($cmd[1])) //Si on ne demande pas une commande précise, on affiche la liste
		{
			$list = array();
			$events = $this->_plugins->listEvents('irc');
			ksort($events); // Alphabetical order
			
			foreach($events as $event => $lvl)
			{
				if($level >= $lvl)
					$list[] = $event;
			}
			
			LeelaBotIrc::sendMessage('List : '.join(', ', $list).'.');
		}
		else //Sinon on affiche l'aide d'une commande
		{
			$cmd[1] = str_replace('!','',$cmd[1]);
			
			if($this->_plugins->eventExists('irc', $cmd[1]))
			{
				LeelaBotIrc::sendMessage('!'.$cmd[1].' : euh..');
				// TODO : find help.
			}
			else
			{
				LeelaBotIrc::sendMessage("This command doesn't exist.");
			}
		}
		
	}
	
	public function IrcUrt($pseudo, $channel, $cmd, $message)
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
				$rcon->say('^4IRC : <$nick> $message', array('nick' => $pseudo, 'message' => LeelaBotIrc::standardize(rtrim($envoi[$i]))));
		}
	}
	
	public function IrcServerList($pseudo, $channel, $cmd, $message)
	{
		$serverlist = ServerList::getList();
		LeelaBotIrc::sendMessage("Servers : ".join(', ', $serverlist));
	}
	
}

$this->addPluginData(array(
'name' => 'irc',
'className' => 'PluginIrc',
'display' => 'IRC Plugin',
'autoload' => TRUE));
