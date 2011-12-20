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
	private $_pluginClasses; ///< Plugin classes names
	private $_routines = array(); ///< Routines list
	private $_serverEvents = array(); ///< Server events
	private $_commands = array(); ///< Commands
	private $_commandLevels = array(); ///< Command levels
	private $_defaultLevel; ///< Default level for new commands.
	private $_quietReply; ///< Trigger for the automatic reply for invalid commands/Insufficient access
	private $_pluginCache = array(); ///< Cache for already loaded plugins (to avoid class redefinition when reloading plugins)
	
	/** Constructor for PluginManager
	 * Initializes the class.
	 * 
	 * \param $main A reference to the main program class (Leelabot class).
	 */
	public function __construct(&$main)
	{
		$this->_main = $main;
		$this->_plugins = array();
		$this->_defaultLevel = 0;
		$this->_quietReply = FALSE;
	}
	
	/** Returns the name of a plugin from its instance.
	 * This function returns the name of a plugin, from its instance.
	 * 
	 * \param $object A plugin instance.
	 * 
	 * \return The plugins' name or NULL if the plugin class is not found.
	 */
	public function getName($object)
	{
		if(is_object($object))
		{
			foreach($this->_plugins as $plugin)
			{
				if($plugin['obj'] == $object)
					return $plugin['name'];
			}
		}
		elseif(is_string($object))
		{
			foreach($this->_plugins as $plugin)
			{
				if($plugin['className'] == $object)
					return $plugin['name'];
			}
		}
		
		return NULL;
	}
	
	/** Sets the default right level.
	 * This functions set the default right level for all future commands. This level will be interpreted (when adding the commands) as the new level 0, and when
	 * plugins will decide to redefine the level of some of their commands, they will be redefined regarding the value of the default level.
	 * 
	 * \param $level The new default right level.
	 * 
	 * \return TRUE if the level set correctly, FALSE otherwise.
	 */
	public function setDefaultRightLevel($level)
	{
		if($level >= 0)
			$this->_defaultLevel = $level;
		else
			return FALSE;
			
		Leelabot::message('Default right level is now $0', array($level), E_DEBUG);
		
		return TRUE;
	}
	
	/** Sets a command's right level.
	 * This functions sets a command's right level directly, without taking care of the default level set.
	 * 
	 * \param $cmd The command to modify.
	 * \param $level The new level.
	 * 
	 * \return TRUE if the level set correctly, FALSE otherwise.
	 */
	public function setCommandLevel($cmd, $level)
	{
		if($level >= 0 && isset($this->_commands[$cmd]))
			$this->_commandLevels[$cmd] = $level;
		else
			return FALSE;
		
		return TRUE;
	}
	
	/** Sets the sending of automatic replies.
	 * This function sets if the replies for inexistent commands and forbidden access (too low level). By default it is enabled.
	 * 
	 * \param $quiet The new quietReply state (Boolean or string, which will be interpreted with Leelabot::parseBool()).
	 * 
	 * \return The set state as a boolean (can be used to verify the goodness of the set value).
	 */
	public function setQuietReply($quiet = TRUE)
	{
		if(is_string($quiet))
			$this->_quietReply = Leelabot::parseBool($quiet);
		else
			$this->_quietReply = (bool)$quiet;
		
		return $this->_quietReply;
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
			$return = $this->loadPlugin($plugin);
			if($return !== FALSE)
				$loadedPlugins[] = $plugin;
			else
				return FALSE;
		}
		
		return TRUE;
	}
	
	/** Loads one plugin.
	 * This function loads one plugin. It is a very basic function. It checks that plugin exists and isn't loaded yet, and include its file. All the remaining work
	 * is done by PluginManager::initPlugin(), called inside the plugin file.
	 * 
	 * \param $plugin Plugin to be loaded.
	 * \return TRUE if plugin loaded successfully, FALSE otherwise. In fact, this value, beyond the "plugin not found" error, depends of the return value of 
	 * PluginManager::initPlugin.
	 */
	public function loadPlugin($plugin)
	{
		Leelabot::message('Loading plugin $0', array($plugin));
		
		//We check that the plugin is not already loaded
		if(in_array($plugin, $this->getLoadedPlugins()))
		{
			Leelabot::message('Plugin $0 is already loaded', array($plugin), E_WARNING);
			return FALSE;
		}
		
		if(!is_file('plugins/'.$plugin.'.php'))
		{
			Leelabot::message('Plugin $0 does not exists (File : $1)', array($plugin, getcwd().'/plugins/'.$plugin.'.php'), E_WARNING);
			return FALSE;
		}
		
		
		if(!isset($this->_pluginCache[$plugin]))
			include('plugins/'.$plugin.'.php'); //If the plugin has not already been loaded, we include the class
		else
			$this->initPlugin($this->_pluginCache[$plugin]); //Else we reload the plugin with the cached data from the first loading
	}
	
	/** Unloads a plugin.
	 * This function unloads a plugin. It does not unload the dependencies with it yet.
	 * 
	 * \param $plugin The plugin to unload.
	 * 
	 * \return TRUE if the plugin successuly unloaded, FALSE otherwise.
	 */
	public function unloadPlugin($plugin)
	{
		//We check that the plugin is not already loaded
		if(!in_array($plugin, $this->getLoadedPlugins()))
		{
			Leelabot::message('Plugin $0 is not loaded', array($plugin), E_WARNING);
			return FALSE;
		}
		
		$this->_plugins[$plugin]['obj']->destroy();
		unset($this->_plugins[$plugin]['obj']);
		unset($this->_plugins[$plugin]);
		
		return TRUE;
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
		//Checking that we have everything needed to load the plugin
		if(!is_array($params) || !isset($params['name']) || !isset($params['className']))
		{
			Leelabot::message('Cannot load plugin with given data : $0', array(Leelabot::dumpArray($params)), E_WARNING);
			return FALSE;
		}
		
		//Putting plugin data in memory for future use
		$this->_pluginCache[$params['name']] = $params;
		
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
		
		//Init of plugin data array, and plugin instanciation
		$this->_plugins[$params['name']] = array(
		'obj' => NULL,
		'name' => $params['name'],
		'display' => (isset($params['display']) ? $params['display'] : $params['name']),
		'dependencies' => (isset($params['dependencies']) ? $params['dependencies'] : array()),
		'className' => $params['className']);
		$this->_plugins[$params['name']]['obj'] = new $params['className']($this, $this->_main);
		
		//Autoloading !!1!
		if(isset($params['autoload']) && $params['autoload'])
		{
			Leelabot::message('Using automatic events recognition...');
			$methods = get_class_methods($params['className']); //Get all class methods for plugin
		
			//Analyse all class methods
			foreach($methods as $method)
			{
				//Checks for routines
				if(preg_match('#^Routine#', $method))
					$this->addRoutine($this->_plugins[$params['name']]['obj'], $method);
				//Checks for server events
				if(preg_match('#^SrvEvent#', $method))
					$this->addServerEvent(preg_replace('#SrvEvent(.+)#', '$1', $method), $this->_plugins[$params['name']]['obj'], $method);
				//Checks from command (found commands are used in lower case)
				if(preg_match('#^Command#', $method))
					$this->addCommand(strtolower(preg_replace('#Command(.+)#', '$1', $method)), $this->_plugins[$params['name']]['obj'], $method);
				//Checks for Webservice methods
				if(preg_match('#^WSMethod#', $method))
					$this->addWSMethod(lcfirst(preg_replace('#WSMethod(.+)#', '$1', $method)), $this->_plugins[$params['name']]['obj'], $method);
				//Checks for Webadmin pages
				if(preg_match('#^WAPage#', $method))
					$this->addWAPage(strtolower(preg_replace('#WAPage(.+)#', '$1', $method)), $this->_plugins[$params['name']]['obj'], $method);
			}
		}
		
		$this->_plugins[$params['name']]['obj']->init(); //Call to init() method of loaded plugin, for internal initializations and such.
		Leelabot::message('Loaded plugin $0', array($params['name']));
		
		//Now that the plugin is loaded, we update the list of all plugins' classes names
		$this->_reloadPluginsClasses();
		
		return TRUE;
	}
	
	/** Reloads the plugins' classes cache.
	 * This function reloads the cache containing the classes loaded for each plugin, for better performances while executing an event.
	 * 
	 * \return Nothing.
	 */
	private function _reloadPluginsClasses()
	{
		$this->_pluginClasses = array();
		foreach($this->_plugins as $plugin)
			$this->_pluginClasses[$plugin['className']] = $plugin['name'];
	}
	
	/** Gets the currently loaded plugins.
	 * This function returns an array containing the list of all currently loaded plugins' names.
	 * 
	 * \return The currently loaded plugins' names, in an array.
	 */
	public function getLoadedPlugins()
	{
		return array_keys($this->_plugins);
	}
	
	/** Returns the object of a plugin.
	 * This function returns the object instancied for a plugin, allowing to query it directly.
	 * 
	 * \param $name The plugin's name.
	 * 
	 * \return The instance of the plugin's class, or NULL if it does not exists.
	 */
	public function getPlugin($name)
	{
		if(isset($this->_plugins[$name]))
			return $this->_plugins[$name];
		else
			return NULL;
	}
	
	/** Adds a routine to the event manager.
	 * This function adds a routine to the event manager, i.e. a function that will be executed every once in a while.
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be executed.
	 * \param $time The time interval between 2 executions of the routine. Defaults to 1 second.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function addRoutine(&$plugin, $method, $time = 1)
	{
		Leelabot::message('Adding routine $0, executed every $1s', array(get_class($plugin).'::'.$method, $time), E_DEBUG);
		
		if(!method_exists($plugin, $method)) //Check if method exists
		{
			Leelabot::message('Error : Target method does not exists.', array(), E_DEBUG);
			return FALSE;
		}
		
		$this->_routines[get_class($plugin)][$method] = array($plugin, $time, array());
		
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
		Leelabot::message('Adding method $0, on server event $1', array(get_class($plugin).'::'.$method, $event), E_DEBUG);
		
		if(!method_exists($plugin, $method)) //Check if method exists
		{
			Leelabot::message('Error : Target method does not exists.', array(), E_DEBUG);
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
	 * \param $level The minimum level needed by the player to execute it.
	 * 
	 * \return TRUE if method registered correctly, FALSE otherwise.
	 */
	public function addCommand($event, &$plugin, $method, $level = NULL)
	{
		if($level === NULL)
			$level = $this->_defaultLevel;
		
		Leelabot::message('Adding method $0, on client command $1, with level $2', array(get_class($plugin).'::'.$method, '!'.$event, $level), E_DEBUG);
		
		if(!method_exists($plugin, $method)) //Check if method exists
		{
			Leelabot::message('Target method does not exists.', array(), E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_commands[$event]))
			$this->_commands[$event] = array();
		
		$this->_commands[$event][get_class($plugin)] = array($plugin, $method);
		$this->_commandLevels[$event] = $level;
		
		return TRUE;
	}
	
	/** Adds a method to the Webservice.
	 * This function adds a method to the Outer API Webservice, which will be callable over a HTTP POST request (see MOAPServer and MOAPClient classes for more info).
	 * 
	 * \param $method The method name to bind (i.e. the method name tha will be called other HTTP)
	 * \param $object The object of the plugin to call.
	 * \param $callback The callback method.
	 * 
	 * \return TRUE if the method added correctly, FALSE otherwise (mainly if the webservice is disables).
	 */
	public function addWSMethod($method, &$object, $callback)
	{
		Leelabot::message('Adding method $0, on OuterAPI method $1', array($callback, $method), E_DEBUG);
		if(is_object($this->_main->outerAPI) && $this->_main->outerAPI->getWSState())
			$this->_main->outerAPI->getWSObject()->addMethod($method, array($object, $callback));
		else
			return FALSE;
	}
	
	/** Adds a page to the webadmin.
	 * This function adds a page to the OuterAPI webadmin, pointing to the given method. The callback needs to return the HTML of the page, without the design 
	 * (it will be added manually).
	 * 
	 * \param $page The page name. It will be a sub-page of the plugin's section (to avoid pages collisions).
	 * \param $object Reference to the plugin object that will handle the call. Plugin name will be guessed from this object.
	 * \param $callback The callback method to be called.
	 */
	public function addWAPage($page, &$object, $callback)
	{
		if(!$plugin = $this->getName($object))
			return FALSE;
			
		if(is_object($this->_main->outerAPI) && $this->_main->outerAPI->getWAState())
			$this->_main->outerAPI->getWAObject()->addPluginPage($plugin.'/'.$page, array($object, $callback));
		
	}
	
	/** Deletes a routine.
	 * This function deletes a routine from the event manager.
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 * 
	 * \return TRUE if method deleted correctly, FALSE otherwise.
	 */
	public function deleteRoutine(&$plugin, $method)
	{
		Leelabot::message('Deleting routine $0', array(get_class($plugin).'::'.$method), E_DEBUG);
		
		if(!isset($this->_routines[get_class($plugin)]))
		{
			Leelabot::message('Plugin $0 does not exists in routine list.', array($event), E_DEBUG);
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
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 * 
	 * \return TRUE if method deleted correctly, FALSE otherwise.
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
		if(empty($this->_serverEvents[$event]))
			unset($this->_serverEvents[$event]);
		
		return TRUE;
	}
	
	/** Deletes a command.
	 * This function deletes a command from the event manager.
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be deleted.
	 * 
	 * \return TRUE if method deleted correctly, FALSE otherwise.
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
		if(empty($this->_commands[$event]))
			unset($this->_commands[$event]);
		
		return TRUE;
	}
	
	/** Returns the command list.
	 * This function returns the list of all defined commands, for all plugins or only for a list of plugins.
	 * 
	 * \param $plugins Plugin list for which we want to get the commands.
	 * 
	 * \return an array containing in keys the commands' names, and in value their right level.
	 */
	public function getCommandList($plugins = FALSE)
	{
		$return = array();
		if($plugins != FALSE)
		{
			foreach($this->_commands as $event => $info)
			{
				if(in_array($this->getName($info[0]), $plugins))
					$return[$event] = $this->_commandLevels[$event];
			}
		}
		else
		{
			foreach($this->_commands as $event => $info)
				$return[$event] = $this->_commandLevels[$event];
		}
		
		return $return;
	}
	
	/** Changes the time interval of a routine
	 * This function allow the plugin to changes the time interval of one of his routines. It is useful when using automatic revent detection, because it does not
	 * handles custom timers for routines (they are set to 1 second).
	 * 
	 * \param $plugin A reference to the plugin's class where the method is.
	 * \param $method The name of the method to be updated.
	 * \param $time The new time interval.
	 * 
	 * \return TRUE if method modified correctly, FALSE otherwise.
	 */
	public function changeRoutineTimeInterval(&$plugin, $method, $time)
	{
		Leelabot::message('Changing routine $0 time interval to $1s', array(get_class($plugin).'::'.$method, $time), E_DEBUG);
		
		if(!isset($this->_routines[get_class($plugin)]))
		{
			Leelabot::message('Plugin $0 does not exists in routine list.', array(get_class($plugin)), E_DEBUG);
			return FALSE;
		}
		
		if(!isset($this->_routines[get_class($plugin)][$method]))
		{
			Leelabot::message('Routine does not exists.', array(), E_DEBUG);
			return FALSE;
		}
		
		$this->_routines[get_class($plugin)][$method][1] = $time;
		
		return TRUE;
	}
	
	/** Executes all the routines for all plugins.
	 * This function executes all the routines for all plugins, whether checking if their interval timed out, or not (so all routines are executed), depending
	 * on the value of the \b $force param.
	 * 
	 * \param $force Forces the routines to be executed or not. By default it does not executes them.
	 * 
	 * \return TRUE if routines executed correctly, FALSE otherwise.
	 */
	public function callAllRoutines($force = FALSE)
	{
		$serverPlugins = Server::getPlugins();
		$serverName = Server::getName();
		foreach($this->_routines as $className => &$class)
		{
			foreach($class as $name => &$routine)
			{
				if($force || !isset($routine[2][$serverName]) || (time() >= $routine[2][$serverName] + $routine[1] /*&& time() != $routine[2][$serverName] */))
				{
					if(in_array($this->_pluginClasses[$className], $serverPlugins))
					{
						$routine[0]->$name();
						$routine[2][$serverName] = time();
					}
				}
			}
		}
	}
	
	/** Calls a server event.
	 * This function executes all the callback methods bound to the given server event.
	 * 
	 * \param $event The server event called.
	 * \param $params Parameter(s) to send to the callbacks, in an array.
	 * 
	 * \return TRUE if callbacks executed correctly, FALSE otherwise.
	 */
	public function callServerEvent($event, $params = array())
	{
		if(isset($this->_serverEvents[$event]))
		{
			if(!is_array($params))
				$params = array($params);
			
			$serverPlugins = Server::getPlugins();
			foreach($this->_serverEvents[$event] as $plugin => &$event)
			{
				if(in_array($this->_pluginClasses[$plugin], $serverPlugins))
					call_user_func_array($event, $params);
			}
		}
		return TRUE;
	}
	
	/** Calls a client command.
	 * This function executes all the callback methods bound to the given event.
	 * 
	 * \param $event The server event called.
	 * \param $player The ID of the player who sent the event
	 * \param $params Parameter(s) to send to the callbacks.
	 * 
	 * \return TRUE if callbacks executed correctly, FALSE otherwise.
	 */
	public function callCommand($event, $player, $params)
	{
		if(isset($this->_commands[$event]))
		{
			$serverPlugins = Server::getPlugins();
			foreach($this->_commands[$event] as $plugin => &$class)
			{
				if(in_array($this->_pluginClasses[$plugin], $serverPlugins))
				{
					Leelabot::message('Level matching : $0/$1', array(Server::getPlayer($player)->level,  $this->_commandLevels[$event]), E_DEBUG);
					if(Server::getPlayer($player)->level >= $this->_commandLevels[$event])
						$class[0]->$class[1]($player, $params);
					else
					{
						if(!$this->_quietReply)
							RCon:tell($player, 'You\'re not allowed to execute this command.');
						break;
					}
				}
			}
		}
		else
		{
			if(!$this->_quietReply)
				RCon::tell($player, 'Command not found.');
		}
	}
}

/**
 * \brief Plugin parent class for leelabot.
 * 
 * This class is the default template used when plugins are defined. It contains the default constructor (binding main class and plugins class to private properties)
 * and some shortcuts methods for plugin handling (it is better to user $this->addRoutine('someRoutineMethod') than $this->_plugins->addRoutine($this, 
 * 'someRoutineMethod'))
 */
class Plugin
{
	protected $_main; ///< Reference to main class (Leelabot)
	protected $_plugins; ///< Reference to plugin manager (PluginManager)
	protected $config; ///< Plugin configuration
	
	public function __construct(&$plugins, &$main)
	{
		$this->_plugins = $plugins;
		$this->_main = $main;
		
		$plugin = ucfirst($plugins->getName(get_class($this)));
		
		if(!isset($main->config['Plugin']) || !isset($main->config['Plugin'][$plugin]))
			$main->config['Plugin'] = array($plugin => array());
			
		$this->config = $main->config['Plugin'][$plugin];
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
	
	/** Default plugin destroy function.
	 * This function is empty. Its unique purpose is to avoid using method_exists() on PluginManager::unloadPlugin(). Returns TRUE.
	 * 
	 * \return TRUE.
	 */
	public function destroy()
	{
		return TRUE;
	}
	
	/** Shortcut to PluginManager::addServerEvent.
	 * \see PluginManager::addServerEvent
	 */
	public function addServerEvent($event, $method)
	{
		return $this->_plugins->addServerEvent($event, $this, $method);
	}
	
	/** Shortcut to PluginManager::addCommand.
	 * \see PluginManager::addCommand
	 */
	public function addCommand($event, $method)
	{
		return $this->_plugins->addCommand($event, $this, $method);
	}
	
	/** Shortcut to PluginManager::addRoutine.
	 * \see PluginManager::addRoutine
	 */
	public function addRoutine($method, $time = 1)
	{
		return $this->_plugins->addRoutine($this, $method, $time);
	}
	
	/** Shortcut to PluginManager::addWSMethod
	 * \see PluginManager::addWSMethod
	 */
	public function addWSMethod($method, $callback)
	{
		return $this->_plugins->addOuterAPIMethod($method, $this, $callback);
	}
	
	/** Shortcut to PluginManager::deleteServerEvent.
	 * \see PluginManager::deleteServerEvent
	 */
	public function deleteServerEvent($event)
	{
		return $this->_plugins->deleteServerEvent($event, $this);
	}
	
	/** Shortcut to PluginManager::deleteCommand.
	 * \see PluginManager::deleteCommand
	 */
	public function deleteCommand($event)
	{
		return $this->_plugins->deleteCommand($event, $this);
	}
	
	/** Shortcut to PluginManager::deleteRoutine.
	 * \see PluginManager::deleteRoutine
	 */
	public function deleteRoutine($method)
	{
		return $this->_plugins->deleteRoutine($this, $method);
	}
	
	/** Shortcut to PluginManager::changeRoutineTimeInterval.
	 * \see PluginManager::deleteRoutine
	 */
	public function changeRoutineTimeInterval($method, $time)
	{
		return $this->_plugins->changeRoutineTimeInterval($this, $method, $time);
	}
}
