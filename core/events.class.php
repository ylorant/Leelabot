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
	protected $_events = array(); ///< Events storage
	protected $_eventLevels = array(); ///< Event levels storage
	protected $_autoMethods = array(); ///< Method prefixes for automatic event recognition
	
	const UNDEFINED_LISTENER = 1; ///< Undefined event listener
	const UNDEFINED_EVENT = 2; ///< Undefined event name
	const ACCESS_DENIED = 3; ///< The entity calling the event does not have sufficient level to call it.
	
	/** Adds a custom event listener, with its auto-binding method prefix.
	 * This function adds a new event listener to the event system. It allows a plugin to create his own space of events, which it cans trigger after, allowing better
	 * and easier interaction between plugins.
	 * 
	 * \param $name The name the listener will get.
	 * \param $autoMethodPrefix The prefix there will be used by other plugins for automatic method binding.
	 * 
	 * \return TRUE if the listener was correctly created, FALSE otherwise.
	 */
	public function addEventListener($name, $autoMethodPrefix)
	{
		if(isset($this->_events[$name]))
		{
			Leelabot::message('Error : Already defined Event Listener: $0', $name, E_DEBUG);
			return FALSE;
		}
		
		$this->_events[$name] = array();
		$this->_eventLevels[$name] = array();
		$this->_autoMethods[$name] = $autoMethodPrefix;
		
		return TRUE;
	}
	
	/** Deletes an event listener.
	 * This functions deletes an event listener from the event system. The underlying events for this listener will be deleted as well.
	 * 
	 * \param $name The listener's name
	 * 
	 * \return TRUE if the listener has been deleted succesfully, FALSE otherwise.
	 */
	public function deleteEventListener($name)
	{
		if(!isset($this->_events[$name]))
		{
			Leelabot::message('Error : Undefined Event Listener: $0', $name, E_DEBUG);
			return FALSE;
		}
		
		unset($this->_events[$name]);
		unset($this->_events[$name]);
		
		return TRUE;
	}
	
	/** Adds an event to an event listener.
	 * This function adds an event to an anlready defined event listener. The callback linked to the event will be later distinguished of the others by an identifier
	 * which must be unique in the same event.
	 * 
	 * \param $listener The listener in which the event will be added. Must be defined when adding the event.
	 * \param $id The callback identifier. Must be unique in the same event, can be duplicated across events.
	 * \param $event The event to which the callback will be linked.
	 * \param $callback The callback that will be called when the event is triggered.
	 * \param $level The level for which the event will be triggered. If the event already exists, the level is updated only if it is higher
	 * than the precedent one defined.
	 * 
	 * \return TRUE if the event added correctly, FALSE otherwise.
	 */
	public function addEvent($listener, $id, $event, $callback, $level = 0)
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
		
		if($this->_events[$listener][$event] < $level)
			$this->_events[$listener][$event] = $level;
		
		return TRUE;
	}
	
	/** Deletes an event from the event listener.
	 * This function deletes an already defined callback (bound to an event) from the event listener, so it will not be called by event triggering again.
	 * 
	 * \param $listener The event listener from which the callback will be deleted.
	 * \param $event The event name (from which the callback is triggered).
	 * \param $id The callback's ID.
	 * 
	 * \return TRUE if the event deleted correctly, FALSE otherwise.
	 */
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
		
		if(empty($this->_events[$listener][$event]))
		{
			unset($this->_events[$listener][$event]);
			unset($this->_eventLevels[$listener][$event]);
		}
		
		return TRUE;
	}
	
	/** Calls an event for an event listener.
	 * This function calls the callbacks for the given event name. It will execute the callbacks by giving them for parameters the additionnal parameter given to this
	 * method.
	 * For example, calling callEvent like that : $this->callEvent('somelistener', 'connect', 1, 'hi'); will call the callbacks bound to the event "connect" with 2
	 * parameters : 1 and 'hi'.
	 * 
	 * \param $listener The listener from which the event will be called.
	 * \param $event The event to call.
	 * \param $level The level of the entity who is executing the command.
	 * 
	 * \return An error code relating to the error type occured, or TRUE if no error occured.
	 */
	public function callEvent($listener, $event, $level = 0)
	{
		if(!isset($this->_events[$listener]))
		{
			Leelabot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return Events::UNDEFINED_LISTENER;
		}
		
		if(!isset($this->_events[$listener][$event]) || !$this->_events[$listener][$event])
			return Events::UNDEFINED_EVENT;
		
		if($level < $this->_eventLevels[$listener][$event])
			return Events::ACCESS_DENIED;
		
		//Get additionnal parameters given to the method to give them to the callbacks
		$params = func_get_args();
		array_shift($params);
		array_shift($params);
		
		//Calling back
		foreach($this->_events[$listener][$event] as $id => $callback)
			call_user_func_array($callback, $params);
		
		return TRUE;
	}
	
	/** List the available events for a listener.
	 * This function lists all the available events for the given listener.
	 * 
	 * \param $listener The listener from which list the events.
	 * 
	 * \return An array containing the wanted event list, with event names in keys and event levels in valuesy.
	 */
	public function listEvents($listener)
	{
		if(!isset($this->_events[$listener]))
		{
			Leelabot::message('Error: Undefined Event Listener: $0', $listener, E_DEBUG);
			return FALSE;
		}
		
		$list = array();
		foreach($this->_events[$listener] as $event => $callbacks)
			$list[$event] = $this->_eventLevels[$listener][$event];
		
		return $list;
	}
	
	/** Returns the list of defined events on a listener.
	 * This function returns all the events defined for an event listener.
	 * 
	 * \param $listener The listener where we'll get the events.
	 * 
	 * \return The list of the events from the listener.
	 */
	public function getEventsNames($listener)
	{
		return array_keys($this->_events[$listener]);
	}
	
	/** Sets the level of an event.
	 * This functions sets the level required to use this event.
	 * \param $listener The listener in which the event is located.
	 * \param $event The event's name.
	 * \param $level The new level.
	 * 
	 * \return TRUE if the level changed correctly, FALSE otherwise.
	 */
	public function setEventLevel($listener, $event, $level)
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
		
		$this->_eventLevels[$listener][$event] = $level;
		return TRUE;
	}
	
	/** Returns the level of an event.
	 * This function returns the required level set to execute the event.
	 * 
	 * \param $listener The listener in which the event is located.
	 * \param $event The event's name.
	 * 
	 * \return The event's level if found, or FALSE if not found.
	 */
	public function getEventLevel($listener, $event)
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
		
		return $this->_eventLevels[$listener][$event];
	}
}
