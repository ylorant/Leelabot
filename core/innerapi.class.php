<?php

/**
 * \file core/instance.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief InnerAPI class file.
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
 * This file hosts the Inner API, letting the access to server instances and RCon easier. For convenance, i've chosen to use multiple classes instead of one big class.
 * It eases the comprehension of the code by splitting methods in "categories", represented by classes with clear names. These classes are of course accessed statically,
 * and the current server you are querying with these classes is changed automatically at each event for the correct one (but there is also some methods to dialog to
 * other servers).
 */

/**
 * \brief RCon simple access class.
 * 
 * This class allows access to the RCon with a relative ease. All this class is made to be called statically, so don't try to instanciate an object wit it (except in special cases, like when you want to access other servers than the current server.
 * This class is automatically set to the current server, i.e. The server from which we are reading events.
 * Thanks to its __callStatic magic method, you can send a RCon command on the server just by calling RCon::somerconcommand($parameter, $parameter...), for example
 * RCon::kick('all'), for kicking all players.
 */
class RCon
{
	private $_rcon; ///< Reference to Quake3RCon class
	private $_server; ///< Reference to the server class.
	private static $_instance; ///< Auto reference to the class (to make a static singleton).
	
	/** Returns the instance of the class.
	 *This function returns the auto-reference to the singleton instance of the class. It should not be called by other classes.
	 * 
	 * \return The auto-reference to the singleton.
	 */
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
	}
	
	/** Sets the current server to query.
	 * This function sets the data for the RCon class using the $server ServerInstance object given in argument, permitting it to query the good server without
	 * giving its address, port or even its ServerInstance class at any time.
	 * 
	 * \param $server A reference of the server to set.
	 * 
	 * \return Nothing.
	 */
	public static function setServer(&$server)
	{
		$self = self::getInstance();
		$self->_server = $server;
		
		list($addr, $port) = $self->_server->getAddress();
		$rcon = $self->_server->getRConPassword();
		$self->_rcon->setServer($addr, $port);
		$self->_rcon->setRConPassword($rcon);
	}
	
	/** Gets the current server to query.
	 * This function returns the current server set for the class. It is useful for restoring the server when doing one-time RCon command sending, but it is better
	 * to use ServerList::getServerRCon().
	 * 
	 * \return The current set server.
	 */
	public static function getServer()
	{
		$self = self::getInstance();
		return $self->_server;
	}
	
	/** Sets the Quake3RCon object.
	 * This function sets the Quake3RCon object which will be used by the class to query the game server.
	 * 
	 * \param $object The object to use.
	 * 
	 * \return Nothing.
	 */
	public static function setQueryClass(&$object)
	{
		$self = self::getInstance();
		$self->_rcon = $object;
	}
	
	/** Sends a RCon query to the game server.
	 * This function sends a RCon query to the gameserver, using the object previously bound to the class.
	 * 
	 * \param $rcon The query to send.
	 * 
	 * \return The result given by the Quake3RCon class.
	 */
	public static function send($rcon)
	{
		$self = self::getInstance();
		return $self->_rcon->RCon($rcon);
	}
	
	/** Waits and get a reply from the game server.
	 * This function waits to get a reply to the server. If a timeout is given, it will wait the time wanted, but if the timeout is not specified, it will return
	 * almost immediately (for network purposes, a 50ms wait is imposed, to "fight" the latence, but sometimes it is not sufficient, likely if you're using a 
	 * saerver in outer space or something like that).
	 * 
	 * \param $timeout The timeout to wait.
	 * 
	 * \return The game server's reply.
	 */
	public static function getReply($timeout = FALSE)
	{
		$self = self::getInstance();
		return $self->_rcon->getReply($timeout);
	}
	
	/** Tests the connectivity to the server.
	 * This function tests the connectivity with the server by sending a status command to it and waiting a reply. The test fails if the wait time is over
	 * (by default, 5 seconds), or if the server replies by an error (Bad password, no password set).
	 * 
	 * \param $timeout	The timeout to wait for the test. It can be useful to raise this time if you're dialing a very far server or if your internet connection is 
	 * 					really creepy. Defaults to 5 seconds.
	 * 
	 * \return TRUE if the test succeeded, FALSE otherwise.
	 */
	public static function test($timeout = 5)
	{
		$self = self::getInstance();
		return $self->_rcon->test($timeout);
	}
	
	/** Get last error from the Quake3RCon class.
	 * This function returns the last error code given by the Quake3RCon class.
	 * 
	 * \return The last error code.
	 */
	public static function lastError()
	{
		$self = self::getInstance();
		return $self->_rcon->lastError();
	}
	
	/** Get the error string associated with an error code.
	 * This function returns the error string for a code returned by Quake3RCon::lastError(), or for any given code.
	 * 
	 * \param $error Quake3RCon Error code.
	 * 
	 * \return The string associated with the code, or FALSE if the code does not correspond to any error.
	 */
	public static function getErrorString($error)
	{
		$self = self::getInstance();
		return Leelabot::$instance->intl->translate($self->_rcon->getErrorString($error));
	}
	
	/** Sends a "say" message to the server.
	 * This functions sends a "say" message to the server, visible by everyone in the chat space.
	 * 
	 * \param $message The message to send. It will be parsed like in Leelabot::message().
	 * \param $args The arguments of the message, for value replacement. Like in Leelabot::message().
	 * \param $translate Boolean indicating if the method has to translate the message before sending. Default : TRUE.
	 * 
	 * \return Nothing.
	 * \see RCon::tell()
	 */
	public static function say($message, $args = array(), $translate = TRUE)
	{
		//Parsing message vars
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);
		
		if($translate)
			$message = Leelabot::$instance->intl->translate($message);
		
		Leelabot::message('Reply message (say) : $0', array($message), E_DEBUG);
		
		return self::send('say "^7'.$message.'"');
	}
	
	/** Sends a "tell" message to the server.
	 * This functions sends a "tell" message to the server, visible by just one player in the chat space.
	 * 
	 * \param $player The id of the player to send the message.
	 * \param $message The message to send. It will be parsed like in Leelabot::message().
	 * \param $args The arguments of the message, for value replacement. Like in Leelabot::message().
	 * \param $translate Boolean indicating if the method has to translate the message before sending. Default : TRUE.
	 * 
	 * \return Nothing.
	 * \see RCon::say()
	 */
	public static function tell($player, $message, $args = array(), $translate = TRUE)
	{
		//Parsing message vars
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);
		
		if($translate)
			$message = Leelabot::$instance->intl->translate($message);
		
		Leelabot::message('Reply message (tell) : $0', array($message), E_DEBUG);
		
		return self::send('tell '.$player.' "^7'.$message.'"');
	}
	
	/** Shortcut to all RCon commands.
	 * This method is called when an inexistent method is called. Its sole action is to send as RCon command the method name called, with the arguments joined, to
	 * make a coherent RCon query. It is a shortcut made to avoid the creation of many methods with the same body.
	 * 
	 * \param $command The RCon command that will be sended (the called method's name).
	 * \param $arguments The list of arguments to that command (the called method's arguments).
	 * 
	 * \return The reply of RCon::send().
	 */
	public static function __callStatic($command, $arguments)
	{
		return self::send($command.' '.join(' ', $arguments));
	}
	
	/** Shortcut to all RCon commands (for instance mode).
	 * This method is called when an inexistent method is called. Its sole action is to send as RCon command the method name called, with the arguments joined, to
	 * make a coherent RCon query. It is a shortcut made to avoid the creation of many methods with the same body.
	 * 
	 * \param $command The RCon command that will be sended (the called method's name).
	 * \param $arguments The list of arguments to that command (the called method's arguments).
	 * 
	 * \return The reply of RCon::send().
	 */
	public function __call($command, $arguments)
	{
		return $this->send($command.' '.join(' ', $arguments));
	}
	
	/** Lists the player in the blue team.
	 * This function lists the players in the blue team, by reading the content of the g_blueteamlist var. If nobody has been in the blue team yet (since the map
	 * start), this command will fail.
	 * 
	 * \return An array containing the IDs of the blue team's players.
	 * \see RCon::redTeamList()
	 */
	public static function blueTeamList()
	{
		if(!self::send('g_blueteamlist'))
			return FALSE;
		
		if(!($ret = self::getReply()))
			return FALSE;
		
		$ret = explode(':', $ret);
		if($ret[0] == 'broadcast')
			return array();
		
		//Getting content of g_blueteamlist
		$ret = explode('"', $ret[1]);
		$list = str_replace('^7', '', $ret[1]);
		$list = str_split($list);
		
		//Str split does not returns an empty array for an empty string, so we have to verify this ourselves.
		if(count($list) == 1 && !$list[0])
			return array();
		
		//Transforming upper letters (ABCD...) in numbers
		foreach($list as &$el)
			$el = ord($el) - 65;
		
		return $list;
	}
	
	/** Lists the player in the red team.
	 * This function lists the players in the red team, by reading the content of the g_redteamlist var. If nobody has been in the red team yet (since the map
	 * start), this command will fail.
	 * 
	 * \return An array containing the IDs of the red team's players.
	 * \see RCon::blueTeamList()
	 */
	public static function redTeamList()
	{
		if(!self::send('g_redteamlist'))
			return FALSE;
		
		if(!($ret = self::getReply()))
			return FALSE;
		
		$ret = explode(':', $ret);
		if($ret[0] == 'broadcast')
			return array();
		
		//Getting content of g_blueteamlist
		$ret = explode('"', $ret[1]);
		$list = str_replace('^7', '', $ret[1]);
		$list = str_split($list);
		
		//Str split does not returns an empty array for an empty string, so we have to verify this ourselves.
		if(count($list) == 1 && !$list[0])
			return array();
		
		//Transforming upper letters (ABCD...) in numbers
		foreach($list as &$el)
			$el = ord($el) - 65;
		
		return $list;
	}
	
	/** This function gets the status of the server.
	 * This function gets the data from the command "status", contaning player list with some data (ID, IP, name...), and the map's name.
	 * 
	 * \return An array containing the map name (key "map") and a sub-array of the players (key "players").
	 */
	public static function status()
	{
		if(!self::send('status'))
			return FALSE;
		
		$status = array('map' => '', 'players' => array());
		if(!($ret = self::getReply()))
			return FALSE;
		
		$ret = explode("\n", $ret);
		$status['map'] = trim(str_replace('map:', '', $ret[0]));
		
		//Remove map name and player list header to get only the player list to loop with a foreach
		for($i = 0; $i < 3; $i++) array_shift($ret);
		
		foreach($ret as $player)
		{
			$player = explode(' ', trim(preg_replace('# +#', ' ',$player)));
			$status['players'][$player[0]] = array(
			'id' => $player[0],
			'score' => $player[1],
			'ping' => $player[2],
			'name' => preg_replace('#\^[0-9]#', '', $player[3]),
			'lastmsg' => $player[4],
			'address' => $player[5],
			'qport' => $player[6],
			'rate' => $player[7]);
		}
		
		return $status;
	}
	
	/** Gets the data of one player.
	 * This function gets the data of one user, by sending the "dumpuser" command, and parse it to return an associative array contaning the data.
	 * 
	 * \param $id The ID of the player to dump.
	 * 
	 * \return An associative array of all the data gathered by the command.
	 */
	public static function dumpUser($id)
	{
		if(!self::send('dumpuser '.$id))
			return FALSE;
		
		if(!($ret = self::getReply()))
			return FALSE;
		$ret = explode("\n", $ret);
		
		//Delete the header, useless for the treatment
		for($i = 0; $i < 2; $i++) array_shift($ret);
		
		$user = array();
		foreach($ret as $info)
		{
			$info = explode(' ', preg_replace('# +#', ' ',$info), 2);
			$user[$info[0]] = $info[1];
		}
		
		return $user;
	}
	
	/** Gets all the general server info.
	 * This function gets all the "general" server informations (excluding specific players' info), by executing the RCon command "serverinfo". It works basically
	 * like RCon::dumpUser() because the data is presented in the same way (it has to do the same things to get the organised array).
	 * 
	 * \return An associative array of all the servers' vars/data.
	 */
	public static function serverInfo()
	{
		if(!self::send('serverinfo'))
			return FALSE;
		
		if(!($ret = self::getReply()))
			return FALSE;
		$ret = explode("\n", $ret);
		
		//Delete the header, useless for the treatment
		array_shift($ret);
		
		$serverInfo = array();
		foreach($ret as $info)
		{
			$info = explode(' ', preg_replace('# +#', ' ',trim($info)), 2);
			$serverInfo[$info[0]] = $info[1];
		}
		
		return $serverInfo;
	}
}

/**
 * \brief Holds the server related methods.
 * This class holds all the unique server methods and constants (like teams, gametypes and flag statuses). The methods used in this class are mainly converters methods
 * (except the methods to set/get the plugins, name and class).
 */
class Server
{
	private $_server; ///< Current server instance object
	private static $_instance; ///< Auto-reference for static singleton
	
	//Teams ID
	const TEAM_RED = 1; ///< Red team ID
	const TEAM_BLUE = 2; ///< Blue team ID
	const TEAM_SPEC = 3; ///< Spectators' ID
	
	//Gametypes IDs
	const GAME_FFA = 1; ///< Free For All
	const GAME_TDM = 3; ///< Team Deathmatch
	const GAME_TS = 4; ///< Team Survivor
	const GAME_FTL = 5; ///< Follow The Leader
	const GAME_CNH = 6; ///< Capture and Hold
	const GAME_CTF = 7; ///< Capture the Flag
	const GAME_BOMB = 8; ///< Bomb mode
	
	const FLAG_DROP = 0; ///< Flag drop
	const FLAG_RETURN = 1; ///< Flag return
	const FLAG_CAPTURE = 2; ///< Flag capture
	
	/** Returns the instance of the class.
	 *This function returns the auto-reference to the singleton instance of the class. It should not be called by other classes.
	 * 
	 * \return The auto-reference to the singleton.
	 */
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
	}
	
	/** Sets the current server to query.
	 * This function sets the data for the RCon class using the $server ServerInstance object given in argument, permitting it to query the good server without
	 * giving its address, port or even its ServerInstance class at any time.
	 * 
	 * \param $server A reference of the server to set.
	 * 
	 * \return Nothing.
	 */
	public static function setServer(&$server)
	{
		$self = self::getInstance();
		$self->_server = $server;
	}
	
	/** Gets the current server to query.
	 * This function returns the current server set for the class.
	 * 
	 * \return The current set server.
	 */
	public static function getServer()
	{
		$self = self::getInstance();
		return $self->_server;
	}
	
	/** Returns the server's active plugins
	 * This function returns the server's active plugins, i.e. the plugins that will be processed each time an event is triggered  (or also for routines).
	 *
	 * \return An array containing the server's plugins list.
	 */
	public static function getPlugins()
	{
		$self = self::getInstance();
		return $self->_server->getPlugins();
	}
	
	/** Returns the server's name.
	 * This function returns the current server's name, as set in the configuration.
	 * 
	 * \return The current server's name.
	 */
	public static function getName()
	{
		$self = self::getInstance();
		return $self->_server->getName();
	}
	
	/** Search player(s) on the server.
	 * This function searches player(s) on the server, corresponding to the given mask. If only one player is found, the value returned is the sole ID of the
	 * player (an int). 
	 * 
	 * \param $search The search mask, a regular expression.
	 * 
	 * \return The result(s). If only one player is found, only it's ID is returned. If multiple players are found, an array containing all the IDs is returned.
	 */
	public static function searchPlayer($search)
	{
		$self = self::getInstance();
		
		$matches = array();
		foreach($self->_server->players as $player)
		{
			if(preg_match('/'.str_replace('/', '\/', $search).'/', $player->name) !== FALSE)
				$matches[] = $player->id;
		}
		
		//If there is only one match, we return it instead of an array.
		if(count($matches) == 1)
			return $matches[0];
		elseif(count($matches))
			return $matches;
		else
			return FALSE;
	}
	
	/** Returns the game type name.
	 * This function returns the game type name, in a readable string, from the gametype number given in argument, or if not given, with the current server's
	 * gametype of the server.
	 * 
	 * \param $gametype The wanted gametype number. If not given, the current server's gametype is taken.
	 * 
	 * \return A string containing the gmetype name.
	 */
	public static function getGametype($gametype = FALSE)
	{
		if($gametype === FALSE)
		{
			$self = self::getInstance();
			$gametype = $self->_server->serverInfo['g_gametype'];
		}
		
		switch($gametype)
		{
			case self::GAME_TDM:
				return 'Team Deathmatch';
			case self::GAME_TS:
				return 'Team Survivor';
			case self::GAME_FTL:
				return 'Follow the Leader';
			case self::GAME_CNH:
				return 'Capture and Hold';
			case self::GAME_CTF:
				return 'Capture The Flag';
			case self::GAME_BOMB:
				return 'Bomb';
			default:
				return 'Free for All';
		}
	}
	
	/** Returns the team's name of a team or a player.
	 * This function returns the team name for a given team number or a given player data object.
	 * 
	 * \param $team A team number, or a player's data. if it is player data, it will return the team name corresponding with the 'team' property of that object.
	 * 
	 * \return A string containing the requierd team name.
	 */
	public static function getTeamName($team)
	{
		if(is_object($team))
			$team = $team->team;
		
		switch($team)
		{
			case self::TEAM_RED:
				return 'Red';
			case self::TEAM_BLUE:
				return 'Blue';
			case self::TEAM_SPEC:
				return 'Spectator';
			default:
				return FALSE;
		}
	}
	
	/** Returns the team number corresponding to a name.
	 * This function returns the team number corresponding with the name given.
	 * 
	 * \param $teamname The team name.
	 * 
	 * \return The team number, or FALSE if the name is not recognized.
	 */
	public static function getTeamNumber($teamname)
	{
		switch(strtolower($teamname))
		{
			case 'red':
				return self::TEAM_RED;
			case 'blue':
				return self::TEAM_BLUE;
			case 'spec':
			case 'spectator':
				return self::TEAM_SPEC;
			default:
				return FALSE;
		}
	}
	
	/** Returns the player count of each team.
	 * This function returns the player count for each team, in an array.
	 * 
	 * \param $players Array of player data. If not given, the current server's players will be used for counting.
	 * 
	 * \return An array containing the player count for each team.
	 */
	public static function getTeamCount($players = FALSE)
	{
		$count = array(NULL, 0, 0, 0);
		
		if($players === FALSE)
		{
			$self = self::getInstance();
			$players = $self->_server->players;
		}
		
		foreach($players as $player)
			$count[$player->team]++;
		
		return $count;
	}
	
	/** Parses the info given by the log.
	 * This function parses the data given by the server, in the server events InitGame, ClientUserInfo and the others.
	 * 
	 * \param $info The information to parse.
	 * 
	 * \return The info in an associative array.
	 */
	public static function parseInfo($info)
	{
		$out = array();
		
		//Splitting the different parts of data
		$info = explode('\\', $info);
		
		//The data starting with a backslash sometimes, we must shift the array of the first element if it is empty.
		if(empty($info[0]))
			array_shift($info);
		
		for($i = 0; isset($info[$i]); ++$i)
			$out[$info[$i]] = $info[++$i];
		
		return $out;
	}
	
	/** Returns the player data for an ID.
	 * This function returns the player data for the given ID.
	 * 
	 * \param $id The ID of the player to get data from.
	 * 
	 * \return A Storage object of the player data. If the player does not exists, it returns NULL.
	 */
	public static function getPlayer($id)
	{
		$server = self::getInstance();
		return isset($server->_server->players[$id]) ? $server->_server->players[$id] : NULL;
	}
	
	/** Returns the player list for the server.
	 * This function returns the player data list for the server, or for only a team.
	 * 
	 * \param $team The team to get. If not given or incorrect (it does not correpond to team constants), all the players are returned.
	 * 
	 * \return The player data list, in an array.
	 */
	public static function getPlayerList($team = FALSE)
	{
		$server = self::getInstance();
		
		if($team == FALSE || !in_array($team, array(self::TEAM_RED, self::TEAM_BLUE, self::TEAM_SPEC)))
			return $server->_server->players;
		else
		{
			$list = array();
			foreach($server->_server->players as &$player)
			{
				if($player->team == $team)
					$list[] = $player;
			}
		}
		
		return $list;
	}
	
	/** Sets a custom server var.
	 * This function sets a custom variable for the current server. Beware, the variables are not limited to your plugin, but are accessible in all plugins, so
	 * be aware of the other plugins' var names, to avoid overwrite.
	 * 
	 * \param $var Var name.
	 * \param $value Var value.
	 * 
	 * \return The set value.
	 */
	public static function set($var, $value)
	{
		$self = self::getInstance();
		return $self->_server->pluginVars[$var] = $value;
	}
	
	/** Gets a custom server var.
	 * This function returns a custom variable set for the current server.
	 * 
	 * \param $var Var name.
	 * 
	 * \returns The variable value, or null if the variable does not exists.
	 */
	public static function get($var)
	{
		$self = self::getInstance();
		return isset($self->_server->pluginVars[$var]) ? $self->_server->pluginVars[$var] : NULL;
	}
	
	/** Gets a file from the server.
	 * This function gets the contents of a file from the server, with the protocole set to read the log (so it also works on remote servers).
	 * 
	 * \param $file File name.
	 * 
	 * \return A string containing the file's contents, or FALSE if an error happened.
	 */
	public static function fileGetContents($file)
	{
		$self = self::getInstance();
		return $self->_server->fileGetContents($file);
	}
	
	/** Writes to a file on the server.
	 * This function writes the given string to a file on the server, using the appropriate writing method (so it also works on remote servers).
	 * 
	 * \param $file The file to write to.
	 * \param $content The content to write.
	 * 
	 * \return TRUE if the file wrote correctly, or FALSE if anything unexpected happened.
	 */
	public static function filePutContents($file, $contents)
	{
		$self = self::getInstance();
		return $self->_server->filePutContents($file, $contents);
	}
}

/**
 * \brief Multiple server access class.
 * This class allow any plugin to access to any server where the bot is connected. The plugins can use it to send queries and retrieve statuses from multiple servers
 * at a time.
 */
class ServerList
{
	private $_leelabot; ///< Reference to Leelabot class
	private static $_instance; ///< Self-reference to the class (to make a static singleton)
	
	/** Returns the instance of the class.
	 *This function returns the auto-reference to the singleton instance of the class. It should not be called by other classes.
	 * 
	 * \return The auto-reference to the singleton.
	 */
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
	}
	
	/** Sets the Leelabot class.
	 * This function sets the object that the class will call to get all server instances, a Leelabot class.
	 * 
	 * \param $class The class to set.
	 * 
	 * \return Nothing.
	 */
	public static function setLeelabotClass(&$class)
	{
		$self = self::getInstance();
		$self->_leelabot = $class;
	}
	
	/** Gets a RCon instance for the specified server.
	 * This function returns an instance of RCon (the same as the innerAPI one) for the specified server in argument.
	 * 
	 * \param $server The wanted server's name.
	 * 
	 * \return If the server exists, it returns an instance of RCon class. Else, it returns FALSE.
	 */
	public static function getServerRCon($server)
	{
		$self = self::getInstance();
		if(!isset($self->_leelabot->servers[$server]))
			return FALSE;
		
		$rcon = new RCon();
		$Q3RCon = new Quake3RCon();
		$rcon->setQueryClass($Q3RCon);
		$rcon->setServer($self->_leelabot->servers[$server]);
		
		return $rcon;
	}
	
	/** Gets a Server class instance for the specified server.
	 * This function returns an instance of Server (the same as the innerAPI one) for the specified server in argument.
	 * 
	 * \param $server The wanted server's name.
	 * 
	 * \return If the server exists, it returns an instance of Server class. Else, it returns FALSE.
	 */
	public static function getServer($server)
	{
		$self = self::getInstance();
		if(!isset($self->_leelabot->servers[$server]))
			return FALSE;
		
		$instance = new Server();
		$instance->setServer($self->_leelabot->servers[$server]);
		
		return $instance;
	}
	
	/** Deletes a server from the bot.
	 * This function deletes a server from the bot. This server is of course deleted temporarily, the only way to do it permanently is to remove it from the config.
	 * 
	 * \param $name The server's name.
	 * 
	 * \return TRUE if the server deleted correctly, FALSE otherwise.
	 */
	public static function unload($name)
	{
		$self = self::getInstance();
		
		return $self->_leelabot->unloadServer($name);
	}
	
	
	public static function getList()
	{
		$self = self::getInstance();
		
		return array_keys($self->_leelabot->servers);
	}
}

/**
 * \brief Locales access class.
 * This class allows to use multiple locales at a time, by instanciating multiple objects of Intl class simultaneously. It loads the locales automatically, so just
 * the methods for locale listing/check and translation are available.
 */
class Locales
{
	private $_defaultIntl; ///< Default Intl object, from where locale objects will be cloned.
	private $_locales = array(); ///< Locales list.
	private static $_instance; ///< Auto-reference for static singleton
	
	/** Returns the instance of the class.
	 *This function returns the auto-reference to the singleton instance of the class. It should not be called by other classes.
	 * 
	 * \return The auto-reference to the singleton.
	 */
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
	}
	
	/** Inits the class.
	 * This function inits the class, by loading the default template object.
	 * 
	 * \returns Nothing.
	 */
	public static function init()
	{
		$self = self::getInstance();
		
		$self->_defaultIntl = new Intl();
	}
	
	/** Translates a message.
	 * This method translates a message from an identifier or English to another locale, loading if necessary new locale files.
	 * 
	 * \param $locale The destination locale name.
	 * \param $from The message to translate.
	 * 
	 * \return The translated message, or, if there was an error, the original message.
	 */
	public function translate($locale, $from)
	{
		$self = self::getInstance();
		
		$lcname = $self->_defaultIntl->localeExists($locale);
		if(!$lcname)
			return $from;
		else
		{
			if(!isset($self->_locales[$lcname]))
			{
				$self->_locales[$lcname] = clone $self->_defaultIntl;
				$self->_locales[$lcname]->setLocale($lcname);
			}
			
			return $self->_locales[$lcname]->translate($from);
		}
	}
	
	/** Lists available locales
	 * This function lists available locales.
	 * 
	 * \return An array containing the list of all available locales.
	 */
	public function getList()
	{
		$self = self::getInstance();
		return $self->_defaultIntl->getLocaleList();
	}
}

/**
 * \brief Plugin access class.
 * This class allow any plugin to access to the plugin manager, for getting other plugins' objects (to query them), or to load new plugins (although the dependency
 * manager is preferred).
 */
class Plugins
{
	private static $_plugins; ///< Internal reference to plugin manager
	
	/** Returns the current plugins.
	 * This function returns the list of all loaded plugins of the bot. Notice : the plugins returned may not be loaded on all servers, so be careful of that when
	 * using this method.
	 * 
	 * \return An array containing the list of currently loaded plugins.
	 */
	public static function getList()
	{
		return self::$_plugins->getLoadedPlugins();
	}
	
	/** Returns the object for a plugin.
	 * This function returns the instance object for a plugin. With it, you will be able to access public properties for it or query directly its methods.
	 * 
	 * \warning If the methods you are calling for the plugin use RCon commands, be sure that you send them on the good server.
	 * 
	 * \param $plugin The wanted plugin's name.
	 * 
	 * \return The plugins object, or NULL if the plugin does not exists.
	 */
	public static function get($plugin)
	{
		return self::$_plugins->getPlugin($plugin);
	}
	
	/** Loads a plugin.
	 * This function loads the asked plugin. 
	 * 
	 * \see PluginManager::loadPlugin()
	 */
	public static function load($plugin)
	{
		return self::$_plugins->loadPlugin($plugin);
	}
	
	/** Sets the plugin manager class.
	 * This function sets the plugin manager class, which will be called by the other methods.
	 * 
	 * \param $obj A reference to the plugin manager class.
	 * 
	 * \return Nothing.
	 */
	public static function setPluginManager(&$obj)
	{
		self::$_plugins = $obj;
	}
}
