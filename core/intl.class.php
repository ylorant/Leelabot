<?php
/**
 * \file core/intl.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Internationalization class file.
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
 * This file hosts the Internationalization class, allowing the bot to talk in multiple languages and use multiple date formats.
 */

/**
 * \brief Internationalization class.
 * 
 * This class is the Internationalization class, allowing the bot to talk in different languages with ease and use different date formats
 * (in accordance with the related language). It reads locale data (messages, date format and alias) from configuration files, wrote with
 * a special syntax.
 */
class Intl
{
	private $_locale; ///< Current set locale.
	private $_root; ///< Locales root directory.
	private $_data; ///< Locale data.
	public $init; ///< Allow to know if first locale loading is successful (at instanciation)
	
	/** Constructor.
	 * This is the constructor for the class. It initalizes properties at their default values, and set the default locale if a parameter is
	 * given.
	 * 
	 * \return The object currently created. If the locale is not available, FALSE will be returned.
	 */
	public function __construct($locale = NULL)
	{
		//Setting default values for properties
		$this->_root = 'data/locales';
		
		$this->init = TRUE;
		if($locale)
		{
			if(!$this->setLocale($locale))
				$this->init = FALSE;
		}
	}
	
	/** Returns the list of available locales.
	 * This function returns the list of all available locales, according with their parameter #display.
	 * 
	 * \return An associative array containing the locales ('identifier' => 'name').
	 */
	public function getLocaleList()
	{
		$list = array();
		$dir = scandir($this->_root);
		foreach($dir as $el)
		{
			if(is_dir($this->_root.'/'.$el))
			{
				if(is_file($this->_root.'/'.$el.'/lc.conf')) //Alias check
				{
					$parser = new Intl_Parser();
					$data = $parser->parseFile($this->_root.'/'.$el.'/lc.conf');
					unset($parser);
					$list[$el] = $data['display'];
				}
				else
					$list[$el] = $el;
			}
		}
		
		return $list;
	}
	
	/** Returns the current locale.
	 * This function returns the current locale set with the function setLocale, or, if not set, the default locale.
	 * 
	 * \return The current set locale.
	 * 
	 * \see Intl::setLocale()
	 */
	public function getLocale()
	{
		return $this->_locale;
	}
	
	/** Returns the current locale directory root.
	 * This function returns the current locale directory root set with the function setRoot, or, if not set, the default directory.
	 * 
	 * \return The current set locale directory root.
	 * 
	 * \see Intl::setRoot()
	 */
	public function getRoot()
	{
		return $this->_root;
	}
	
	/** Sets the class' locale directory root.
	 * This function sets the current locale directory root to the specified one given as parameter. It checks if the directory exists before.
	 * 
	 * \param $root The directory to set.
	 * \return TRUE if the directory has been changed correctly, FALSE if the directory does not exists.
	 * 
	 * \see Intl::getRoot()
	 */
	public function setRoot($root)
	{
		//Trim ending slashes
		if(substr($root, -1) == '/')
			$root = ltrim($root, '/');
			
		if(is_dir($root))
			$this->_root = $root;
		else
			return FALSE;
		
		return TRUE;
	}
	
	/** Sets the class' locale.
	 * This function sets the current locale to the specified one given as parameter. It also loads the locale from the configuration files.
	 * 
	 * \param $locale The locale to set.
	 * \return TRUE if the locale has been changed correctly, FALSE otherwise (specified locale does not exists, specified locale is corrupted...).
	 *
	 * \see Intl::getLocale()
	 */
	public function setLocale($locale)
	{
		if(!($dir = $this->localeExists($locale)))
			return FALSE;
		
		$parser = new Intl_Parser();
		if(!($data = $parser->parseDir($this->_root.'/'.$dir)))
			return FALSE;
		
		$this->_data = $data;
		return TRUE;
	}
	
	/** Checks if a locale exists.
	 * This function checks if the locale exists, by its folder name, or by an alias.
	 * 
	 * \param $locale The locale to set.
	 * \return The locale's real name if the locale exists, FALSE if not.
	 */
	public function localeExists($locale)
	{
		$dir = scandir($this->_root);
		foreach($dir as $el)
		{
			if(is_dir($this->_root.'/'.$el))
			{
				if($el == $locale) //Folder name check
					return $el;
				
				if(is_file($this->_root.'/'.$el.'/lc.conf')) //Alias check
				{
					$parser = new Intl_Parser();
					$data = $parser->parseFile($this->_root.'/'.$el.'/lc.conf');
					unset($parser);
					if(isset($data['aliases']) && in_array($locale, $data['aliases']))
						return $el;
				}
			}
		}
		
		return FALSE;
	}
	
	/** Translate a text in the set locale.
	 * This function translate the given text or give the message associated with an identifier in the current set locale.
	 * 
	 * \param $from The text to translate, or the identifier of the message to get.
	 * \return The translated message if it exists, the original message if not.
	 */
	public function translate($from)
	{
		//ID-based translation
		if(isset($this->_data['content'][$from]) && isset($this->_data['content'][$from]['to']))
			return $this->_data['content'][$from]['to'];
		
		//Text search
		foreach($this->_data['content'] as $pair)
		{
			if($pair['from'] == $from)
				return $pair['to'];
		}
		
		return $from;
	}
	
	/** Alias for Intl::translate().
	 * This function is an alias for Intl::translate().
	 * 
	 * \see Intl::translate()
	 */
	public function _($from)
	{
		return $this->translate($from);
	}
	
	/** Returns the current date format.
	 * This function returns the date format set for the current locale. If no locale has been loaded yet, it returns English date format.
	 * 
	 * \return The current date format, or a generic one.
	 */
	public function getDateFormat()
	{
		if(isset($this->_data['dateformat']))
			return $this->_data['dateformat'];
		else
			return "m/d/Y";
	}
	
	/** Returns the current time format.
	 * This function returns the time format set for the current locale. If no locale has been loaded yet, it returns English time format.
	 * 
	 * \return The current time format, or a generic one.
	 */
	public function getTimeFormat()
	{
		if(isset($this->_data['timeformat']))
			return $this->_data['timeformat'];
		else
			return "h:i:s A";
	}
	
	/** Returns the current date and time format.
	 * This function returns the date and time format set for the current locale. If no locale has been loaded yet, it returns English format.
	 * 
	 * \return The current date and time format, or a generic one.
	 */
	public function getDateTimeFormat()
	{
		$datetime = '';
		if(isset($this->_data['dateformat']))
			$datetime .= $this->_data['dateformat'].' ';
		else
			$datetime .= "m/d/Y ";
		
		if(isset($this->_data['timeformat']))
			return $datetime.$this->_data['timeformat'];
		else
			return $datetime."h:i:s A";
	}
}

/**
 * \brief Internationalization files parser.
 * 
 * This class is used along the Intl class, for parsing locales files and folders. Its only purpose is to parse locale config files, for
 * better code separation, and lighter memory usage (the parser doesn't have to be loaded when the files have not to be parsed).
 */
class Intl_Parser
{
	private $_data; ///< Data already parsed by the parser.
	private $_tempData; ///< Temporary parsed data, mostly from the current file being parsed
	private $_currentID; ///< Current ID, for defined-ID translation pairs
	private $_globalID; ///< Current automatic global ID, for automatic translation pairs
	
	/** Constructor.
	 * This is the constructor for the class. It initalizes properties at their default values.
	 * 
	 * \return The object currently created.
	 */
	public function __construct()
	{
		//Initializing properties
		$this->_data = array('content' => array());
		$this->_globalID = 0;
		$this->_tempData = array();
	}
	
	/** Parses a locale file.
	 * This function parses an unique locale file, for gathering all available data inside. Of course, if there is #include statements in
	 * the given file, included files will be also parsed.
	 * 
	 * \warning If there is an inclusion loop, this function will loop indefinitely, filling all
	 * the available memory, so beware of what you are parsing, and what you are including.
	 * 
	 * \param $file The file to parse.
	 * \return The parsed data if the file has been correctly parsed, FALSE otherwise.
	 * 
	 * \see Intl_Parser::parseDir()
	 */
	public function parseFile($file)
	{
		if(!is_file($file))
			return FALSE;
		
		//Set-ID initialization
		$this->_currentID = NULL;
		
		$content = file_get_contents($file);
		
		//Getting rid of comments
		$content = preg_replace("#/\*(.*)\*/#isU", '', $content);
		
		//Getting commands and looping them for individual parsing
		$commands = explode("\n", $content);
		foreach($commands as $command)
		{
			$result = $this->_parseCommand($command, $file);
			if($result === FALSE)
				return FALSE;
		}
		
		//Content data verification (checks message pairs cohesion)
		foreach($this->_data['content'] as $pair)
		{
			if(!isset($pair['from']) || !isset($pair['to']))
			{
				$this->__construct();
				return FALSE;
			}
		}
		
		//Merge temporary data with final data, and empty temporary data
		$this->_data = array_merge_recursive($this->_data, $this->_tempData);
		foreach($this->_data['content'] as &$pair) //Erase precedent data if present in pairs
		{
			if(is_array($pair['from']))
				$pair['from'] = $pair['from'][1];
			if(is_array($pair['to']))
				$pair['to'] = $pair['to'][1];
		}
		$this->_tempData = array();
		
		return $this->_data;
	}
	
	/** Parses a locale directory.
	 * This function parses an complete directory, with locale files in it. It will only parse .lc files and lc.conf file. This function
	 * calls parseFile for parsing every file.
	 * 
	 * \warning If there is an inclusion loop, this function will loop indefinitely, filling all
	 * the available memory, so beware of what you are parsing, and what you are including.
	 * 
	 * \param $dir The directory to parse.
	 * \return The parsed data if the directory has been correctly parsed, FALSE otherwise.
	 * 
	 * \see Intl_Parser::parseFile()
	 */
	public function parseDir($dir)
	{
		if(!is_dir($dir))
			return FALSE;
		
		//Trim ending slashes
		if(substr($dir, -1) == '/')
			$dir = ltrim($dir, '/');
		
		$content = scandir($dir);
		foreach($content as $el)
		{
			if(is_dir($dir.'/'.$el) && $el[0] != '.')
				$this->parseDir($dir.'/'.$el);
			elseif(pathinfo($el, PATHINFO_EXTENSION) == 'lc' || $el == 'lc.conf')
			{
				$ret = $this->parseFile($dir.'/'.$el);
				if($ret === FALSE)
					return FALSE;
			}
		}
		
		return $this->_data;
	}
	
	/** Parses a command.
	 * This function simply parses a single command, stripping all parasites (like extra spaces and so) and interpreting the statements
	 * get.
	 * 
	 * \param $command The command to parse.
	 * \param $file The file from where the command comes from. It is useful for knowing the base path for the #include statement.
	 * \returns TRUE if the command parsed successfully, or FALSE if anything has gone wrong.
	 * 
	 * \see Intl_Parser::parseFile()
	 */
	private function _parseCommand($command, $file)
	{
		if(!$command)
			return TRUE;
		
		//Tabs will be interpreted as space, for splitting.
		$command = str_replace("\t", ' ', trim($command));
		$cmdArray = explode(' ', $command, 2);
		$keyword = $cmdArray[0];
		
		if(isset($cmdArray[1]))
			$params = $cmdArray[1];
		
		//Keyword (i.e. command name) parsing
		switch($keyword)
		{
			
			case '#author':
				if(!isset($this->_tempData['authors']))
					$this->_tempData['authors'] = array();
				$this->_tempData['authors'][] = $params;
				break;
			//Same behavior for these commands, globally locale info
			case '#contact':
			case '#version':
			case '#display':
			case '#license':
			case '#dateformat':
			case '#timeformat':
				$this->_tempData = array_merge($this->_tempData, array(substr($keyword, 1) => $params));
				break;
			//Locale alias names
			case '#alias':
				if(strpos($params, ';') === FALSE) //Unique alias
				{
					if(isset($this->_tempData['aliases']))
						$aliases = array_merge($this->_tempData['aliases'], array($params));
					else
						$aliases = array($params);
				}
				else //Multiple aliases
				{
					if(isset($this->_tempData['aliases']))
						$aliases = array_merge($this->_tempData['aliases'], array_map('trim',explode(';',$params)));
					else
						$aliases = array_map('trim',explode(';',$params));
				}
				$this->_tempData = array_merge($this->_tempData, array('aliases' => $aliases));
				break;
			//Include another files (relative paths from current read file path)
			case '#include':
				$dir = pathinfo($file, PATHINFO_DIRNAME);
				$result = $this->parseFile($dir.'/'.$params);
				//If parsing failed, chain
				if($result === FALSE)
					return FALSE;
				break;
			//Clears all the data in memory from previous parsing
			case '#clear':
				$this->__construct();
				break;
			//Sets current ID for ordering
			case '#id':
				$this->_currentID = $params;
				break;
			//Disable ID ordering
			case '#noid':
				$this->_currentID = NULL;
				break;
			//Source search for translation
			case '#from':
				if($this->_currentID !== NULL)
				{
					if(!isset($this->_tempData['content'][$this->_currentID]))
						$this->_tempData['content'][$this->_currentID] = array();
					
					$element = &$this->_tempData['content'][$this->_currentID];
				}
				else
				{
					$this->_tempData['content'][$this->_globalID] = array();
					$element = &$this->_tempData['content'][$this->_globalID];
				}
				$element['from'] = str_replace('\n', "\n",$params);
				break;
			//In what the source text will be translated
			case '#to':
				if($this->_currentID !== NULL)
				{
					if(!isset($this->_tempData['content'][$this->_currentID]))
						$this->_tempData['content'][$this->_currentID] = array();
					
					$element = &$this->_tempData['content'][$this->_currentID];
				}
				else
				{
					$element = &$this->_tempData['content'][$this->_globalID];
					$this->_globalID++;
				}
				$element['to'] = str_replace('\n', "\n",$params);
				break;
		}
		
		return TRUE;
	}
}
