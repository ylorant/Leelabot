<?php

/**
 * \file core/plugins.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief PluginManager class file.
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
 * This file hosts the PluginManager class, handling all events and plugins. It hosts also the Plugin parent class, which is the model for plugins, and contains
 * also some shortcut methods.
 */

/**
 * \brief PluginManager class for leelabot.
 * 
 * This class manages all the plugins for leelabot, loading them, unloading them and reloading them. It handles also event managing on three levels :
 * 		\li Routines : executed independently from what the gamerserver sends, on a timed base, configurable.
 * 		\li Server events : They are executed when the gameserver sends the appropriate command.
 * 		\li Commands (also called client events) : They are executed when a client sends a command (beginning with a !)
 * It handles plugins dependency (semi-automatic) and automatic event loading.
 * 
 * \warning For server events and commands, the manager will only handle 1 callback by event at a time. It is done for simplicity purposes, both at plugin's side
 * and at manager's side (I've noticed that it is not necessary to have multiple callbacks for an unique event, unless you can think about getting your code clear)
 */
class PluginManager
{
	private $_main; ///<  Reference to main class
	private $_plugins; ///< Plugins list
	private $_routines = array(); ///< Routines list
	private $_serverEvents = array(); ///< Server events
	private $_commands = array(); ///< Commands
	
	/** Constructor for PluginManager
	 * Initializes the class.
	 * 
	 * \param $main A reference to the main program class (Leelabot class).
	 */
	public function __construct(&$main)
	{
		$this->_main = $main;
		$this->plugins = array();
	}
	
	/** Loads plugins from an array.
	 * This function loads plugins from an array, allowing to load multiple plugins in one time. Basically, it justs do a burst call of loadPlugin for all specified
	 * plugins, plus a load verification.
	 * 
	 * \param $list Plugin list to be loaded.
	 * \return TRUE if all plugins loaded successfully, FALSE otherwise.
	 */
	public function loadPlugins($list)
	{
		if(!is_array($list))
			return FALSE;
		
		Leelabot::message('Loading plugins : $0', array(join(', ', $list)));
		$loadedPlugins = array();
		foreach($list as $plugin)
		{
			if(!in_array($plugin, array_keys($this->plugins))) //Do not load twice the same plugin
			{
				$return = $this->loadPlugin($plugin);
				if($return !== FALSE)
					$loadedPlugins[] = $plugin;
				else
					return FALSE;
			}
		}
		
		return TRUE;
	}
	
	/** Loads one plugin.
	 * This function loads one plugin. It is a very basic function. It checks that plugin exists, and include its file. All the remaining work is done by
	 * PluginManager::initPlugin(), called inside the plugin file.
	 * 
	 * \param $plugin Plugin to be loaded.
	 * \return TRUE if plugin loaded successfully, FALSE otherwise. In fact, this value, beyond the "plugin not found" error, depends of the return value of 
	 * PluginManager::initPlugin.
	 */
	public function loadPlugin($plugin)
	{
		Leelabot::message('Loading plugin $0', array($plugin));
		
		if(!is_file('plugins/'.$plugin.'.php'))
		{
			Leelabot::message('Plugin $0 does not exists (File : $1)', array($plugin, getcwd().'/plugins/$0.php'), E_WARNING);
			return FALSE;
		}
		
		//Loading plugin file and creating class
		include('plugins/'.$plugin.'.php');
	}
	
	/** Initializes a plugin.
	 * This function is called from the plugin file, after the plugin class definition. It loads the plugin from the data given in argument, includes dependencies,
	 * loads the class, automatically binds the events to correctly named methods.
	 * 
	 * \param $params Loading parameters for the plugin. This associative array allows these values :
	 * 			\li \b name : The name to be used for the plugin (i.e. the file name). String.
	 * 			\li \b className : The main class to be instancied for the plugin. String.
	 * 			\li \b dependencies : The dependencies the plugin needs for functionning. Array.
	 * 			\li \b autoload : To let know if the function has also to do automatic binding of events. Boolean.
	 * \return TRUE if plugin initialized successfully, FALSE otherwise (and throws many warnings).
	 */
	public function initPlugin($params)
	{
		if(!is_array($params) || !isset($params['name']) || !isset($params['className']))
		{
			Leelabot::message('Cannot load plugin with given data : $0', array(Leelabot::dumpArray($params)), E_WARNING);
			return FALSE;
		}
		
		//Load dependencies if necessary
		if(isset($params['dependencies']) && is_array($params['dependencies']))
		{
			Leelabot::message('Loading plugin dependencies.');
			$ret = $this->loadPlugins($params['dependencies']);
			if(!$ret)
			{
				Leelabot::message('Cannot load plugin dependencies, loading aborted.', array(), E_WARNING);
				return FALSE;
			}
		}
		elseif(isset($params['dependencies']))
			Leelabot::message('Dependencies list is not an array.', array(), E_WARNING);
		
		$this->_plugins[$params['name']] = array(
		'obj' => NULL,
		'name' => $params['name'],
		'dependencies' => (isset($params['dependencies']) ? $params['dependencies'] : array()),
		'className' => $params['className']);
		$this->_plugins[$params['name']]['obj'] = new $params['className']($this, $this->_main);
		
		if(isset($params['autoload']) && $params['autoload'])
		{
			Leelabot::message('Using automatic events recognition...');
			$methods = get_class_methods($params['className']); //Get all class methods for plugin
		
			//Analyse all class methods
			foreach($methods as $method)
			{
				//Checks for routines
				if(preg_match('#^Routine#', $method))
					$this->addRoutine(&$this->_plugins[$params['name']]['obj'], $method);
				//Checks for server events
				if(preg_match('#^SrvEvent#', $method))
					$this->addServerEvent(preg_replace('#SrvEvent(.+)#', '$1', $method), $this->_plugins[$params['name']]['obj'], $method);
				//Checks from command (found commands are used in lower case)
				if(preg_match('#^Command#', $method))
					$this->addCommand(strtolower(preg_replace('#Command(.+)#', '$1', $method)), $this->_plugins[$params['name']]['obj'], $method);
			}
		}
		
		$this->_plugins[$params['name']]['obj']->init();
		Leelabot::message('Loaded plugin $0', array($params['name']));
		
		return TRUE;
	}
	
	/** Adds a routine to the event manager.
	 * This function adds a routine to the event manager, i.e. a function that will be executed every once in a while.
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be executed.
	 * \param $time The time interval between 2 executions of the routine. Defaults to 1.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function addRoutine(&$plugin, $method, $time = 1)
	{
		Leelabot::message('Adding routine $0, executed every $1s', array(get_class($plugin).'::'.$method, $time));
		
		if(!method_exists($plugin, $method)) //Check if method exists
		{
			Leelabot::message('Target method does not exists.', array(), E_WARNING);
			return FALSE;
		}
		
		$this->_routines[get_class($plugin)][$method] = $plugin;
		
		return TRUE;
	}
	
	/** Adds a server event to the event manager.
	 * This function adds a server event to the event manager.
	 * 
	 * \param $event The name of the event to be added (corresponds to server's event names).
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be executed.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function addServerEvent($event, &$plugin, $method)
	{
		Leelabot::message('Adding method $0, on server event $1', array(get_class($plugin).'::'.$method, $event));
		
		if(!method_exists($plugin, $method)) //Check if method exists
		{
			Leelabot::message('Target method does not exists.', array(), E_WARNING);
			return FALSE;
		}
		
		if(!isset($this->_serverEvents[$event]))
			$this->_serverEvents[$event] = array();
		
		$this->_serverEvents[$event][get_class($plugin)] = array($plugin, $method);
		
		return TRUE;
	}
	
	/** Adds a client event to the event manager.
	 * This function adds a client event to the event manager.
	 * 
	 * \param $event The name of the event to be added (corresponds to a command from user).
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be executed.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function addCommand($event, &$plugin, $method)
	{
		Leelabot::message('Adding method $0, on client command $1', array(get_class($plugin).'::'.$method, '!'.$event));
		
		if(!method_exists($plugin, $method)) //Check if method exists
		{
			Leelabot::message('Target method does not exists.', array(), E_WARNING);
			return FALSE;
		}
		
		if(!isset($this->_commands[$event]))
			$this->_commands[$event] = array();
		
		$this->_commands[$event][get_class($plugin)] = array($plugin, $method);
		
		return TRUE;
	}
	
	/** Deletes a routine.
	 * This function deletes a routine from the event manager.
	 * 
	 * \param $event The name of the event to be added (corresponds to a command from user).
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function deleteRoutine(&$plugin, $method)
	{
		Leelabot::message('Deleting routine $0', array(get_class($plugin).'::'.$method));
		
		if(!isset($this->_routines[get_class($plugin)]))
		{
			Leelabot::message('Plugin $0 does not exists in routine list.', array($event), E_WARNING);
			return FALSE;
		}
		
		if(!isset($this->_routines[get_class($plugin)][$method]))
		{
			Leelabot::message('Routine does not exists.', array(), E_WARNING);
			return FALSE;
		}
		
		unset($this->_routines[get_class($plugin)][$method]);
		
		return TRUE;
	}
	
	/** Deletes a server event.
	 * This function deletes a server event from the event manager.
	 * 
	 * \param $event The name of the event which to be deleted.
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function deleteServerEvent($event, &$plugin)
	{
		Leelabot::message('Deleting routine $0', array(get_class($plugin).'/'.$event));
		
		if(!isset($this->_serverEvents[$event]))
		{
			Leelabot::message('Event $0 does not exists.', array($event), E_WARNING);
			return FALSE;
		}
		
		if(!isset($this->_serverEvents[$event][get_class($plugin)]))
		{
			Leelabot::message('Method does not exists.', array(), E_WARNING);
			return FALSE;
		}
		
		unset($this->_serverEvents[$event][get_class($plugin)]);
		
		return TRUE;
	}
	
	/** Deletes a command.
	 * This function deletes a command from the event manager.
	 * 
	 * \param $event The name of the event which to be deleted.
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function deleteCommand($event, &$plugin)
	{
		Leelabot::message('Deleting routine $0', array(get_class($plugin).'/'.$event));
		
		if(!isset($this->_commands[$event]))
		{
			Leelabot::message('Event $0 does not exists.', array($event), E_WARNING);
			return FALSE;
		}
		
		if(!isset($this->_commands[$event][get_class($plugin)]))
		{
			Leelabot::message('Method does not exists.', array(), E_WARNING);
			return FALSE;
		}
		
		unset($this->_commands[$event][get_class($plugin)]);
		
		return TRUE;
	}
}

/**
 * \brief Plugin parent class for leelabot.
 * 
 * This class is the default template used when plugins are defined. It contains the default constructor (binding main class and plugins class to private properties)
 * and some shortcuts methods for plugin handling (it is better to user $this->addRoutine('someRoutineMethod') than$this->_plugins->addRoutine($this, 
 * 'someRoutineMethod'))
 */
class Plugin
{
	protected $_main;
	protected $_plugins;
	
	public function __construct(&$plugins, &$main)
	{
		$this->_plugins = $plugins;
		$this->_main = $main;
	}
	
	/** Default plugin init function.
	 * This function is empty. Its unique purpose is to avoid using method_exists() on PluginManager::initPlugin(). Returns TRUE.
	 * 
	 * \return TRUE.
	 */
	public function init()
	{
		return TRUE;
	}
	
	/** Shortcut to PluginManager::addServerEvent.
	 * \see PluginManager::addServerEvent
	 */
	public function addServerEvent($event, $method)
	{
		return $this->_plugins->addServerEvent($event, &$this, $method);
	}
	
	/** Shortcut to PluginManager::addCommand.
	 * \see PluginManager::addCommand
	 */
	public function addCommand($event, $method)
	{
		return $this->_plugins->addCommand($event, &$this, $method);
	}
	
	/** Shortcut to PluginManager::addRoutine.
	 * \see PluginManager::addRoutine
	 */
	public function addRoutine($method, $time = 1)
	{
		return $this->_plugins->addRoutine(&$this, $method, $time);
	}
	
	/** Shortcut to PluginManager::deleteServerEvent.
	 * \see PluginManager::deleteServerEvent
	 */
	public function deleteServerEvent($event)
	{
		return $this->_plugins->deleteServerEvent($event, &$this);
	}
	
	/** Shortcut to PluginManager::deleteCommand.
	 * \see PluginManager::deleteCommand
	 */
	public function deleteCommand($event)
	{
		return $this->_plugins->deleteCommand($event, &$this);
	}
	
	/** Shortcut to PluginManager::deleteRoutine.
	 * \see PluginManager::deleteRoutine
	 */
	public function deleteRoutine($method)
	{
		return $this->_plugins->deleteRoutine(&$this, $method);
	}
}
