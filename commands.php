<?php
/*
Note : Construction de l'array $players[x] :
0 : ID client
1 : GUID
2 : pseudo
3 : Kills (manche)
4 : Deaths (Manche)
5 : Kills (Total)
6 : Deaths (Total)
7 : Warnings
8 : Alias
9 : IP
10 : Team

Color codes :
0 = Black
1 = Red
2 = Green
3 = Yellow
4 = Blue
5 = Light blue
6 = Pink
7 = White 

*/

//On lit TOUT le fichier ligne par ligne (queue)
while(!feof($fichier))
{
	//On lit
	$game=fgets($fichier);
	echo $game;
	//On parse
	$cmd = explode(' ',trim($game));
	//On exécute en fonction des commandes
	switch($cmd[1])
	{
		case 'ClientConnect:':
			$players[$cmd[2]][0] =  $cmd[2];
			break;
		case 'ClientBegin:':
			$players[$cmd[2]][3] = 0;
			$players[$cmd[2]][4] = 0;
			break;
		case 'ClientUserinfo:':
			$cmd[3] = $cmd[3].$cmd[4].$cmd[5];
			$userInfo = explode('\\',$cmd[3]);
			$players[$cmd[2]][9] = $userInfo[2];
			$players[$cmd[2]][2] = $userInfo[array_search('name',$userInfo)+1];
			$players[$cmd[2]][1] = $userInfo[array_search('cl_guid',$userInfo)+1];
			break;
		case 'ClientUserinfoChanged:':
			$userInfo = explode('\\',$cmd[3]);
			$players[$cmd[2]][10] = $userInfo[3];
			$players[$cmd[2]][2] = $userInfo[array_search('n',$userInfo)+1];
			break;
		case 'ClientDisconnect:' :
			unset($players[$cmd[2]]);
			break;
		case 'say:':
		case 'say_team':
			$say=explode(': ',$game);
			include('say.php');
			break;
	}
	
}