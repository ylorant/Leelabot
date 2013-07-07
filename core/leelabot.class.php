<?php
/**
 * \file core/leelabot.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Leelabot class file.
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
 * This file hosts the Leelabot class, handling all server instances, plugins, API...
 */

/**
 * \brief Leelabot class for leelabot.
 * 
 * This class is the Leelabot class for the bot, though it does not parses the server's messages (this is done by the instance class).
 * This class allow multiple server instances to run together, by dispatching calls to the instances, and giving separate configurations to
 * all the instances.
 */
class Leelabot
{
	private $_configDirectory; ///< Config directory. Defaults to ./conf directory.
	private $_run; ///< Running toggle. Setting it to FALSE stops the bot.
	private $_iterations; ///< Itertion counter, to mesure bot performance.
	private $_showIPS; ///< Show iterations per second toggle.
	private $_IPSHistory; ///< Last 3 seconds IPS.
	private static $_logFile; ///< Log file, accessed by Leelabot::message() method.
	private static $_lastError = NULL; ///< Last error put in the log, according to Leelabot::message().
	public static $verbose; ///< Verbose mode (boolean, defaults to FALSE).
	public static $instance; ///< Current instance of Leelabot (for accessing dynamic properties from static functions)
	public $intl; ///< Locale management object
	public $config; ///< Configuration data (for all objects : servers, plugins...)
	public $servers; ///< Server instances objects
	public $plugins; ///< Plugin manager
	public $update; ///< Update manager
	public $outerAPI; ///< Outer API class
	public $maxServers; ///< Max servers for the bot
	public $system; ///< Name of the system where the bot is executed (equivalent to uname -a on UN*X)
	public $root; ///< Root directory for the bot
	public $botName; ///< Bot name.
	
	const VERSION = '0.5-git "Sandy"'; ///< Current bot version
	const REVISION = '$Rev$'; ///< Current bot revision
	const DEFAULT_LOCALE = "en"; ///< Default locale
	
	/** Initializes the bot.
	 * Initializes the bot, by reading arguments, parsing configs sections, initializes server instances, and many other things.
	 * 
	 * \param $CLIArguments list of arguments provided to the launcher, or generated ones (for further integration into other systems).
	 * \return TRUE in case of success, FALSE otherwise.
	 */
	public function init($CLIArguments)
	{
		//Start Cpu Monitoring
		$this->cpuRequestStart();
		//Setting default values for class attributes
		Leelabot::$instance = &$this;
		$this->_configDirectory = 'conf';
		Leelabot::$verbose = FALSE;
		$this->servers = array();
		$this->system = php_uname('a');
		$this->_showIPS = FALSE;
		$this->_iterations = 0;
		$this->_IPSHistory = array_fill(0, 10, 0);
		
		//Parsing CLI arguments
		$logContent = NULL;
		$CLIArguments = Leelabot::parseArgs($CLIArguments);
		
		//Checking CLI argument for root modification, and modification in case
		if($rootParam = array_intersect(array('r', 'root'), array_keys($CLIArguments)))
			chdir($CLIArguments[$rootParam[0]]);
		
		//Setting root
		$this->root = getcwd();
			
		//Opening default log file (can be modified after, if requested)
		Leelabot::$_logFile = fopen('leelabot.log', 'a+');
		fseek(Leelabot::$_logFile, 0, SEEK_END);
		$initPos = ftell(Leelabot::$_logFile);
		
		//Loading Intl class (if it is not loadable, load a dummy substitute)
		$this->intl = new Intl(Leelabot::DEFAULT_LOCALE);
		if(!$this->intl->init)
		{
			$this->intl = new Intl(); //Load class without a locale defined
			Leelabot::message('Can\'t load Intl class with default locale ($0).', array(Leelabot::DEFAULT_LOCALE), E_ERROR);
			exit();
		}
		
		Leelabot::message('Leelabot version $0 starting...', array(Leelabot::VERSION), E_NOTICE, TRUE);
		
		//Loading plugin manager class
		$this->plugins = new PluginManager($this);
		Plugins::setPluginManager($this->plugins);
		
		//Pre-parsing CLI arguments (these arguments are relative to config loading and files location)
		$this->processCLIPreparsingArguments($CLIArguments);
		
		//Loading config
		if(!$this->loadConfig())
		{
			Leelabot::message("Startup aborted : Can't parse startup config.", array(), E_ERROR);
			exit();
		}
		
		//Checking if the number of servers defined in the config is not greater than the limit defined in the CLI
		if($this->maxServers > 0 && count($this->config['Server']) > $this->maxServers)
		{
			Leelabot::message("Number of set servers in the config is greater than the limit ($0).", array($this->maxServers), E_ERROR);
			exit();
		}
		
		//Processing loaded config (for main parameters)
		if(isset($this->config['Main']))
		{
			$logContent = '';
			//Setting the locale in accordance with the configuration (if set)
			foreach($this->config['Main'] as $name => $value)
			{
				switch($name)
				{
					case 'Locale':
						Leelabot::message('Changed locale by configuration : $0', array($this->config['Main']['Locale']));
						if(!$this->intl->setLocale($this->config['Main']['Locale']))
							Leelabot::message('Cannot change locale to "$0"', array($this->config['Main']['Locale']), E_WARNING);
						else
							Leelabot::message('Locale successfully changed to "$0".', array($this->config['Main']['Locale']));
						break;
					case 'UseLog':
						if(Leelabot::parseBool($value) == FALSE)
						{
							Leelabot::message('Disabling log (by Config).');
							//Save log content for later parameters (like when using --nolog -log file.log)
							if(Leelabot::$_logFile)
							{
								$logContent = '';
								fseek(Leelabot::$_logFile, $initPos);
								
								while(!feof(Leelabot::$_logFile))
									$logContent .= fgets(Leelabot::$_logFile);
									
								//If the file was empty before logging into it, delete it
								if($initPos == 0)
								{
									$logFileInfo = stream_get_meta_data(Leelabot::$_logFile);
									fclose(Leelabot::$_logFile);
									unlink($logFileInfo['uri']);
								}
								else
									fclose(Leelabot::$_logFile);
								
								Leelabot::$_logFile = FALSE;
							}
						}
						break;
					case 'BotName':
						$this->botName = $value;
						break;
					case 'ShowIPS':
						$this->_showIPS = Leelabot::parseBool($value);
						break;
					case 'LogFile':
						Leelabot::message('Changing log file to $0 (by Config)', array($value));
						//Save log content for later parameters (like when using --nolog -log file.log)
						if(Leelabot::$_logFile)
						{
							$logContent = '';
								fseek(Leelabot::$_logFile, $initPos);
								
								while(!feof(Leelabot::$_logFile))
									$logContent .= fgets(Leelabot::$_logFile);
								
								//If the file was empty before logging into it, delete it
								if($initPos == 0)
								{
									$logFileInfo = stream_get_meta_data(Leelabot::$_logFile);
									fclose(Leelabot::$_logFile);
									unlink($logFileInfo['uri']);
								}
								else
									fclose(Leelabot::$_logFile);
								
								Leelabot::$_logFile = FALSE;
						}
						
						//Load new file, and put the old log content into it (if opening has not failed, else we re-open the old log file)
						if(!(Leelabot::$_logFile = fopen($value, 'a+')))
						{
							Leelabot::$_logFile = fopen($logFileInfo['uri'], 'a+');
							Leelabot::message('Cannot open new log file ($0), reverting to old.', array($value), E_WARNING);
						}
						else
						{
							fseek(Leelabot::$_logFile, 0, SEEK_END);
							$initPos = ftell(Leelabot::$_logFile);
							fputs(Leelabot::$_logFile, $logContent);
						}
						break;
				}
			}
			unset($logContent);
		}	
		
		//Post-parsing CLI arguments (after loading the config because they override file configuration)
		$this->processCLIPostparsingArguments($CLIArguments, $initPos);
		
		//Loading ServerList InnerAPI class
		ServerList::setLeelabotClass($this);
		
		//Loading Locale InnerAPI class
		Locales::init($this->intl);
		
		//Loading the OuterAPI (if required, i.e. There is an API section in config and if there's an Enable parameter, it is active)
		if(isset($this->config['API']) && (!isset($this->config['API']['Enable']) || Leelabot::parseBool($this->config['API']['Enable']) == TRUE))
		{
			$this->outerAPI = new OuterAPI();
			$this->outerAPI->load($this->config['API']);
		}
		else
			$this->outerAPI = NULL;
			
		//Loading Update class if needed.
		if(isset($this->config['Update']) && isset($this->config['Update']['Enabled']) && Leelabot::parseBool($this->config['Update']['Enabled']))
		{
			include('core/update.class.php');
			$this->update = new Updater($this->config['Update'], $this->plugins);
		}
		
		//Loading plugins (throws a warning if there is no plugin general config, because using leelabot without plugins is as useful as eating corn flakes hoping to fly)
		if(isset($this->config['Plugins']) && isset($this->config['Plugins']['AutoLoad']) && $this->config['Plugins']['AutoLoad'])
		{
			//Getting automatically loaded plugins
			$this->config['Plugins']['AutoLoad'] = explode(',', $this->config['Plugins']['AutoLoad']);
			
			//Setting priority order.
			$order = array();
			$unordered = array();
			foreach($this->config['Plugins']['AutoLoad'] as $plugin)
			{
				$plugin = trim($plugin);
				if(strpos($plugin, '/'))
				{
					$priority = explode('/', $plugin);
					$i = 0;
					for(; isset($order[$priority[1].'.'.$i]);$i++);
					$order[$priority[0].'.'.$i] = $priority[0];
				}
				else
					$unordered[] = $plugin;
			}
			
			ksort($order);
			$this->config['Plugins']['AutoLoad'] = array_merge(array_values($order), $unordered);
			
			//Setting default right level for commands
			if(isset($this->config['Commands']['DefaultLevel']))
				$this->plugins->setDefaultRightLevel($this->config['Commands']['DefaultLevel']);
			else
				$this->plugins->setDefaultRightLevel(0);
			
			//We load plugins
			$this->plugins->loadPlugins($this->config['Plugins']['AutoLoad'], TRUE);
			
			//Setting user-defined levels for commands
			if(isset($this->config['Commands']['Levels']))
			{
				foreach($this->config['Commands']['Levels'] as $key => $value)
				{
					if($key[0] = '!') //We check if the param name is a command
						$this->plugins->setCommandLevel($key, $value, TRUE);
					elseif(intval($key) == $key) //We check if the param name is a level
					{
						$value = explode(',', $value);
						foreach($value as $command)
							$this->plugins->setCommandLevel($command, $key, TRUE);
					}
				}
			}
			
			//Setting the verbosity for the command replies
			if(isset($this->config['Commands']['QuietReplies']))
				$this->plugins->setQuietReply($this->config['Commands']['QuietReplies']);
		}
		else
			Leelabot::message('There is no plugin configuration', array(), E_WARNING);
		
		//Loading server instances
		$this->loadServerInstances();
		
		//Notice that we have loaded successfully if more than one server loaded
		if(count($this->servers))
			Leelabot::message('Leelabot loaded successfully for $0 server$1', array(count($this->servers), (count($this->servers) > 1 ? 's' : '')));
		else
		{
			Leelabot::message('Can\'t load Leelabot for any configured server.', array(), E_ERROR);
			exit();
		}
	}
	
	/** Leelabot loop.
	* Leelabot loop for the bot, blocking.
	* 
	* \return TRUE in case of success when user asked the loop to end, FALSE if an error occured.
	*/
	public function run()
	{
		$this->_run = TRUE;
		
		$lastTime = time();
		while($this->_run)
		{
			foreach($this->servers as $name => $server)
			{
				//Setting servers for static inner API
				RCon::setServer($this->servers[$name]);
				Server::setServer($this->servers[$name]);
				
				$this->servers[$name]->step();
				usleep(5000);
			}
			if($this->outerAPI !== NULL)
				$this->outerAPI->process();
			
			if($this->_showIPS)
			{
				$this->_iterations++;
				//Showing IPS
				if($lastTime != time())
				{
					Leelabot::message('IPS: $0', array($this->_iterations), E_DEBUG);
					array_pop($this->_IPSHistory);
					array_unshift($this->_IPSHistory, $this->_iterations);
					$this->_iterations = 0;
					$lastTime = time();
				}
			}
		}
		
		foreach($this->servers as $name => $server)
			$server->disconnect();
		
		foreach($this->plugins->getLoadedPlugins() as $plugin)
			$this->plugins->unloadPlugin($plugin);
			
		return TRUE;
	}
	
	/** Stop toggle for the bot.
	 * This function simply stops the main loop of the bot.
	 * 
	 * \return Nothing.
	 */
	public function stop()
	{
		$this->_run = FALSE;
	}
	
	public static function fork($callback, $params)
	{
		$pid = pcntl_fork();
		if ($pid == -1)
			return false;
		else if ($pid)
			return true;
		else
		{
			self::$verbose = true;
			call_user_func_array($callback, $params);
			die();
		}
	}
	
	/** Loads all the server instances and defining their parameters
	 * This function loads all the server instances found in Leelabot::$config, and initializes them with their config.
	 * 
	 * \returns TRUE if instances loaded successfully, FALSE otherwise.
	 */
	public function loadServerInstances()
	{
		if(!isset($this->config['Server']))
		{
			Leelabot::message('No server defined in configuration.', array(), E_ERROR);
			exit();
		}
		
		$this->servers = array();
		Leelabot::message('Loading server instances.');
		
		//Checking default configuration
		if(isset($this->config['Server']['default']))
		{
			$defaultConfig = $this->config['Server']['default'];
			unset($this->config['Server']['default']);
		}
		foreach($this->config['Server'] as $name => $instance)
		{
			if(isset($instance['name']))
				$name = $instance['name'];
			
			Leelabot::message('Loading server "$0".', array($name));
			
			$this->servers[$name] = new ServerInstance($this); //Using config name if not specified
			$this->servers[$name]->setName($name);
			
			//Loading config
			if(!$this->servers[$name]->loadConfig($instance))
			{
				Leelabot::message('Can\'t load config for server "$0".', array($name), E_WARNING);
				unset($this->servers[$name]);
				continue;
			}
			
			//Setting servers for static inner API
			RCon::setServer($this->servers[$name]);
			Server::setServer($this->servers[$name]);
			
			//Connecting to server
			if(!$this->servers[$name]->connect())
			{
				Leelabot::message('Can\'t connect to server "$0".', array($name), E_WARNING);
				unset($this->servers[$name]);
			}
		}
	}
	
	/** Loads a new server.
	 * This function creates and loads the config associated (if it exists). Finally it connects to the server.
	 * 
	 * \param $name The server name.
	 * \param $config An associative array containing the config for the new server, where the keys corresponds to config vars' names. If not given, the config
	 * will be loaded from the global config (i.e. the config files), if it exists.
	 * 
	 * \return TRUE if the server loaded correctly, FALSE otherwise.
	 */
	public function loadServer($name, $config = NULL)
	{
		$this->servers[$name] = new ServerInstance($this);
		$this->servers[$name]->setName($name);
		
		if(is_array($config))
			$this->servers[$name]->loadConfig($config);
		elseif(isset($this->config['Server'][$name]))
			$this->servers[$name]->loadConfig($this->config['Server'][$name]);
		else
			return FALSE;
		
		if(!$this->servers[$name]->connect())
			return FALSE;
		
		return TRUE;
	}
	
	/** Unloads a server.
	 * This function unloads a previously loaded server. It does not disconnects the clients and everything, it justs removes it from the server list.
	 * 
	 * \param $name The server's name.
	 * 
	 * \return TRUE if the server unloaded successfully, FALSE otherwise.
	 */
	public function unloadServer($name)
	{
		if(!isset($this->_servers[$name]))
			return FALSE;
		
		unset($this->_servers[$name]);
		return TRUE;
	}
	
	/** Processes pre-parsing CLI arguments.
	 * Processes the arguments who have to be processed before config loading (like path to that config). Root change parameter (-r) is an exception and not
	 * parsed here because it needs to be parsed earlier.
	 * 
	 * \returns TRUE if everything gone fine, FALSE if an error occured.
	 */
	public function processCLIPreparsingArguments($CLIArguments)
	{
		foreach($CLIArguments as $name => $value)
		{
			switch($name)
			{
				//Changing config file or directory (root config directory will be guessed from config file if given)
				case 'c':
				case 'config':
					Leelabot::message('Setting user config directory : $0', array($value));
					if(!$this->setConfigLocation($value))
						Leelabot::message('Cannot ser user config directory to "$0"', array($value));
					break;
				//Enable verbose mode.
				case 'v':
				case 'verbose':
					Leelabot::$verbose = 1;
					Leelabot::message('Starting in Verbose mode.');
					Leelabot::message('Command arguments : $0', array($this->dumpArray($CLIArguments)));
					break;
				case 'vv':
				case 'veryverbose':
					Leelabot::$verbose = 2;
					Leelabot::message('Starting in Very Verbose mode.');
					Leelabot::message('Command arguments : $0', array($this->dumpArray($CLIArguments)));
					Leelabot::message('Current PHP version : $0', array(phpversion()));
					break;
				case 'maxservers':
					$this->maxServers = intval($value);
					break;
			}
		}
	}
	
	/** Processes pre-parsing CLI arguments.
	 * Processes the arguments who have to be processed after config loading (so they are prioritary on config, they overwrite it).
	 * 
	 * \returns TRUE if everything gone fine, FALSE if an error occured.
	 */
	public function processCLIPostparsingArguments($CLIArguments, $logPos)
	{
		$logContent = '';
		foreach($CLIArguments as $name => $value)
		{
			switch($name)
			{
				//Change display language
				case 'l':
				case 'lang':
					Leelabot::message('Changed locale by CLI : $0', array($value));
					if(!$this->intl->setLocale($value))
						Leelabot::message('Cannot change locale to "$0"', array($value), E_WARNING);
					else
						Leelabot::message('Locale successfully changed to "$0".', array($value));
					break;
				case 'no-log': //Don't use a log file
					Leelabot::message('Disabling log (by CLI).');
				case 'log': //Define the log file in another place than the default
					if($name == 'log')
						Leelabot::message('Changing log file to $0 (By CLI)', array($value));
					//Save log content for later parameters (like --nolog -log file.log)
					if(Leelabot::$_logFile)
					{
						$logContent = '';
						fseek(Leelabot::$_logFile, $logPos);
						
						while(!feof(Leelabot::$_logFile))
							$logContent .= fgets(Leelabot::$_logFile);
						
						$logFileInfo = stream_get_meta_data(Leelabot::$_logFile);
						fclose(Leelabot::$_logFile);
						
						//If the file was empty before logging into it, delete it
						if($logPos == 0)
							unlink($logFileInfo['uri']);
						
						Leelabot::$_logFile = FALSE;
					}
					
					if($name == 'no-log')
						break;
					//Load new file, and put the old log content into it (if opening has not failed, else we re-open the old log file)
					if(!(Leelabot::$_logFile = fopen($value, 'a+')))
					{
						Leelabot::$_logFile = fopen($logFileInfo['uri'], 'a+');
						Leelabot::message('Cannot open new log file ($0), reverting to old.', array($value), E_WARNING);
						break;
					}
					fseek(Leelabot::$_logFile, 0, SEEK_END);
					$logPos = ftell(Leelabot::$_logFile);
					fputs(Leelabot::$_logFile, $logContent);
					break;
				case 'erase-log':
					$logFileInfo = stream_get_meta_data(Leelabot::$_logFile);
					Leelabot::message('Erasing all previous log in $0...',array($logFileInfo['uri']));
					//Rewinding log file to start of current session log
					fseek(Leelabot::$_logFile, $logPos);
					
					//Dumping new log data before wiping old log
					$log = '';
					while(!feof(Leelabot::$_logFile))
							$log .= fgets(Leelabot::$_logFile);
					
					//Closing file and reopening it in w+ mode, ensures that previous data is wiped, then write current log in the now-empty file
					fclose(Leelabot::$_logFile);
					Leelabot::$_logFile = fopen($logFileInfo['uri'], 'w+');
					fputs(Leelabot::$_logFile, $log);
					break;
			}
		}
	}
	
	/** Loads configuration from config files.
	 * This function loads configuration from all .ini files in the configuration folder.
	 * Configurations for all servers can be in one file or multiple files, content will be glued together
	 * and read as an unique file (it allows a more flexible ordering for big configurations).
	 * 
	 * \return TRUE if the configuration loaded successfully, FALSE otherwise.
	 */
	public function loadConfig()
	{
		Leelabot::message('Loading config...');
		if(!is_dir($this->_configDirectory))
		{
			Leelabot::message('Could not access to user-set confdir : $0', array($this->_configDirectory), E_WARNING);
			
			//Throw a fatal error if the default config directory does not exists
			if(!is_dir('conf'))
			{
				Leelabot::message('Could not access to default confdir : ./conf', array(), E_ERROR);
				exit();
			}
			else
				$this->_configDirectory = 'conf';
		}
		
		//Scanning config directory recursively (with also recursive section names)
		$config = Leelabot::parseCFGDirRecursive($this->_configDirectory);
		
		if(!$config)
			return FALSE;
		else
			$this->config = $config;
		
		return TRUE;	
	}
	
	/** Loads data from .cfg files into a directory, recursively.
	 * This function loads configuration from all .ini files in the given folder. It also loads the configurations found in all sub-directories.
	 * The files are proceeded as .ini files, but adds a useful feature to them : multi-level sections. Using the '.', users will be able to
	 * define more than one level of configuration (useful for data ordering). It does not parses the UNIX hidden directories.
	 * 
	 * \param $dir The directory to analyze.
	 * \return The configuration if it loaded successfully, FALSE otherwise.
	 */
	public static function parseCFGDirRecursive($dir)
	{
		if(!($dirContent = scandir($dir)))
			return FALSE;
			
		$finalConfig = array();
		
		$cfgdata = '';
		foreach($dirContent as $file)
		{
			if(is_file($dir.'/'.$file) && pathinfo($file, PATHINFO_EXTENSION) == 'cfg')
				$cfgdata .= "\n".file_get_contents($dir.'/'.$file);
			elseif(is_dir($dir.'/'.$file) && !in_array($file, array('.', '..')) && $file[0] != '.')
			{
				if($fileConf = Leelabot::parseCFGDirRecursive($dir.'/'.$file))
					$finalConfig = array_merge($finalConfig, $fileConf);
				else
				{
					Leelabot::message('Parse error in $0 directory.', array($dir), E_WARNING);
					return FALSE;
				}
			}
		}
		
		$finalConfig = array_merge($finalConfig, Leelabot::parseINIStringRecursive($cfgdata));
		
		return $finalConfig;
	}
	
	/** Parses an INI string, with recursive sections.
	 * This function parses a string written in the INI format, and process sections to produce a recursive array of sections, splitting levels
	 * with the dot symbol.
	 * 
	 * \param $str The string to parse.
	 * 
	 * \return FALSE if an error occured during the parsing, else it returns the recursive array obtained from the parsing.
	 */
	public static function parseINIStringRecursive($str)
	{
		$config = array();
		
		//Parsing string and determining recursive array
		$inidata = parse_ini_string($str, TRUE, INI_SCANNER_RAW);
		if(!$inidata)
			return FALSE;
		foreach($inidata as $section => $content)
		{
			if(is_array($content))
			{
				$section = explode('.', $section);
				//Getting reference on the config category pointed
				$edit = &$config;
				foreach($section as $el) 
					$edit = &$edit[$el];
				
				$edit = $content;
			}
			else
				Leelabot::message('Orphan config parameter : $0', array($section), E_WARNING);
		}
		
		return $config;
	}
	
	/** Generates INI config string for recursive data.
	 * This function takes configuration array passed in parameter and generates an INI configuration string with recursive sections.
	 * 
	 * \param $data The data to be transformed.
	 * \param $root The root section. Normally, this parameter is used by the function to recursively parse data by calling itself.
	 * 
	 * \return The INI config data.
	 */
	public static function generateINIStringRecursive($data, $root = "")
	{
		$out = "";
		
		if($root)
			$out = '['.$root.']'."\n";
		
		$arrays = array();
		
		//Process data, saving sub-arrays, putting direct values in config.
		foreach($data as $name => $value)
		{
			if(is_array($value) || is_object($value))
				$arrays[$name] = $value;
			elseif(is_bool($value))
				$out .= $name.'='.($value ? 'yes' : 'no')."\n";
			else
				$out .= $name.'='.$value."\n";	
		}
		
		if($out)
			$out .= "\n";
		
		//Processing sub-sections
		foreach($arrays as $name => $value)
			$out .= Leelabot::generateINIStringRecursive($value, $root.($root ? '.' : '').$name)."\n\n";
		
		return trim($out);
	}
	
	/** Changes the configuration directory.
	 * Changes the configuration directory to $path. If $path is a file (like Leelabot config file for example),
	 * configuration directory will be guessed from this file.
	 * 
	 * \param $path The path where the bot will read the config.
	 * \return 	TRUE if config location successfully changed, FALSE otherwise (Leelabotly because $dir is 
	 * 			neither an existing directory path nor an existing file path).
	 */
	public function setConfigLocation($path)
	{
		if(substr($path, -1) == '/')
			$path = substr($path, 0, -1);
		
		if(is_dir($path))
			$this->_configDirectory = $path;
		elseif(is_file($path))
			$this->_configDirectory = pathinfo($path, PATHINFO_DIRNAME);
		else
			return FALSE;
		
		return TRUE;
	}
	
	/** Returns the configuration directory.
	 * This function simply returns the path to the current configuration directory, as specified by setConfigLocation,
	 * or the default one if it has not been overwrited yet.
	 * 
	 * \return The current configuration directory.
	 */
	public function getConfigLocation()
	{
		return $this->_configDirectory;
	}
	
	/** Parses CLI arguments.
	 * Parses command line arguments, in UNIX format. Taken from php.net.
	 * 
	 * \param $args Command-line formatted list of arguments
	 * \return argument list, in an ordered clean array.
	 */
	public static function parseArgs($args)
	{
		$result = array();
		$noopt = array();
		$params = $args;
		// could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
		reset($params);
		while (list($tmp, $p) = each($params))
		{
			if ($p{0} == '-')
			{
				$pname = substr($p, 1);
				$value = true;
				if ($pname{0} == '-')
				{
					// long-opt (--<param>)
					$pname = substr($pname, 1);
					if (strpos($p, '=') !== false)// value specified inline (--<param>=<value>)
						list($pname, $value) = explode('=', substr($p, 2), 2);
				}
				// check if next parameter is a descriptor or a value
				$nextparm = current($params);
				if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-')
					list($tmp, $value) = each($params);
				$result[$pname] = $value;
			}
			else// param doesn't belong to any option
				$result[] = $p;
		}
		return $result;
	}
	
	/** Prints text to the standard output.
	 * This function simply prints text "as is" to the standard output, regarding to the value of the verbose setting.
	 * It also adds a line return by default, but it is possible to don't add it.
	 * 
	 * \param $text The text to print.
	 * \param $linefeed Add a line return or not. Defaults to TRUE.
	 * \return TRUE. Zero error possible.
	 */
	public static function printText($text, $linefeed = TRUE)
	{
		if(Leelabot::$verbose)
			echo $text.($linefeed ? PHP_EOL : '');
		
		return TRUE;
	}
	
	/** Dumps an array into a short and clear representable string.
	 * This function joins parts of an array into a chain of one line, representing the most accurate data that can be put in, while
	 * staying clear and (quite) comprehensible by humans. The produced string wille use a syntax similar with GET syntax, but a little
	 * clearer and spaced. It is useful for logging bunch of information, but not too much (scientific studies proved that too much information
	 * in little space confuse people).
	 * 
	 * \param $array The data to dump
	 * \return The dumped data, into a string.
	 */
	public static function dumpArray($array)
	{
		if(!is_array($array))
			$array = array(gettype($array) => $array);
		
		$return = array();
		
		foreach($array as $id => $el)
		{
			if(is_array($el))
				$return[] = $id.'=Array';
			elseif(is_object($el))
				$return[] = $id.'='.get_class($el).' object';
			elseif(is_string($el))
				$return[] = $id.'="'.$el.'"';
			elseif(is_bool($el))
				$return[] = $id.'='.($el ? 'TRUE' : 'FALSE');
			else
				$return[] = $id.'='.(is_null($el) ? 'NULL' : $el);
		}
		
		return join(', ', $return);
	}
	
	/** Parses a string data to get a boolean.
	 * This function reads the string $var and returns the boolean of it, depending of its value. "1", "on", "true" and "yes" are recognized as TRUE, everything else
	 * as FALSE.
	 * 
	 * \param $var The string to read.
	 * 
	 * \return TRUE or FALSE.
	 */
	public static function parseBool($var)
	{
		if(in_array(strtolower($var), array('1', 'on', 'true', 'yes')))
			return TRUE;
		else
			return FALSE;
	}
	
	/** Shows a message to the standard output.
	 * This function shows a predefined message to the standard output. Unlike the printText function (which is a low-level function),
	 * this one translates the message in the current locale, date it and adds a tag to it. It also writes it on the program log.
	 * For proper translations, the message needs to be separated from his variable parts. For this, you can use \$X for a varaiable,
	 * where X corresponds to the key for the value in the $args array
	 * 
	 * \param $message The message to show. It will be translated according to the translation tables available.
	 * \param $args The variables to bind with the identifiers in $message.
	 * \param $type The type of the message. Available types are based on PHP error types constants (user errors constants, i.e. starting with "E_USER"
	 * 				are also available) :
	 * 				\li E_NOTICE : A notice message (useful for debug and info messages). The default.
	 * 				\li E_WARNING : A non-fatal error.
	 * 				\li E_ERROR : A fatal error. The program is expected to end after an error of this type.
	 * \param $force Forces the message to be displayed, even if verbose mode is not enabled
	 * \param $translate Indicates if the message has to be translated.
	 * \return TRUE if the message has been correctly displayed, FALSE if an error occured.
	 */
	public static function message($message, $args = array(), $type = E_NOTICE, $force = FALSE, $translate = TRUE)
	{
		$verbosity = 1;
		$prefix = "";
		//Getting type string
		switch($type)
		{
			case E_NOTICE:
			case E_USER_NOTICE:
				$prefix = 'Notice';
				break;
			case E_WARNING:
			case E_USER_WARNING:
			$prefix = 'Warning';
				if(PHP_OS == 'Linux') //If we are on Linux, we use colors
					echo "\033[0;33m";
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$prefix = 'Error';
				$force = TRUE;
				$verbosity = 0;
				if(PHP_OS == 'Linux') //If we are on Linux, we use colors (yes, I comment twice)
					echo "\033[0;31m";
				break;
			case E_DEBUG:
				$prefix = 'Debug';
				$verbosity = 2;
				break;
			default:
				$prefix = 'Unknown';
		}
		
		//Translating prefix
		if($translate)
			$prefix = Leelabot::$instance->intl->translate($prefix);
		
		//Translating message
		if($translate)
			$message = Leelabot::$instance->intl->translate($message);
		
		//Parsing message vars
		if(!is_array($args))
			$args = array($args);
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);
		
		if(in_array($type, array(E_USER_ERROR, E_ERROR, E_WARNING, E_USER_WARNING)))
			Leelabot::$_lastError = $message;
		
		//Put it in log, if is opened
		if(Leelabot::$_logFile)
			fputs(Leelabot::$_logFile, date(($translate ? Leelabot::$instance->intl->getDateTimeFormat() : "m/d/Y h:i:s A")).' -- '.$prefix.' -- '.$message.PHP_EOL);
		
		if(Leelabot::$verbose >= $verbosity || $force)
		{
			echo date(($translate ? Leelabot::$instance->intl->getDateTimeFormat() : "m/d/Y h:i:s A")).' -- '.$prefix.' -- '.$message.PHP_EOL;
			if(PHP_OS == 'Linux')
				echo "\033[0m";
		}
	}
	
	/** Returns the last error put in the log.
	 * This method returns the last error put in the log, useful for returning error descriptors directly to user, avoiding him to go look in the log.
	 * 
	 * \return The last error message, or NULL if there is still no error.
	 */
	public static function lastError()
	{
		return self::$_lastError;
	}
	
	/** Error handler for Leelabot
	 * Handles errors thrown by PHP, writing them into the log along internal messages (useful for debugging from user experience).
	 */
	public static function errorHandler($errno, $errstr, $errfile, $errline)
	{
		//Don't log error if it's a socket_accept() error (it floods the log)
		if(strpos($errstr, 'socket_accept') !== FALSE)
			return TRUE;
		
		if(Leelabot::$verbose >= 2 || $errno == E_ERROR )
			Leelabot::message('Error in $0 at line $1 : $2', array($errfile, $errline, $errstr), $errno, FALSE, FALSE);
	}
	
	/** Generates an UUID.
	 * This function generates an UUID.
	 * 
	 * \author Anis uddin Ahmad <admin@ajaxray.com>
	 * 
	 * \param $prefix An optional prefix.
	 * 
	 * \return The UUID.
	 */
	public static function UUID($prefix = '')
	{
		$chars = md5(uniqid(mt_rand(), true));
		$uuid  = substr($chars,0,8) . '-';
		$uuid .= substr($chars,8,4) . '-';
		$uuid .= substr($chars,12,4) . '-';
		$uuid .= substr($chars,16,4) . '-';
		$uuid .= substr($chars,20,12);
		return $prefix . $uuid;
	}
	
	/** Gives the string equivalent for a boolean value.
	 * This function returns the string equivalent of a boolean, "true" or "false". If $bool is not a boolean, it will be casted to it.
	 * 
	 * \param $bool The boolean to transform.
	 * 
	 * \returns The string representation of the given boolean.
	 */
	public static function boolString($bool)
	{
		return $bool == true ? 'true' : 'false';
	}
	
	
	/** Start function of monitor cpu 
	 * This function define constant needed by getCpuUsage() function
	 * 
	 * \returns nothing
	 */
	private function cpuRequestStart()
	{
		$dat = getrusage();
		define('PHP_TUSAGE', microtime(true));
		define('PHP_RUSAGE', $dat["ru_utime.tv_sec"]*1e6+$dat["ru_utime.tv_usec"]);
	}
 
	
	/** Gives the current cpu usage
	 * This function returns the cpu usage of bot in %
	 * 
	 * \returns The cpu usage of bot.
	 */
	public static function getCpuUsage()
	{
	    $dat = getrusage();
	    $dat["ru_utime.tv_usec"] = ($dat["ru_utime.tv_sec"]*1e6 + $dat["ru_utime.tv_usec"]) - PHP_RUSAGE;
	    $time = (microtime(true) - PHP_TUSAGE) * 1000000;
	 
	    // cpu per request
	    if($time > 0) {
	        $cpu = sprintf("%01.2f", ($dat["ru_utime.tv_usec"] / $time) * 100);
	    } else {
	        $cpu = '0.00';
	    }
	 
	    return $cpu;
	}
}

/**
 * \brief Storage class.
 * 
 * This class stores the data contained in an array into (public) properties of itself.
 */
class Storage
{
	/** Constructor.
	 * This constructor takes the array in parameter and transforms it into properties of the object.
	 * 
	 * \param $array The array to be translated.
	 */
	public function __construct($array)
	{
		foreach($array as $name => $value)
			$this->$name = $value;
	}
	
	/** Transforms an instance of Storage into the array of all its properties.
	 * Basically, this method does the exact opposite of the class constructor. It takes all properties of the Storage object given in argument and returns an array
	 * of all its properties.
	 * 
	 * \param $object The Storage object to transform into array.
	 * 
	 * \return An associative array containing all properties using the shape : propname => value
	 */
	public function toArray()
	{
		$return = array();
		foreach($this as $var => $val)
			$return[$var] = $val;
		
		return $return;
	}
	
	/** Merges a Storage object with another, or with an array.
	 * This functions merges the properties of the Storage object $from with the available properties (or elements if it is an array) of $to, and returns the result.
	 * 
	 * \param $to The Storage object or the array to be merged on the object.
	 * 
	 * \returns the merged Storage object if it merged successfully, or FALSE otherwise.
	 */
	public function merge($to)
	{
		foreach($to as $key => &$el)
			$this->$key = $el;
		
		return $this;
	}
}

set_error_handler('Leelabot::errorHandler');
