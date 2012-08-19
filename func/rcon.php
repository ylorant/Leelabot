<?php
$socket = fsockopen('udp://'.$addr.':'.$port.'');
$cmd = "\xFF\xFF\xFF\xFFrcon ".$password." ".$cmd."\x00";
       if ($socket){
       stream_set_timeout($socket, 3);
       $length = strlen($cmd);
	$reponse=FALSE;
       while(!$reponse)
       {
       fwrite($socket, $cmd, $length);
       $reponse = fgets($socket, 1500);
       }
$return=TRUE;
}
else
{
$return=FALSE;
}