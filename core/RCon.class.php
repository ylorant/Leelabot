<?php

/**
 * \file core/RCon.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Quake 3 RCon access class file.
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
 * This file hosts the RCon class, allowing to send RCon commands to a server.
 */

/**
 * \brief Quake 3 RCon access class for leelabot.
 * 
 * This class manages access to RCon for all servers defined in Leelabot. Since the RCon protocol for Quake 3 uses UDP connectionless commands, the same class
 * is used to query all servers. It just needs to call method RCon::changeServer() to change the IP address to query.
 */ 
class Quake3RCon
{
	private $_addr; ///< Current server address.
	private $_port; ///< Current server port.
	private $_compactBuffer; ///< Compacting method activity.
	private $_password; ///< RCon password.
	private $_valid; ///< Indicates the servers where the connection has already been verified.
	private $_socket; ///< UDP socket for handling connection to the server.
	private $_lastRConTime; ///< Temps auquel à été envoyé la dernière commande RCon
	private $_error; ///< Last error encountered by the class.
	private $_lastCommand; ///< Last RCon command sent.
	private $_waiting; ///< Toggle on waiting between successive RCon commands.
	
	const E_CONNECTION = 1; ///< Can't send data on the server.
	const E_BADRCON = 2; ///< Bad RCon password.
	const E_NOREPLY = 3; ///< No reply from the server.
	const E_NORCON = 4; ///< No RCon available on the server (i.e. no rconpassword)
	
	public function __construct()
	{
		$this->_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_nonblock($this->_socket);
		$this->_lastRConTime = microtime(TRUE) + 0.5;
		$this->_valid = array();
		$this->_error = 0;
	}
	
	/** Sets the gameserver address.
	 * This function sets the address of the server that the bot wants to query. The validity of the address will be verified, but not if a server responds.
	 * This will be verified only at runtime.
	 * 
	 * \param $addr The server's address.
	 * \param $port The server's port.
	 * 
	 * \return TRUE if address set properly, FALSE if not (address invalid).
	 */
	public function setServer($addr, $port)
	{
		//Checking port regularity
		if($port && ($port < 0 || $port > 65535))
			return FALSE;
		
		if($addr)
			$this->_addr = $addr;
		if($port)
			$this->_port = $port;
		if(($addr || $port) && !isset($this->_valid[$addr.':'.$port]))
			$this->_valid[$addr.':'.$port] = FALSE;
			
		return TRUE;
	}
	
	/** Sets the compacting method activity.
	 * This function sets if the compacting method for sending RCon commands will be active or not. The compacting method consists of writing all the commands
	 * in a config file, then executing this file.
	 * 
	 * \param $compact Compacting the commands or not. Defaults to FALSE.
	 * 
	 * \return TRUE if the value set correctly, FALSE if it is not a boolean.
	 */
	public function setCompactBuffer($compact = FALSE)
	{
		if(is_bool($compact))
			$this->_compactBuffer = $compact;
		else
			return FALSE;
		
		return TRUE;
	}
	
	/** Sets the RCon password.
	 * This function sets the RCon password which will be used for server commands.
	 * 
	 * \param $password The password.
	 * 
	 * \return Nothing.
	 */
	public function setRConPassword($password)
	{
		$this->_password = $password;
	}
	
	public function setWaiting($wait)
	{
		$this->_waiting = (bool)$wait;
	}
	
	/** Tests the connectivity between the server and the bot.
	 * This function tests if there is a responsive server at the other end, and if the RCon password specified is valid.
	 * 
	 * \param $timeout The timeout for the test. Defaults to 5.
	 * 
	 * \return TRUE if the server is responsive and the RCon password valid, FALSE otherwise.
	 */
	public function test($timeout = 5)
	{
		$this->_valid[$this->_addr.':'.$this->_port] = FALSE;
		
		$sleep = floor(($this->_lastRConTime + 0.5 - microtime(true) + 0.01) * 1000000);
		if($sleep > 0)	
			usleep($sleep);
		
		$rcon = str_repeat(chr(255), 4).'rcon '.$this->_password." status\n";
		if(!socket_sendto($this->_socket, $rcon, strlen($rcon), 0, $this->_addr, $this->_port))
		{
			$this->_error = self::E_CONNECTION;
			return FALSE;
		}
		
		$this->_lastRConTime = microtime(TRUE);
		
		if(!$data = $this->getReply($timeout))
			return FALSE;
		
		$this->_valid[$this->_addr.':'.$this->_port] = TRUE;
		return TRUE;
	}
	
	/** Sends a RCon command.
	 * This function sends a RCon command to the set gameserver. If the server has not been tested yet, it will be tested
	 * before sending the command.
	 * 
	 * \param $command The command to send.
	 * 
	 * \return TRUE if the command sent correctly, FALSE otherwise.
	 */
	public function RCon($command)
	{
		if($this->_waiting)
			$interval = 0.5;
		else
			$interval = 0.18;
		
		$sleep = floor(($this->_lastRConTime + $interval - microtime(true) + 0.01) * 1000000);
		if($sleep > 0)	
			usleep($sleep);
		
		
		$this->_lastRConTime = microtime(TRUE);
		$this->_lastCommand = $command;
		$rcon = 'rcon '.$this->_password.' '.$command;
		return $this->send($rcon);
	}
	
	/** Re-sends the last RCon command.
	 * This function re-sends the last sent RCon command to the set gameserver. Normal RCon function call applies then.
	 * 
	 * \return The RCon function return, for the last command.
	 */
	public function resend()
	{
		return $this->RCon($this->_lastCommand);
	}
	
	/** Sends a direct command.
	 * This function sends a direct command to the server, without the "rcon" prefix. If the server has not been tested yet,
	 * it will be tested before sending the command (if not overridden by parameter).
	 * 
	 * \param $command The command to send.
	 * \param $test Wether test the connection before or not. Defaults to TRUE.
	 * 
	 * \return TRUE if the command sent correctly, FALSE otherwise.
	 */
	public function send($command, $test = TRUE)
	{
		if(!$this->_valid[$this->_addr.':'.$this->_port] && $test)
		{
			if(!$this->test())
				return FALSE;
		}
		
		$data = str_repeat(chr(255), 4).$command."\n";
		return (socket_sendto($this->_socket, $data, strlen($data), 0, $this->_addr, $this->_port) !== FALSE);
	}
	
	/** Waits a RCon reply from the server.
	 * This function waits a RCon reply from the set gameserver. If the parameter timeout is given, it will wait a reply the specified numbers of seconds. If not, it
	 * will directly return FALSE, after a short wait (there is always a short timeout, because of network latences).
	 * 
	 * \param $timeout The time to wait the reply.
	 * 
	 * \return A string containing the server's reply if success, FALSE otherwise.
	 */
	public function getReply($timeout = FALSE)
	{
		$time = time();
		$data = '';
		$microtime = microtime(TRUE) + 0.05; //Set the micro-timeout at 50ms
		while($time + $timeout >= time())
		{
			if($tmp = socket_read($this->_socket, 1024))
				$data .= $tmp;
			elseif($data)
				break;
			if($timeout === FALSE && !$data && $microtime < microtime(TRUE))
			{
				$this->_error = self::E_NOREPLY;
				return FALSE;
			}
			usleep(2000);
		}
		
		if($time + $timeout < time())
		{
			$this->_error = self::E_NOREPLY;
			return FALSE;
		}
		
		//Remove the header (\xFF\xFF\xFF\xFFprint\n) from reply
		$data = trim(str_replace(str_repeat(chr(255), 4)."print\n", '',$data));
		
		if($data == "Bad rconpassword.")
		{
			$this->_error = self::E_BADRCON;
			return FALSE;
		}
		elseif($data == "No rconpassword set on the server.")
		{
			$this->_error = self::E_NORCON;
			return FALSE;
		}
		
		return $data;
	}
	
	/** Gets last error occured on the server.
	 * This function returns an integer for the last error that occured.
	 * 
	 * \return The last error code, or 0 if no error occured yet.
	 */
	public function lastError()
	{
		return $this->_error;
	}
	
	/** Gets a short description on an error.
	 * This function returns astring containing a short description of the given error code.
	 * 
	 * \param $error The error code on which description is wanted.
	 * 
	 * \return The descriptive string for the error code, or FALSE if the error is not recognized.
	 */
	public function getErrorString($error)
	{
		switch($error)
		{
			case self::E_CONNECTION:
				return "Can't send data to the server.";
			case self::E_BADRCON:
				return "Bad RCon password.";
			case self::E_NOREPLY:
				return "No reply from the server.";
			case self::E_NORCON:
				return "No RCon available on the server.";
			default:
				return FALSE;
		}
	}
}
