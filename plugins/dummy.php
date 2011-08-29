<?php

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
