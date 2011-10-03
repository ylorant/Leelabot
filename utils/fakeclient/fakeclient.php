<?php

if(file_exists('logfile.txt'))
	$logfile = file_get_contents('logfile.txt');
else
{
	echo 'Where is the logfile ?'."\nlogfile> ";
	$logfile = trim(fgets(STDIN));

	if(!file_exists($logfile))
		exit('This logfile does not exists');
	
	file_put_contents('logfile.txt', $logfile);
}

$fp = fopen($logfile, 'a+');

$choice = -1;
$players = array();
while($choice != 0)
{
	echo '> ';
	$command = trim(fgets(STDIN));
	$command = explode(' ', $command, 3);
	$message = '  0:00 ';
	$send = TRUE;
	switch($command[0])
	{
		case 'Command':
			$message .= 'say: '.$command[1].' '.$players[$command[1]]['name'].': '.$command[2];
			break;
		case 'Connect':
			$players[$command[1]] = array();
			$message .= 'ClientConnect: '.$command[1];
			break;
		case 'SendUserinfo':
			$message .= 'ClientUserinfo: '.$command[1].' \\ip\\'.$players[$command[1]]['ip'].'\\name\\'.$players[$command[1]]['name'].'\\racered\\1\\raceblue\\1\\rate\\8000\\ut_timenudge\\0\\cg_rgb\\128 128 128\\cg_predictitems\\0\\cg_physics\\1\\cl_anonymous\\0\\sex\\male\\handicap\\100\\color2\\5\\color1\\4\\team_headmodel\\*james\\team_model\\james\\headmodel\\sarge\\model\\sarge\\snaps\\20\\gear\\GKAARWA\\teamtask\\0\\cl_guid\\C2633300415D2FC2976752BB49A58CC3\\weapmodes\\01000010020000010002'."\n";
			$message .= '  0:00 ClientUserinfoChanged: '.$command[1].' n\\'.$players[$command[1]]['name'].'\\t\\'.$players[$command[1]]['team'].'\\r\\1\\tl\\0\\f0\\\\f1\\\\f2\\\\a0\\0\\a1\\0\\a2\\255';
			break;
		case 'Name':
		case 'IP':
		case 'Team':
		//case 'Bot':
			$players[$command[1]][strtolower($command[0])] = $command[2];
			$send = FALSE;
			break;
	}
	
	if($send)
		fputs($fp, $message."\n");
}
