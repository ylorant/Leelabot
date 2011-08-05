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
 * \brief Main class for leelabot.
 * 
 * This class is the main class for the bot, though it does not parses the server's messages (this is done by the instance class).
 * This class allow multiple server instances to run together, by dispatching calls to the instances, and giving separate configurations to
 * all the instances.
 */
class Leelabot
{
	private $_configDirectory; ///< Config directory. Defaults to ./conf directory.
	public static $verbose; ///< Verbose mode (boolean, defaults to FALSE).
	
	/** Initializes the bot.
	* Initializes the bot, by reading arguments, parsing configs sections, initializes server instances, and many other things.
	* 
	* \param $arguments list of arguments provided to the launcher, or generated ones (for further integration into other systems).
	* \return TRUE in case of success, FALSE otherwise.
	*/
	public function init($arguments)
	{
		//Setting default values for class attributes
		$this->_configDirectory = 'conf/';
		Main::$verbose = FALSE;
		
		//Parsing CLI arguments
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
					Main::$verbose = TRUE;
					break;
			}
		}
		
		$this->loadConfig();
	}
	
	/** Main loop.
	* Main loop for the bot, blocking.
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
		if(!($dirContent = scandir($this->_configDirectory)))
			return FALSE;
		
	}
	
	/** Changes the configuration directory.
	 * Changes the configuration directory to $path. If $path is a file (like main config file for example),
	 * configuration directory will be guessed from this file.
	 * 
	 * \param $path The path where the bot will read the config.
	 * \return 	TRUE if config location successfully changed, FALSE otherwise (mainly because $dir is 
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
	public static function parseArgs($argv)
	{
		array_shift($argv);
		$out = array();
		foreach ($argv as $arg)
		{
			if (substr($arg,0,2) == '--')
			{
				$eqPos = strpos($arg,'=');
				if ($eqPos === false)
				{
					$key = substr($arg,2);
					$out[$key] = isset($out[$key]) ? $out[$key] : true;
				}
				else
				{
					$key = substr($arg,2,$eqPos-2);
					$out[$key] = substr($arg,$eqPos+1);
				}
			}
			else if (substr($arg,0,1) == '-')
			{
				if (substr($arg,2,1) == '=')
				{
					$key = substr($arg,1,1);
					$out[$key] = substr($arg,3);
				}
				else
				{
					$chars = str_split(substr($arg,1));
					foreach ($chars as $char)
					{
						$key = $char;
						$out[$key] = isset($out[$key]) ? $out[$key] : true;
					}
				}
			}
			else
				$out[] = $arg;
		}
		return $out;
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
		if(Main::$verbose)
			echo $text.($linefeed ? PHP_EOL : '');
		
		return TRUE;
	}
	
	/** Shows a message to the standard output.
	 * This function shows a predefined message to the standard output. Unlike the printText function (which is a low-level function),
	 * this one translates the message in the current locale, date it and adds a tag to it. It also writes it on the program log.
	 * For proper translations, the message needs to be separated from his variable parts. For this, you can use \\X for a varaiable,
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
	public static function message($message, $args = array(), $type = E_NOTICE)
	{
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
				break;
			default:
				return FALSE;
		}
	}
}
