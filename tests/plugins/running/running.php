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
 * This file contains the class and the run code for testing plugins in running case, by adding a dummy parser to the code. This file will NOT be further documented.
 */

chdir('../../../');

require('core/db.class.php');
require('core/innerapi.class.php');
require('core/outerapi.class.php');
require('core/plugins.class.php');
require('core/RCon.class.php');
require('core/intl.class.php');
require('core/leelabot.class.php');

class RunningTest extends Leelabot
{
	private $_fp;
	
	public function run()
	{
		$this->_fp = fopen('php://stdin', 'r');
		stream_set_blocking($this->_fp, 0);
		while(1)
		{
			$read = array(&$this->_fp);
			$write = array();
			stream_select($read,$write,$write,0);
			if(count($read) != 0)
			{
				$line = fgets($this->_fp);
				$this->parseLine($line);
			}
			$this->plugins->callAllRoutines();
			usleep(1000);
		}
	}
	
	public function parseLine($line)
	{
		//Commands looks like "<server or client>:[ ]<event> <parameters>\n"
		$line = explode(':', trim($line), 2);
		$eventType = $line[0];
		if(isset($line[1]))
			$params = explode(' ', trim($line[1]));
		else
			$params = array('', '');
		$command = array_shift($params);
		if($eventType == 'server')
			$this->plugins->callServerEvent($command, $params);
		elseif($eventType == 'client')
			$this->plugins->callCommand($command, array(), $params);
	}
}

$main = new RunningTest();
$main->init(explode(' ', '-c tests/plugins/running/running_conf -v'));
$main->run();
