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
	
class RCon
{
	private $_rcon;
	private $_server;
	private static $_instance;
	
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
	}
	
	
	public static function setServer(&$server)
	{
		$self = self::getInstance();
		$self->_server = $server;
		
		list($addr, $port) = $self->_server->getAddress();
		$rcon = $self->_server->getRConPassword();
		$self->_rcon->setServer($addr, $port);
		$self->_rcon->setRConPassword($rcon);
	}
	
	public static function setQueryClass(&$class)
	{
		$self = self::getInstance();
		$self->_rcon = $class;
	}
	
	public static function send($rcon)
	{
		$self = self::getInstance();
		return $self->_rcon->RCon($rcon);
	}
	
	public static function getReply($timeout = FALSE)
	{
		$self = self::getInstance();
		return $self->_rcon->getReply($timeout);
	}
	
	public static function test($timeout = 5)
	{
		$self = self::getInstance();
		return $self->_rcon->test($timeout);
	}
	
	public static function lastError()
	{
		$self = self::getInstance();
		return $self->_rcon->lastError();
	}
	
	public static function getErrorString($error)
	{
		$self = self::getInstance();
		return Leelabot::$instance->intl->translate($self->_rcon->getErrorString($error));
	}
	
	public static function say($message)
	{
		self::send('say "^7'.$message.'"');
	}
	
	public static function __callStatic($command, $arguments)
	{
		self::send($command.' '.join(' ', $arguments));
	}
	
	public static function blueTeamList()
	{
		if(!self::send('g_blueteamlist'))
			return FALSE;
		
		$ret = self::getReply();
		
		$ret = explode(':', $ret);
		if($ret[0] == 'broadcast')
			return array();
		
		//Getting content of g_blueteamlist
		$ret = explode('"', $ret[1]);
		$list = str_replace('^7', '', $ret[1]);
		$list = str_split($list);
		
		//Transforming upper letters (ABCD...) in numbers
		foreach($list as &$el)
			$el = ord($el) - 65;
		
		return $list;
	}
	
	public static function redTeamList()
	{
		if(!self::send('g_redteamlist'))
			return FALSE;
		
		$ret = self::getReply();
		
		$ret = explode(':', $ret);
		if($ret[0] == 'broadcast')
			return array();
		
		//Getting content of g_blueteamlist
		$ret = explode('"', $ret[1]);
		$list = str_replace('^7', '', $ret[1]);
		$list = str_split($list);
		
		//Transforming upper letters (ABCD...) in numbers
		foreach($list as &$el)
			$el = ord($el) - 65;
		
		return $list;
	}
	
	public static function status()
	{
		if(!self::send('status'))
			return FALSE;
		
		$status = array('map' => '', 'players' => array());
		$ret = self::getReply();
		
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
	
	public static function dumpUser($id)
	{
		if(!self::send('dumpuser '.$id))
			return FALSE;
		
		$ret = self::getReply();
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
	
	public static function serverInfo()
	{
		if(!self::send('serverinfo'))
			return FALSE;
		
		$ret = self::getReply();
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

class Server
{
	private $_server;
	private static $_instance;
	
	const TEAM_RED = 1;
	const TEAM_BLUE = 2;
	const TEAM_SPEC = 3;
	
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
	}
	
	public static function setServer(&$server)
	{
		$self = self::getInstance();
		$self->_server = $server;
	}
	
	public static function searchPlayer($search)
	{
		
	}
	
	public static function getGametype($gametype = FALSE)
	{
		if(!$gametype)
		{
			$self = self::getInstance();
			$gametype = $self->_server->serverInfo['g_gametype'];
		}
		
		switch($gametype)
		{
			case 0:
			case 1:
			case 2:
				return 'Free for All';
			case 3:
				return 'Team Deathmatch';
			case 4:
				return 'Team Survivor';
			case 5:
				return 'Follow the Leader';
			case 6:
				return 'Capture and Hold';
			case 7:
				return 'Capture The Flag';
			case 8:
				return 'Bomb';
			default:
				return 'Unknown';
		}
	}
}
