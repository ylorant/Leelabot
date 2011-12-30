<?php

/**
 * \file core/events.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Events class file.
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
 * This file hosts the Events class, handling all events.
 */

/**
 * \brief Events class for leelabot.
 * 
 * \warning For server events and commands, the manager will only handle 1 callback by event at a time. It is done for simplicity purposes, both at plugin's side
 * and at manager's side (I've noticed that it is not necessary to have multiple callbacks for an unique event, unless you can think about getting your code clear)
 */
class Events
{
	protected $_events = array();
	protected $_eventsID = array();
	protected $_autoMethods = array();
	
	public function addEventListener($name, $autoMethodPrefix)
	{
		if(isset($this->_events[$name]))
		{
			Leelabot::message('Error : Invalid Event Listener: $0', $name, E_DEBUG);
			return FALSE;
		}
		
		$this->_events[$name] = array();
		$this->_autoMethods[$name] = $autoMethodPrefix;
		
		return TRUE;
	}
	
	public function deleteEventListener($name)
	{
		unset($this->_events[$name]);
		
		return TRUE;
	}
	
	public function addEvent($listener, $id, $event, $callback)
	{
		if(!isset($this->_events[$listener]))
		{
			Leelabot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_events[$listener][$event]))
			$this->_events[$listener][$event] = array();
		
		if(isset($this->_events[$listener][$event][$id]))
		{
			Leelabot::message('Error: Already defined identifier: $0', $id, E_DEBUG);
			return FALSE;
		}
		
		if(!method_exists($callback[0], $callback[1])) //Check if method exists
		{
			Leelabot::message('Error : Target method does not exists.', array(), E_DEBUG);
			return FALSE;
		}
		
		$this->_events[$listener][$event][$id] = $callback;
		
		return TRUE;
	}
	
	public function deleteEvent($listener, $event, $id)
	{
		if(!isset($this->_events[$listener]))
		{
			Leelabot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_events[$listener][$event]))
		{
			Leelabot::message('Error: Undefined Event: $0', $id, E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_events[$listener][$event][$id]))
		{
			Leelabot::message('Error: Already defined identifier: $0', $id, E_DEBUG);
			return FALSE;
		}
		
		unset($this->_events[$listener][$event][$id]);
		
		return TRUE;
	}
	
	public function getEvents($listener)
	{
		return array_keys($this->_events[$listener]);
	}
	
	public function callEvent($listener, $event)
	{
		if(!isset($this->_events[$listener]))
		{
			Leelabot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_events[$listener][$event]) || !$this->_events[$listener][$event])
			return FALSE;
		
		//Get additionnal parameters given to the method to give them to the callbacks
		$params = func_get_args();
		array_shift($params);
		array_shift($params);
		
		//Calling back
		foreach($this->_events[$listener][$event] as $id => $callback)
			call_user_func_array($callback, $params);
		
		return TRUE;
	}
}
