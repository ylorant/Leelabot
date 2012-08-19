<?php
$version = "0.0.0.0.1";

/*Copyrights, header etc.*/
echo "          .,annn.                     
       ,+adXXXXXXbn.                       
      ,d>XXXXXXXXXXXb.     .,an-.                    
       ,dXXXXXXXXXXXXPW.,adXXXXX<_                       
      ,dXXXXXXXP\"'   >H*XXXXXXXXXXbn.                   
      ;XXXXXXXP    .aXXXXXXXXXXXXXX<                 
      iXXXXXXI    ;XXXXXX'I;;\"XXXXXI           
      IXXXXXX!    'YXXXXX;....YXXXX!           
      IXXXXXX;      YXXXX'.,--.XXXP'      
     .dXXXXXX;      '\"MYV.(  , YXX\"      
     ;XXXXXXX;       (@M\"..`-..;YX   
     iXXXXXXX'         !.......;;             
    ,dXXXXXP\"         ,;...,,,;;;                 
_.,aXXXYXXP'          ;'...**=-=\"                    
'\"~*@^' \"'          ,,;....;..,            
                  ,'..'....\"'..;.        
               ,-'...;  .....;....          
              ,'......) '..,.. '...`.          

LeelaBot : garde un oeil sur votre serveur !\n";
echo "Par Linkboss. Version ".$version.".\n\n";

/*---Fonctions utiles du programme---*/
//Exécute une commande RCon sur un serveur donné en paramètre
function RCon($cmd,$password,$addr,$port)
{
	include('func/rcon.php');
	return $return;
}

//Remplacer le numero d'equipe par le nom (red, blue, spec)
function TeamName($number)
{
	include('func/team.php');
	return $return;
}

//Vérifie si un diminutif de joueur existe
function SearchPlayer($player,$players)
{
	include('func/searchplayer.php');
	return $return;
}

/*Chargement de la config*/
echo "Chargement de la configuration...";
if(is_file('leelabot.ini'))
{
	$config = parse_ini_file('leelabot.ini');
	$addr=$config['addr'];
	$password=$config['password'];
	$admin=$config['admin'];
	$port=$config['port'];
	$logfile=$config['logfile'];
	echo "OK\n";
}
else
{
	echo "ERREUR\n";
	exit;
}
echo "Connexion au log de jeu...";
if(!is_file($logfile))
{
	echo "ERREUR\n";
	exit;
}
else
{
	echo "OK\n";
}
echo "Connexion au RCon...";
if(!RCon('getstatus',$password,$addr,$port))
{
	echo "ERREUR\n";
	exit;
}
else
{
	echo "OK\n";
}
echo "Bot lancé.\n";
RCon('bigtext "LeelaBot, garde un oeil sur votre serveur !"',$password,$addr,$port);
//Ouverture du log de messages
$log=fopen('msgs.log','a+');
//Purge du log de jeu
$buf=fopen($logfile,'w+');
fclose($buf);
while(1)
{
	//On ouvre le log de jeu
	$fichier=fopen($logfile,'r') or die("errir");
		//Inclusion du fichier de gestion des commandes du log
		include('commands.php');
	//Et on purge le log de jeu
	$buf=fopen($logfile,'w+');
	fclose($buf);
}