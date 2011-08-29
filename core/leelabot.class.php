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
	private static $_logFile; ///< Log file, accessed by Leelabot::message() method.
	public static $verbose; ///< Verbose mode (boolean, defaults to FALSE).
	public static $instance; ///< Current instance of Leelabot (for accessing dynamic properties from static functions)
	public $intl; ///< Locale management object
	public $config; ///< Configuration data (for all objects : servers, plugins...)
	public $servers; ///< Server instances objects
	public $plugins; ///< Plugin manager
	
	const VERSION = '0.5-svn "Sandy"'; ///< Current bot version
	const DEFAULT_LOCALE = "en"; ///< Default locale
	
	/** Initializes the bot.
	 * Initializes the bot, by reading arguments, parsing configs sections, initializes server instances, and many other things.
	 * 
	 * \param $CLIArguments list of arguments provided to the launcher, or generated ones (for further integration into other systems).
	 * \return TRUE in case of success, FALSE otherwise.
	 */
	public function init($CLIArguments)
	{
		//Setting default values for class attributes
		Leelabot::$instance = &$this;
		$this->_configDirectory = 'conf';
		Leelabot::$verbose = FALSE;
		$this->servers = array();
		
		//Parsing CLI arguments
		$logContent = NULL;
		$CLIArguments = Leelabot::parseArgs($CLIArguments);
		
		//Loading plugins class
		$this->plugins = new PluginManager();
		
		//Checking CLI argument for root modification, and modification in case
		if($rootParam = array_intersect(array('r', 'root'), array_keys($CLIArguments)))
			chdir($CLIArguments[$rootParam[0]]);
			
		//Opening default log file (can be modified after, if requested)
		Leelabot::$_logFile = fopen('leelabot.log', 'w+');
		
		//Loading Intl class (if it is not loadable, load a dummy substitute)
		$this->intl = new Intl(Leelabot::DEFAULT_LOCALE);
		if(!$this->intl->init)
		{
			$this->intl = new Intl(); //Load class without a locale defined
			Leelabot::message('Can\'t load Intl class with default locale ($0).', array(Leelabot::DEFAULT_LOCALE), E_ERROR);
			exit();
		}
		
		Leelabot::message('Leelabot version $0 starting...', array(Leelabot::VERSION), E_NOTICE, TRUE);
		
		//Pre-parsing CLI arguments (these arguments are relative to config loading and files location)
		$this->processCLIPreparsingArguments($CLIArguments);
		
		//Loading config
		if(!$this->loadConfig())
		{
			Leelabot::message("Startup aborted : Can't parse startup config.", array(), E_ERROR);
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
					case 'LogFile':
						Leelabot::message('Changing log file to $0 (by Config)', array($value));
						//Save log content for later parameters (like --nolog -log file.log)
						if(Leelabot::$_logFile)
						{
							$logFileInfo = stream_get_meta_data(Leelabot::$_logFile);
							$logContent = file_get_contents($logFileInfo['uri']);
							fclose(Leelabot::$_logFile);
							Leelabot::$_logFile = FALSE;
						}
						
						//Load new file, and put the old log content into it (if opening has not failed, else we re-open the old log file)
						if(!(Leelabot::$_logFile = fopen($value, 'w+')))
						{
							Leelabot::$_logFile = fopen($logFileInfo['uri'], 'w+');
							Leelabot::message('Cannot open new log file ($0), reverting to old.', array($value), E_WARNING);
						}
						fputs(Leelabot::$_logFile, $logContent);
						break;
				}
			}
			unset($logContent);
		}	
		
		//Post-parsing CLI arguments (after loading the config because they override file configuration)
		$this->processCLIPostparsingArguments($CLIArguments);
		
		//Loading plugins (throws an error if there is no plugin general config, because using leelabot without plugins is as useful as eating corn flakes hoping to fly)
		if(isset($this->config['Plugins']))
		{
			if(isset($this->config['Plugins']['AutoLoad']))
			{
				$this->config['Plugins']['AutoLoad'] = explode(',', $this->config['Plugins']['AutoLoad']);
				$this->config['Plugins']['AutoLoad'] = array_map('trim', $this->config['Plugins']['AutoLoad']);
				$this->plugins->loadPlugins($this->config['Plugins']['AutoLoad']);
			}
		}
		else
			Leelabot::message('There is no plugin configuration', array(), E_WARNING);
		
		//Loading server instances
		$this->loadServerInstances();
	}
	
	/** Leelabot loop.
	* Leelabot loop for the bot, blocking.
	* 
	* \return TRUE in case of success when user asked the loop to end, FALSE if an error occured.
	*/
	public function run()
	{
		
	}
	
	//Ajouter un paramètre dans [Servers] qui permet de définir une config comme celle par défaut
	/** Loads all the server instances and defining their parameters
	 * This function loads alle the server instances found in Leelabot::$config, and initializes them with their config.
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
		
		foreach($this->config['Server'] as $instance)
		{
			
		}
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
					Leelabot::$verbose = TRUE;
					Leelabot::message('Starting in Verbose mode.');
					Leelabot::message('Command arguments : $0', array($this->dumpArray($CLIArguments)));
					break;
			}
		}
	}
	
	/** Processes pre-parsing CLI arguments.
	 * Processes the arguments who have to be processed after config loading (so they are prioritary on config, they overwrite it).
	 * 
	 * \returns TRUE if everything gone fine, FALSE if an error occured.
	 */
	public function processCLIPostparsingArguments($CLIArguments)
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
					Leelabot::message('Disabling log.');
				case 'log': //Define the log file in another place than the default
					//Save log content for later parameters (like --nolog -log file.log)
					if(Leelabot::$_logFile)
					{
						$logFileInfo = stream_get_meta_data(Leelabot::$_logFile);
						$logContent = file_get_contents($logFileInfo['uri']);
						fclose(Leelabot::$_logFile);
						Leelabot::$_logFile = FALSE;
					}
					if($name == 'no-log')
						break;
					Leelabot::message('Changing log file to $0 (By CLI)', array($value));
					//Load new file, and put the old log content into it (if opening has not failed, else we re-open the old log file)
					if(!(Leelabot::$_logFile = fopen($value, 'w+')))
					{
						Leelabot::$_logFile = fopen($logFileInfo['uri'], 'w+');
						Leelabot::message('Cannot open new log file ($0), reverting to old.', array($value), E_WARNING);
					}
					fputs(Leelabot::$_logFile, $logContent);
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
		$config = $this->parseCFGDirRecursive($this->_configDirectory);
		
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
	public function parseCFGDirRecursive($dir)
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
				if($fileConf = $this->parseCFGDirRecursive($dir.'/'.$file))
					$finalConfig = array_merge($finalConfig, $fileConf);
				else
				{
					Leelabot::message('Parse error in $0 directory.', array($dir), E_WARNING);
					return FALSE;
				}
			}
		}
		
		//Parsing string and determining recursive array
		$inidata = parse_ini_string($cfgdata, TRUE);
		if(!$inidata)
			return FALSE;
		foreach($inidata as $section => $content)
		{
			if(is_array($content))
			{
				$section = explode('.', $section);
				//Getting reference on the config category pointed
				$edit = &$finalConfig;
				foreach($section as $el) 
					$edit = &$edit[$el];
				
				$edit = $content;
			}
			else
				Leelabot::message('Orphan config parameter : $0', array($section), E_WARNING);
		}
		
		return $finalConfig;
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
	function parseArgs($args)
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
	public function dumpArray($array)
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
	 * \return TRUE if the message has been correctly displayed, FALSE if an error occured.
	 */
	public static function message($message, $args = array(), $type = E_NOTICE, $force = FALSE, $translate = TRUE)
	{
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
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$prefix = 'Error';
				$force = TRUE;
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
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);
		
		//Put it in log, if is opened
		if(Leelabot::$_logFile)
			fputs(Leelabot::$_logFile, date(($translate ? Leelabot::$instance->intl->getDateTimeFormat() : "m/d/Y h:i:s A")).' -- '.$prefix.' -- '.$message.PHP_EOL);
		
		if(Leelabot::$verbose || $force)
			echo date(($translate ? Leelabot::$instance->intl->getDateTimeFormat() : "m/d/Y h:i:s A")).' -- '.$prefix.' -- '.$message.PHP_EOL;
	}
	
	/** Error handler for Leelabot
	 * Handles errors thrown by PHP, writing them into the log along internal messages (useful for debugging from user experience).
	 */
	public static function errorHandler($errno, $errstr, $errfile, $errline)
	{
		Leelabot::message('Error in $0 at line $1 : $2', array($errfile, $errline, $errstr), $errno, FALSE, FALSE);
	}
}

set_error_handler('Leelabot::errorHandler');
