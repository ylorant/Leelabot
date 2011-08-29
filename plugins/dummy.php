<?php
/**
 * \file plugins/dummy.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Dummy plugin for Leelabot.
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
 * This file contains the dummy plugin made for testing Leelabot core. As all test files, this will NOT be further documented.
 */
class PluginDummy
{
	private $_main;
	private $_plugins;
	
	public function __construct(&$plugins, &$main)
	{
		$this->_plugins = $plugins;
		$this->_main = $main;
	}
	
	public function RoutineCheckPlayers()
	{
		
	}
	
	public function SrvEventClientConnect($command)
	{
		
	}
	
	public function CommandKick($player, $command)
	{
		
	}
}

return $this->initPlugin(array(
'name' => 'dummy',
'className' => 'PluginDummy',
'autoload' => TRUE));
