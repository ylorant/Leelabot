<?php

/**
 * \file core/update.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Updater class file.
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
 * This file hosts the Updater class, allowing the bot to automatically update itself when an update is available.
 */

/**
 * \brief Updater class for leelabot.
 * 
 * This class allow Leelabot to automatically update itself. If it is actived, it will check the updates on leelabot servers and update the
 * bot if necessary.
 * 
 */
class Updater
{
	private $_mirror;
	
	public function __construct($config, $plugins)
	{
		if(isset($config['Mirror']))
		{
			Leelabot::message('Checking update mirror $0', $config['Mirror']);
			if($this->getLastVersion($config['Mirror']))
				$this->_mirror = $config['Mirror'];
			else
			{
				Leelabot::message('Can\'t get version from mirror, falling back to default.', array(), E_WARNING);
			}
		}
		else
			$this->_mirror = 'http://leelabot.com/';
		
		$plugins->addRoutine($this, 'checkUpdate', 3600);
	}
	
	public function getLastVersion($mirror = NULL)
	{
		if($mirror === NULL)
			$mirror = $this->_mirror;
		
		$mirror .= substr($mirror, -1) == '/' ? '' : '/';
		
		$xml = file_get_contents($this->_mirror.'update/versions');
		
		if(!$xml)
			return FALSE;
		
		$data = simplexml_load_string($xml);
		
		if(!$data)
			return FALSE;
		
		return $data->version[0];
	}
	
	public function checkUpdate()
	{
		if(!preg_match('/: ([0-9]+) \$/', Leelabot::REVISION, $matches)
			return FALSE;
		
		$last = $this->getLastVersion();
		
		if($last->rev <= $matches[0])
			return;
	}
}
