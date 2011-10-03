<?php
/**
 * \file lib/moap/moapclient.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief MOAPClient class file.
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
 * This is the file containing the MOAP client class.
 */
 
/**
 * \brief MOAP Client class.
 * 
 * This class allows the user to create easily a MOAP client (stands for Minimalist Object Access Protocol), a subset of SOAP, but really easier.
 * Any call of a function which is not defined will be sended as a method access to the server.
 */
class MOAPClient
{
	private $_url; ///< URL of the server
	private $_host; ///< Server host
	private $_port; ///< Server port
	private $_path; ///< API path
	private $_timeout = 5; ///< Timeout for replies.
	
	/** Class constructor.
	 * This function simply needs an argument (the access url for the server), and it just stores it for use.
	 * 
	 * \param $url The address of the server.
	 * 
	 * \return FALSE if the URL is not correct, the object otherwise.
	 */
	public function __construct($url)
	{
		if(filter_var($url, FILTER_VALIDATE_URL))
		{
			preg_match('#http://([^/:]+)(:[0-9]+)?(/.+)?#',$url, $matches);
			$this->_host = $matches[1];
			$this->_port = substr($matches[2], 1) ? substr($matches[2], 1) : 80;
			$this->_path = !empty($matches[3]) ? $matches[3] : '/';
			$this->_url = $url;
		}
		else
			return FALSE;
	}
	
	/** Sets the timeout for the replies.
	 * This function sets the time the class will wait when sending a command before closing the connection.
	 * 
	 * \param $timeout The timeout to wait. Defaults to 5s.
	 * 
	 * \return Nothing.
	 */
	public function _setTimeout($timeout)
	{
		$this->_timeout = $timeout;
	}
	
	/** Method overloader, for server function call.
	 * This magic method is called when we try to access to a non-existent method of the class. The name of the called method and its parameters will be sent to
	 * the MOAP server.
	 * 
	 * \param $funcname The function name.
	 * \param $arguments array containing the arguments.
	 * 
	 * \returns The remote method reply if sended and replied, FALSE otherwise.
	 */
	public function __call($funcname, $arguments)
	{
		$data = 'request='.$funcname;
		
		foreach($arguments as $i => $value)
			$data .= '&param'.$i.'='.urlencode($value);
		
		$socket = fsockopen($this->_host, $this->_port);
		stream_set_timeout($socket, $this->_timeout); 
		
		fputs($socket, 
		'POST '.$this->_path.' HTTP/1.1'."\r\n".
		'Host: '.$this->_host."\r\n".
		'User-Agent: MOAPClient/1.0'."\r\n".
		"\r\n".$data);
		
		$time = time();
		$reply = NULL;
		while($line = fgets($socket))
			$reply .= $line;
		
		if($reply == NULL)
			return FALSE;
		
		$reply = explode("\r\n\r\n", $reply);
		
		return $reply[1];
	}
}
