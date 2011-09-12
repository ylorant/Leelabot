<?php

/**
 * \file utils/langfiller.php
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
 * This scripts will parse any file or directory you give in parameter in searching output messages, and generate an output schema for Intl_Parser that you will just
 * have to fill with the good translations (in other words, it will generate a file with all #from and empty #to you will have to complete).
 */

//For access to method Leelabot::parseArgs()
require('../core/leelabot.class.php');

array_shift($argv);
$args = Leelabot::parseArgs($argv);
if(is_file($args[0]))
{
	$contents = file_get_contents($args[0]);
	preg_match_all('#Leelabot::message\(("|\')(.+)("|\',|\'\))(.+\))?;#isU', $contents, $matches);
	
	file_put_contents(pathinfo($args[0], PATHINFO_FILENAME).'.lc', '#from '.join("\n#to \n\n#from ", $matches[2])."\n#to ");
}
elseif(is_dir($args[0]))
{
	$dir = scandir($args[0]);
	foreach($dir as $file)
	{
		$contents = file_get_contents($args[0].'/'.$file);
	preg_match_all('#Leelabot::message\(("|\')(.+)(",|"\)|\',|\'\))(.+\))?;#isU', $contents, $matches);
	
	file_put_contents($file.'.lc', '#from '.join("\n#to \n\n#from ", $matches[2])."\n#to ");
	}
}
else
	echo 'You must specify a valid file/dir input.'."\n";
