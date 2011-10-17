<?php
/**
 * \file lib/moap/moapserver.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief MOAPServer class file.
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
 * This is the file containing the MOAP server class.
 */
 
/**
 * \brief MOAP Server class.
 * 
 * This class allows the user to create easily a MOAP server (stands for Minimalist Object Access Protocol), a subset of SOAP, but really easier.
 */
class MOAPServer
{
	private $_call; ///< The class name/The object to call when a query is received
	private $_defaultMethod = 'default'; ///< The default method to fall back where a request does not success.
	private $_aliases;
	private $_replyCallback; ///< Reply callback
	
	/** Sets the reply class.
	 * This function sets the class or the object that will be used for handling the queries.
	 * 
	 * \param $class The class/object that will be used.
	 * 
	 * \return TRUE if the class set correctly, FALSE otherwise.
	 */
	public function setClass($class)
	{
		if(is_object($class))
			$this->_call = &$class;
		elseif(is_string($class))
			$this->_call = $class;
		else
			return FALSE;
		
		return TRUE;
	}
	
	/** Sets the reply callback.
	 * This function sets the reply callback, which will be called by the class to send reply data.
	 * 
	 * \param $callback The callback to set.
	 * 
	 * \return Nothing.
	 */
	public function setReplyCallback($callback)
	{
		$this->_replyCallback = $callback;
	}
	
	/** Handles a MOAP request.
	 * This functions handles a MOAP request, from the parameters given in the $post parameter or, if not given, in the $_POST Superglobal.
	 * 
	 * \param $post The POST data to be processed
	 * 
	 * \return Nothing.
	 */
	public function handle($post = NULL)
	{
		if($post == NULL)
			$post = $_POST;
		
		//If there is no method specified, the default method is called (if it exists, else, nothing happens)
		if(empty($post['request']))
		{
			if(method_exists($this->_call, $this->_defaultMethod))
				$this->_callMethod($this->_defaultMethod, $post);
			
			return NULL;
		}
		else //A method to query is specified
			$this->_callMethod($post['request'], $post);
	}
	
	/** Calls a method, with given data.
	 * This function calls the method $method, and gives in parameter the data in $post.
	 * 
	 * \param $method The method to call.
	 * \param $post The POST data from where extract the method's arguments.
	 * 
	 * \returns Nothing.
	 */
	private function _callMethod($method, $post)
	{
		$args = array();
		foreach($post as $parname => $param)
		{
			if(preg_match('#param([0-9]+)#', $parname, $match)) //Check if it's a parameter
				$args[$match[1]] = $param;
		}
		
		$result = call_user_func_array(array($this->_call, $method), $args);
		call_user_func($this->_replyCallback, $result);
	}
}
