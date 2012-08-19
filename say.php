<?php
//Si commande au bot
if(preg_match('#^!#',$say[2]))
{
	$admin_commands = array('!kick','!whois','!cfg');
	$user_commands = array('!help','!poke','!time','!about');
	if($players[$cmd[2]][1] == $admin)
	{
		$say=explode(' ',$say[2]);
		if($say[0] == '!kick')
		{
			$kick = SearchPlayer(trim($say[1]),$players);
			if(count($kick) == 1)
				RCon('clientkick '.$kick[0][0].'',$password,$addr,$port);
			print_r($kick);
		}
		elseif($say[0] == '!cfg')
		{
			 RCon('exec cfg/'.$say[1].'.cfg',$password,$addr,$port);
		}
		elseif($say[0] == '!whois')
		{
			$whois=SearchPlayer(trim($say[1]),$players);
			if(count($whois) == 1)
			{
				$team=TeamName($whois[10]);
				RCon('tell '.$cmd[2].' "Whois ^2'.$whois[0][2].' : IP:'.$whois[0][9].', Team : '.$team.'',$password,$addr,$port);
			}
		}
	}
	//Message d'erreur disant que l'on a pas les droits SI une commande admin est exécutée
	if(in_array($say[0],$admin_commands) && $players[$cmd[2]][1] == $admin)
		RCon('tell '.$cmd[2].' "^7Vous n\'avez pas les droits pour executer cette commande.',$password,$addr,$port);
	if($say[0] == '!help')
	{
		RCon('tell '.$cmd[2].' "^7Voici la liste des commandes pour LeelaBot (les commandes admin sont en ^1Rouge^7) :',$password,$addr,$port);
		RCon('tell '.$cmd[2].' "^7!help, !time, !poke, !about, ^1!kick^7, ^1!cfg^7,^1!whois^7.',$password,$addr,$port);
	}
	
}