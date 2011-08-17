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
	
	/** Initializes the bot.
	* Initializes the bot, by reading arguments, parsing configs sections, initializes server instances, and many other things.
	* 
	* \param $arguments list of arguments provided to the launcher, or generated ones (for further integration into other systems).
	* \return TRUE in case of success, FALSE otherwise.
	*/
	public function init($arguments)
	{
		//Setting default values for class attributes
		Leelabot::$instance = &$this;
		$this->_configDirectory = 'conf';
		Leelabot::$verbose = FALSE;
		
		//Loading child classes
		$this->intl = new Intl("en");
		
		//Parsing CLI arguments
		$arguments = Leelabot::parseArgs($arguments);
		krsort($arguments); //To get verbose parameter (-v) first
		foreach($arguments as $name => $value)
		{
			switch($name)
			{
				//Change config file or directory (root config directory will be guessed from config file if given)
				case 'c':
				case 'config':
					$this->setConfigLocation($value);
					break;
				//Enable verbose mode.
				case 'v':
				case 'verbose':
					Leelabot::$verbose = TRUE;
					Leelabot::message('Starting in Verbose mode.');
					Leelabot::message('Command arguments : $0', array($this->dumpArray($arguments)));
					break;
				//Change display language
				case 'lang':
					Leelabot::message('Forced locale : $0', array($value));
					if(!$this->intl->setLocale($value))
						Leelabot::message('Cannot change locale to "$0"', array($value), E_WARNING);
					else
						Leelabot::message('Locale successfully changed to "$0".', array($value));
					break;
			}
		}
		
		$this->loadConfig();
	}
	
	/** Leelabot loop.
	* Leelabot loop for the bot, blocking.
	* 
	* \return TRUE in case of success when user asked the loop to end, FALSE if an error occured.
	*/
	public function run()
	{
		
	}
	
	/** Loads configuration from .ini files.
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
		
		if(!($dirContent = scandir($this->_configDirectory)))
			return FALSE;
		
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
	* \param $argv command-line formatted list of arguments
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
	 * \param $type The type of the message. Available types are based on PHP error types constants :
	 * 				\li E_NOTICE : A notice message (useful for debug and info messages). The default.
	 * 				\li E_WARNING : A non-fatal error.
	 * 				\li E_ERROR : A fatal error. The program is expected to end after an error of this type.
	 * \return TRUE if the message has been correctly displayed, FALSE if an error occured.
	 */
	public static function message($message, $args = array(), $type = E_NOTICE, $force = FALSE)
	{
		//Getting type string
		switch($type)
		{
			case E_NOTICE:
				$prefix = 'Notice';
				break;
			case E_WARNING:
				$prefix = 'Warning';
				break;
			case E_ERROR:
				$prefix = 'Error';
				$force = TRUE;
				break;
			default:
				return FALSE;
		}
		
		$prefix = Leelabot::$instance->intl->translate($prefix);
		
		//Translating message
		$message = Leelabot::$instance->intl->translate($message);
		
		//Parsing message vars
		foreach($args as $id => $value)
			$message = str_replace('$'.$id, $value, $message);
		
		//Put it in log, if is opened
		if(Leelabot::$_logFile)
			fputs(Leelabot::$_logFile, date(Leelabot::$instance->intl->getDateTimeFormat()).' -- '.$prefix.' -- '.$message.PHP_EOL);
		
		if(Leelabot::$verbose || $force)
			echo date(Leelabot::$instance->intl->getDateTimeFormat()).' -- '.$prefix.' -- '.$message.PHP_EOL;
	}
}
