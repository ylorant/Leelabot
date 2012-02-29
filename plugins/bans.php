<?php
/**
 * \file plugins/bans.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Ban plugin for Leelabot. Allows admins to ban players.
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
 * This file contains the ban plugins. It allows admins to ban unwanted players from managed servers.
 * The bans can be for only one server (like a reduced temporary ban for teamkill of flood), or bot-wide (cheaters or hackers for example),
 * and players can be banned permanently (at least until the ban database is deleted), or only temporarily.
 * Bans can be on IPs, or on GUIDs. By default :
 * - For short bans (temporary, under 1 day, and from only one server), only the GUID signing is used.
 * - For medium bans (temporary, from 1 day to 1 month), single IP is used, in addition to the GUID.
 * - For long bans (over 1 month temporary bans, permanent bans), IP mask (C class) is used, in addition to the GUID.
 * 
 * The plugin also keeps in memory the last player disconnected for each server, allowing to ban him even if he's disconnected.
 * During the ban, if the player reconnects using a different identity but is still recognized by the banlist, some of his info is saved
 * (like aliases).
 * 
 * Bans are indexed by GUIDs, and at run-time an hashtable for IPs is built.
 * 
 */

/**
 * \brief Plugin Bans class.
 * This class contains the methods and properties forming the bans plugin. It manages bans on the server, permanent and temporary.
 * 
 * This class also implements a new event listener, named bans, bound on the Ban method prefix. It defines 4 events :
 * \li new($id, $banID) : When a player is banned. Parameters : the client ID which is banned, and the ban ID.
 * \li drop($id, $banID) : When a ban on a player is dropped. Parameters : the client ID which was banned, and the ban ID.
 * \li exitnew($banID) : When a player is banned after a deconnection. Parameters : the ban ID.
 * \li connect($id, $banID) : When a banned player connects to the server. Parameters : the banned client ID, and the ban ID.
 */
class PluginBans extends Plugin
{
	private $_banlist; ///< Ban database in memory
	private $_lastDisconnect; ///< Last disconnection
	private $_bannedIP; ///< Hashtable of banned IPs
	private $_bannedGUID; ///< Hashtable of banned GUIDs
	private $_durations = array('second' => 1,
								'minute' => 60,
								'hour' => 3600,
								'day' => 86400,
								'week' => 604800,
								'month' => 2592000,
								'year' => 31104000); ///< Duration equivalents in seconds.
	
	const GLOBALBAN = "all"; ///< Common name used to ban from all servers at once.
	
	/** Initialization of the plugin.
	 * This function initializes the plugin, by checking the existence of the banlist and loading it, or if it doesn't exists, it attempts to
	 * create it. If banlist creation fails, an error is emitted, but the plugin still load.
	 * 
	 * \return TRUE if the banlist correctly loaded, FALSE otherwise.
	 */
	public function init()
	{
		$this->_banlist = array();
		$this->_lastDisconnect = array();
		$this->_bannedIPs = array();
		$this->_bannedGUID = array();
		
		$this->_plugins->addEventListener('bans', 'Ban');
		
		//Setting command rights
		$this->setCommandLevel('ban', 10);
		$this->setCommandLevel('banexit', 10);
		
		//Default duration
		if(isset($this->config['DefaultDuration']))
			$this->config['DefaultDuration'] = $this->_parseBanDuration($this->config['DefaultDuration']);
		
		//Checking banlist existence or if we can create it
		if(!isset($this->config['Banlist']) || (!is_file($this->_main->getConfigLocation().'/'.$this->config['Banlist']) && !touch($this->_main->getConfigLocation().'/'.$this->config['Banlist'])))
		{
			Leelabot::message("Can't load banlist. Bans will not be saved.", array(), E_WARNING);
			return TRUE;
		}
		
		//Loading the banlist
		$this->config['Banlist'] = $this->_main->getConfigLocation().'/'.$this->config['Banlist'];
		$this->loadBanlist();
		
		return TRUE;
	}
	
	/** Event when player info is received.
	 * This function is triggered as an event for the server event ClientUserinfo. It checks on the banlist if there is a banned IP or GUID,
	 * and kicks the player if an active ban is found. Before kicking, ban duration is checked and the ban is deleted if it expired.
	 * If a ban is found and some informations are different, according informations will be updated in the banlist.
	 * 
	 * \param $id Player's ID.
	 * \param $userinfo Player's user info, in which we will get IP and GUID.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventClientUserinfo($id, $userinfo)
	{
		//Disabling ban check for bots.
		if(isset($userinfo['characterfile']))
			return;
		
		$ip = explode(':', $userinfo['ip']);
		$ip = $ip[0];
		
		//We check the GUID ban
		if(isset($this->_bannedGUID[$userinfo['cl_guid']]))
		{
			foreach($this->_bannedGUID[$userinfo['cl_guid']] as $ban)
			{
				if($this->_checkBanKick($id, $ip, $ban, $userinfo['cl_guid']))
					break;
			}
			return;
		}
		
		//We check the exact IP ban
		if(isset($this->_bannedIP[$ip]))
		{
			foreach($this->_bannedIP[$ip] as $ban)
			{
				if($this->_checkBanKick($id, $ip, $ban, $userinfo['cl_guid']))
					break;
			}
			return;	
		}
		
		//We check the mask IP ban
		$ip = explode('.', $ip);
		array_pop($ip);
		$ip = join('.', $ip).'.0';
		
		if(isset($this->_bannedIP[$ip]))
		{
			foreach($this->_bannedIP[$ip] as $ban)
			{
				if($this->_checkBanKick($id, $ip, $ban, $userinfo['cl_guid']))
					break;
			}
			return;
		}
	}
	
	/** Checks if the ban is still valid and perform necessited on it if necessary.
	 * This function checks the time since the connectiong player has been banned, and if not, it kicks him from the server. If the ban is
	 * over, it is deleted from the banlist, and the banlist is saved.
	 * 
	 * \param $id The player's ID, for kicking him if necessary.
	 * \param $ip The player's IP.
	 * \param $banID The ban UUID (from the database).
	 * \param $newguid The new GUID for the banned player (the one get from the ClientUserinfo).
	 * 
	 * \return TRUE if the player has been kicked, FALSE otherwise.
	 */
	private function _checkBanKick($id, $ip, $banID, $newguid)
	{
		$ban = $this->_banlist[$banID];
		$player = Server::getPlayer($id);
		
		//We check if it's an alias and get the root GUID
		if(isset($ban['Refer']))
			$ban = $this->_banlist[$ban['Refer']];
		
		//We check if the ban is valid on this server
		$servername = Server::getName() == self::GLOBALBAN ? 'server:'.self::GLOBALBAN : Server::getName();
		if($ban['Realm'] != self::GLOBALBAN && !in_array($servername, explode(',',$ban['Realm'])))
			return FALSE;
		
		//We check that the ban is over
		if($ban['Duration'] != 'forever' && time() > ($ban['Duration'] + $ban['Begin']))
		{
			unset($this->_bannedIP[$ban['IP']][array_search($banID, $this->_bannedIP[$banIP])]);
			if(empty($this->_bannedIP[$ban['IP']]))
				unset($this->_bannedIP[$ban['IP']]);
			
			$guidList = $ban['GUIDList'];
			foreach($guidList as $currentGUID)
			{
				unset($this->_bannedGUID[$currentGUID][array_search($banID, $this->_bannedGUID[$currentGUID])]);
				if(empty($this->_bannedGUID[$currentGUID]))
					unset($this->_bannedGUID[$currentGUID]);
			}
			
			$this->_plugins->callEventSimple('bans', 'drop', $id, $banID); //Calling the event
			unset($this->_banlist[$banID]);
			
			$this->saveBanlist();
			return FALSE;
		}
		
		//The ban is still active, so we adjust the ban data and we kick the player
		if(!in_array($newguid, $ban['GUIDList']))
		{
			$this->_banlist[$banID]['GUIDList'][] = $newguid;
			$this->_bannedGUID[$newguid] = $banID;
			$this->saveBanlist();
		}
		
		//We save the IP, only if the duration is over one day
		if($ip != $ban['IP'] && $ban['Duration'] > $this->_durations['day'])
		{
			$this->_bannedIP[$ip] = $this->_bannedIP[$ban['IP']];
			$this->_banlist[$banID]['IP'] = $ip;
			unset($this->_bannedIP[$ban['IP']]);
			$this->saveBanlist();
		}
		
		Leelabot::message('Client $0 is banned from server $1, kicked.', array($id, Server::getName()));
		$this->_plugins->callEventSimple('bans', 'connect', $id, $banID); //Calling the event
		RCon::kick($id);
		
		return TRUE;
	}
	
	/** Registers the last disconnection data.
	 * This function puts in memory the last disconnected player, for a possible later use by the function PluginBans::CommandBanExit().
	 * 
	 * \param $id The disconnecting client ID.
	 * 
	 * \return Nothing.
	 */
	public function SrvEventClientDisconnect($id)
	{
		$this->_lastDisconnect[Server::getName()] = Server::getPlayer($id);
	}
	
	/** Bans a player.
	 * This command bans the player passed in first parameter, and parses the other parameters to add modifiers to the ban :
	 * \li from <server(s)> : Specifies the range of the ban : servers can be defined one by one by separating them with a space,
	 * and the special keyword in PluginBans::GLOBALBAN can be used to make the ban bot-wide (if a server is named like that, you can use
	 * server:<PluginBans::GLOBALBAN> to bypass that).
	 * \li for <duration> : Specifies the duration of the ban. You can use numbers and modifiers to specify the duration (valid modifiers : 
	 * second(s), minute(s), hour(s), day(s), week(s), month(s), year(s)).
	 * \li forever : Makes the ban permanent. Can't be used with the "for" parameter.
	 * 
	 * If none parameter specified, the defaults from config file are taken. If a default duration or range is not specified in the config, 
	 * the default applied are 1 day and the current server from which the command has been emitted.
	 * 
	 * Examples : 
	 * \li Simple ban : !ban linkboss
	 * \li Ban from only one server : !ban linkboss from myserver
	 * \li Ban from multiple servers, including one server name <PluginBans::GLOBALBAN> : !ban linkboss from myserver, otherserver,
	 * 		server:<PluginBans::GLOBALBAN>
	 * \li Ban from all servers : !ban linkboss from <PluginBans::GLOBALBAN>
	 * \li Ban for 1 month and a half : !ban linkboss for 1 month 2 weeks
	 * \li Ban from one server during 1 day : !ban linkboss from myserver for 1 day
	 * \li Permanent ban : !ban linkboss forever
	 * 
	 * \note
	 * When you specify the ban duration, if you use 2 numbers one after another, their values will be additionned. For example :
	 * \li !ban linkboss for 1 2 months
	 * Will result of a 3 months ban. Also, if you use a non-recognized keyword between durations, only the last before a recognized keyword
	 * will be used. For example :
	 * \li !ban linkboss for 1 and 2 months
	 * Will result of a 2 months ban. If you do not use any modifier at all, the default modifier applied is the day.
	 * 
	 * \param $id The player ID who used the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 */
	public function CommandBan($id, $command)
	{
		$ban = $this->_parseBanCommand($command);
		
		$error = FALSE;
		if($ban['duration'] != 'forever' && $ban['duration'] <= 0)
			$error = 'Cannot ban for this duration.';
		
		if(empty($ban['servers']))
			$error = 'Cannot ban from zero servers.';
		
		if(empty($ban['banned']))
			$error = 'There is nobody to ban.';
		
		if($error !== FALSE)
		{
			RCon::tell($id, $error);
			return;
		}
		
		$banned = array();
		foreach($ban['banned'] as $player)
		{
			$pid = Server::searchPlayer($player);
			if(is_array($pid))
			{
				RCon::tell('Multiple results found for search $0 : $1', array($player, Server::getPlayerNames($pid)));
				continue;
			}
			elseif($pid === FALSE)
			{
				RCon::tell('No player found for search $0.', array($player));
				continue;
			}
			
			//Adding the entry to the banlist
			$player = Server::getPlayer($pid);
			$banID = Leelabot::UUID();
			if($ban['duration'] !== 'forever' && $ban['duration'] < $this->_durations['day'] && count($ban['servers']) == 1) //GUID save
				$this->_banlist[$banID] = array('GUIDList' => array($player->guid), 'IP' => FALSE, 'Aliases' => array($player->name), 'Duration' => $ban['duration'], 'Begin' => time(), 'Realm' => join(',', $ban['servers']), 'Identifier' => $player->name, 'Description' => '');
			elseif($ban['duration'] !== 'forever' && $ban['duration'] < $this->_durations['month']) //GUID + complete IP save
			{
				$this->_banlist[$banID] = array('GUIDList' => array($player->guid), 'IP' => $player->ip, 'Aliases' => array($player->name), 'Duration' => $ban['duration'], 'Begin' => time(), 'Realm' => join(',', $ban['servers']), 'Identifier' => $player->name, 'Description' => '');
				
				if(!isset($this->_bannedIP[$player->ip]))
					$this->_bannedIP[$player->ip] = array();
				
				$this->_bannedIP[$player->ip][] = $banID;
			}
			else //GUID + IP mask /24 save
			{
				$ip = explode('.', $player->ip);
				array_pop($ip);
				$ip = join('.', $ip).'.0';
				$this->_banlist[$banID] = array('GUIDList' => array($player->guid), 'IP' => $ip, 'Aliases' => array($player->name), 'Duration' => $ban['duration'], 'Begin' => time(), 'Realm' => join(',', $ban['servers']), 'Identifier' => $player->name, 'Description' => '');
				
				if(!isset($this->_bannedIP[$player->ip]))
					$this->_bannedIP[$player->ip] = array();
				
				$this->_bannedIP[$ip] = $banID;
			}
			//Adding the alias for better GUID search
			if(!isset($this->_bannedGUID[$player->guid]))
				$this->_bannedGUID[$player->guid] = array();
			
			$this->_bannedGUID[$player->guid][] = $banID;
		
			//We save the banlist and we kick the player
			$this->saveBanlist();
			
			//We call the event
			$this->_plugins->callEventSimple('bans', 'new', $player->id, $banID);
			
			if(in_array(Server::getName(), $ban['servers']) || $ban['servers'][0] == self::GLOBALBAN) //NEED TEST, CALL APERTURE SCIENCE
				RCon::kick($player->id);
		}
	}
	
	/** Bans the last disconnected player.
	 * This function bans the last player who disconnected from the server, with all the options available from the regular ban command.
	 * 
	 * \param $id The player ID who used the command.
	 * \param $command The command parameters.
	 * 
	 * \return Nothing.
	 * 
	 * \see PluginBans::CommandBan()
	 */
	public function CommandBanExit($id, $command)
	{
		//If no player has disconnected yet, we send an error
		if(!isset($this->_lastDisconnect[Server::getName()]))
		{
			RCon::tell($id, 'No player found.');
			return FALSE;
		}
		
		$ban = $this->_parseBanCommand($command);
		
		//Adding the entry to the banlist
		$player = $this->_lastDisconnect[Server::getName()];
		$banID = Leelabot::UUID();
		if($ban['duration'] !== 'forever' && $ban['duration'] < $this->_durations['day'] && count($ban['servers']) == 1)
			$this->_banlist[$banID] = array('GUIDList' => array($player->guid), 'IP' => FALSE, 'Aliases' => array($player->name), 'Duration' => $ban['duration'], 'Begin' => time(), 'Realm' => join(',', $ban['servers']), 'Identifier' => $player->name, 'Description' => '');
		elseif($ban['duration'] !== 'forever' && $ban['duration'] < $this->_durations['month'])
		{
			$this->_banlist[$banID] = array('GUIDList' => array($player->guid), 'IP' => $player->ip, 'Aliases' => array($player->name), 'Duration' => $ban['duration'], 'Begin' => time(), 'Realm' => join(',', $ban['servers']), 'Identifier' => $player->name, 'Description' => '');
			
			if(!isset($this->_bannedIP[$player->ip]))
				$this->_bannedIP[$player->ip] = array();
				
			$this->_bannedIP[$player->ip][] = $banID;
		}
		else
		{
			$ip = explode('.', $player->ip);
			array_pop($ip);
			$ip = join('.', $ip).'.0';
			$this->_banlist[$banID] = array('GUIDList' => array($player->guid), 'IP' => $ip, 'Aliases' => array($player->name), 'Duration' => $ban['duration'], 'Begin' => time(), 'Realm' => join(',', $ban['servers']), 'Identifier' => $player->name, 'Description' => '');
			
			if(!isset($this->_bannedIP[$player->ip]))
				$this->_bannedIP[$player->ip] = array();
				
			$this->_bannedIP[$ip] = $banID;
		}
		
		//Adding the alias for better GUID search
		if(!isset($this->_bannedGUID[$player->guid]))
			$this->_bannedGUID[$player->guid] = array();
		
		$this->_bannedGUID[$player->guid] = $banID;
		
		//We save the banlist and we kick the player
		$this->saveBanlist();
		
		$this->_plugins->callEventSimple('bans', 'exitnew', $banID); //Calling the event
	}
	
	/** Parses a ban command to get ban infos.
	 * This function takes an array of parameters like the ones given to the !ban command and parses them to get needed infos for the ban,
	 * according to the specifications of the command PluginBans::CommandBan()
	 * 
	 * \param $command the command parameters to parse.
	 * 
	 * \return An array containing the ban infos.
	 */
	private function _parseBanCommand($command)
	{
		$baninfo = array('players' => array(), 'from' => array(), 'for' => array(), 'permanent' => FALSE);
		
		$category = 'players';
		//Parsing command
		foreach($command as $param)
		{
			if($param == 'from' || $param == 'for')
				$category = $param;
			elseif($param == 'forever')
				$baninfo['permanent'] = TRUE;
			else
				$baninfo[$category][] = $param;
		}
		
		//Setting default realm if not present
		if(empty($baninfo['from']) && isset($this->config['DefaultRealm']) && $this->config['DefaultRealm'] == 'server')
			$baninfo['from'][] = self::GLOBALBAN;
		elseif(empty($baninfo['from']))
			$baninfo['from'][] = Server::getName();
		
		//Setting default duration if not present
		$duration = $this->_parseBanDuration(join(' ', $baninfo['for']));
		if($duration == -1 && isset($this->config['DefaultDuration']))
			$duration = $this->config['DefaultDuration'];
		elseif($duration == -1)
			$duration = $this->_durations['day'];
		
		if(!$baninfo['permanent'])
			$return = array('banned' => $baninfo['players'], 'servers' => $baninfo['from'], 'duration' => $duration);
		else
			$return = array('banned' => $baninfo['players'], 'servers' => $baninfo['from'], 'duration' => 'forever');
		
		return $return;
	}
	
	/** Parses a ban duration.
	 * This function takes a ban duration as exprimed by the administrator (in literal form), and transforms it into seconds, allowing the bot
	 * to use it with timestamps. For the rules of duration definition, see PluginBans::CommandBan().
	 * 
	 * \param $duration The duration of the ban, in literal form.
	 * 
	 * \return The duration equivalent in seconds, or if the parsing failed, it returns -1.
	 * 
	 * \see PluginBans::CommandBan()
	 */
	private function _parseBanDuration($duration)
	{
		if(empty($duration))
			return -1;
		
		$duration = explode(' ', $duration);
		$seconds = 0;
		$multiplier = 0;
		
		//Parsing duration elements
		foreach($duration as $el)
		{
			if(is_numeric($el))
				$multiplier += $el;
			elseif(is_string($el))
			{
				$el = rtrim($el, 's');
				if(isset($this->_durations[$el]))
					$seconds += $multiplier * $this->_durations[$el];
				$multiplier = 0;
			}
		}
		
		if($multiplier && $seconds == 0)
			$seconds = $multiplier * $this->_durations['day'];
		
		return $seconds;
	}
	
	/** Web admin main page for the plugin.
	 * This functions is triggered by the web admin when accessing the bans plugin area in the webadmin.
	 * It will show a list of the last bans, along with the current banning stats.
	 * 
	 * \param $data The data from the browser
	 * 
	 * \return The page body
	 */
	public function WAPageIndex($data)
	{
		$parser = Webadmin::getTemplateParser();
		$banlist = array();
		foreach($this->_banlist as $guid => $ban)
		{
			
			$banlist[$guid] = $ban;
			//$banlist[$guid]['End'] = $ban['Duration'] != 'forever' ? ($ban['Begin'] + $ban['Duration']) : Locales::translate('Forever');
			$banlist[$guid]['Duration'] = $ban['Duration'] != 'forever' ? $this->_getDurationHumanRepresentation($ban['Duration']) : Locales::translate('Forever');
			if($ban['Realm'] == self::GLOBALBAN)
				$banlist[$guid]['Realm'] = Locales::translate('Everywhere');
			else
				$banlist[$guid]['Realm'] = str_replace('server:'.self::GLOBALBAN, self::GLOBALBAN, $ban['Realm']);
			
			if(!$ban['Description'])
				$banlist[$guid]['Description'] = Locales::translate('None');
			else
				$banlist[$guid]['Description'] = $ban['Description'];
		}
		
		$parser->assign('banlist', $banlist);
		$parser->assign('lastbans', $this->_extractBanlistSlice($banlist));
		
		return $parser->draw('plugins/bans');
	}
	
	/** Web admin page : Reloads the plugin list from the file.
	 * This function reload the plugin list from the file set in the config file. 
	 * 
	 * \param $data The data from the browser
	 * 
	 * \return The query result, i.e. an error message if an error occured or "ok" if the reload was successful.
	 */
	public function WAPageReload($data)
	{
		Webadmin::disableDesign(); //We disable the design, because of AJAX
		Webadmin::disableCache();
		if($this->loadBanlist())
			return "success:";
		else
			return "error:".Leelabot::lastError();
	}
	
	/** Gets the human representation of a duration exprimed in seconds.
	 * This function translates a period of time exprimed in seconds, usable by the bot, into a human-readable version.
	 * 
	 * \param $seconds Number of seconds to translate.
	 * 
	 * \return A string containing the human-readable version of the input.
	 */
	private function _getDurationHumanRepresentation($seconds)
	{
		$dNames = array_keys($this->_durations);
		$out = '';
		while($seconds != 0)
		{
			$last = 'second';
			foreach($dNames as $name)
			{
				if($seconds / $this->_durations[$name] < 1)
				{
					$t = floor($seconds / $this->_durations[$last]);
					$seconds -= $t * $this->_durations[$last];
					$out .= ' '.$t.' '.$last;
					if($t > 1)
						$out .= 's';
					break;
				}
				else
					$last = $name;
			}
		}
		
		return substr($out, 1);
	}
	
	/** Extract a slice of the banlist, by time.
	 * This function sorts the banlist by time from last to first, and extracts a slice of it.
	 * 
	 * \parameter $banlist The banlist to sort.
	 * \parameter $start The start of the slice.
	 * \parameter $end The end of the slice.
	 * 
	 * \return The slice of the banlist.
	 */
	private function _extractBanlistSlice($banlist, $start = 0, $end = 5)
	{
		//Combo sort the bans
		$swapped = false;
		$gap = $size = count($banlist);
		$keys = array_keys($banlist);
		while (($gap > 1) || $swapped)
		{
			if ($gap > 1)
				$gap = ($gap / 1.247330950103979);
 
			$swapped = false;
			
			for($i = 0; $gap + $i < $size; $i++)
			{
				if($banlist[$keys[$i]]['Begin'] - $banlist[$keys[$i + $gap]]['Begin'] < 0)
				{
					$swap = $banlist[$keys[$i]];
					$banlist[$keys[$i]] = $banlist[$keys[$i + $gap]];
					
					$banlist[$keys[$i + $gap]] = $swap;
					$swapped = true;
				}
			}
	    }
		
	    //And we return the desired slice
	    return array_slice($banlist, $start, $end);
	}
	
	/** Loads the banlist.
	 * This function takes the file pointed by Banlist parameter from the config and loads the banlist from it. It also indexes IPs
	 * for a more efficient and less time-eating search for bans.
	 * 
	 * \return TRUE if the banlist loaded correctly, FALSE otherwise.
	 */
	public function loadBanlist()
	{
		$contents = file_get_contents($this->config['Banlist']);
		
		if($contents === FALSE)
		{
			Leelabot::message('Can\'t load banlist : $0', array(Leelabot::lastError()), E_WARNING);
			return FALSE;
		}
		
		$this->_banlist = Leelabot::parseINIStringRecursive($contents);
		
		if(!$this->_banlist)
			$this->_banlist = array();
		
		$this->_bannedIP = array();
		foreach($this->_banlist as $banID => $ban)
		{
			
			if(isset($ban['IP']))
			{
				if(!isset($this->_bannedIP[$ban['IP']]))
					$this->_bannedIP[$ban['IP']] = array();
				$this->_bannedIP[$ban['IP']][] = $banID;
			}
		}
		
		$this->_bannedGUID = array();
		foreach($this->_banlist as $banID => $ban)
		{
			foreach($ban['GUIDList'] as $guid)
			{
				if(!isset($this->_bannedGUID[$guid]))
					$this->_bannedGUID[$guid] = array();
				$this->_bannedGUID[$guid][] = $banID;
			}
		}
		
		return TRUE;
	}
	
	/** Saves the banlist.
	 * This function takes the banlist in memory and put it into the banlist file specified in the configuration, after having it transformed
	 * into recursive INI mode.
	 * 
	 * \return TRUE if the banlist saved correctly, FALSE otherwise.
	 */
	public function saveBanlist()
	{
		$dump = Leelabot::generateINIStringRecursive($this->_banlist);
		return file_put_contents($this->config['Banlist'], $dump);
	}
}

$this->addPluginData(array(
'name' => 'bans',
'className' => 'PluginBans',
'display' => 'Bans plugin',
'autoload' => TRUE));
