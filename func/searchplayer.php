<?php
reset($players);
			$walk = 0;
			while(current($players) !== FALSE)
			{
				$current = current($players);
				if(stripos($current[2],$player) !== FALSE){
					$kick[$walk] = $current;$walk++;}
				next($players);
			}
if($walk = 0)
	$return = FALSE;
$return = $kick;
?>