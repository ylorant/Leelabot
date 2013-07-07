<?php

class LeelabotAdminPlugins
{
	private $_dispatcher;
	private $_files;
	private $_loadedPlugins;
	
	public function __construct(&$dispatcher)
	{
		$this->_dispatcher = $dispatcher;
		$this->_pluginsInfo = array();
		$this->_loadedPlugins = array();
		$this->_files = array();
		
		$dispatcher->addPage('plugins/?', array($this, 'pluginList'));
		$dispatcher->addPage('plugins/load/(.+)', array($this, 'loadPlugin'));
		$dispatcher->addPage('plugins/unload/(.+)', array($this, 'unloadPlugin'));
	}
	
	public function loadPlugin($data)
	{
		$this->_dispatcher->disableDesign(); //We disable the design, because of AJAX
		$this->_dispatcher->disableCache(); //Disabling cache, to be sure to re-execute the request the next time
		
		//Saving the loaded plugins before loading, so we can get the loaded plugins during operation
		$pluginsBefore = Leelabot::$instance->plugins->getLoadedPlugins();
		
		//Loading the plugin, and processing the result
		if(Leelabot::$instance->plugins->loadPlugin($data['matches'][1], TRUE))
			$ret = 'success:'.join('/',array_diff(Leelabot::$instance->plugins->getLoadedPlugins(), $pluginsBefore));
		else
			$ret = 'error:'.Leelabot::lastError();
		
		return $ret;
	}
	
	public function unloadPlugin($data)
	{
		$this->_dispatcher->disableDesign(); //We disable the design, because of AJAX
		$this->_dispatcher->disableCache(); //Disabling cache, to be sure to re-execute the request the next time
		
		//Returning the the bot's root, since we're in the webadmin root
		$cwd = getcwd();
		chdir(Leelabot::$instance->root);
		
		//Saving the loaded plugins before loading, so we can get the loaded plugins during operation
		$pluginsBefore = Leelabot::$instance->plugins->getLoadedPlugins();
		
		//Loading the plugin, and processing the result
		if(Leelabot::$instance->plugins->unloadPlugin($data['matches'][1]))
			$ret = 'success:'.join('/',array_diff($pluginsBefore, Leelabot::$instance->plugins->getLoadedPlugins()));
		else
			$ret = 'error:'.Leelabot::lastError();
		
		chdir($cwd); //Returning to the webadmin root.
		
		return $ret;
	}
	
	public function pluginList($data)
	{
		//Computing server list for each plugin
		$servers = array();
		foreach(Leelabot::$instance->plugins->getInfoFromFiles() as $plugin)
			$servers[$plugin['name']] = array();
		
		foreach(ServerList::getList() as $servername)
		{
			$server = ServerList::getServer($servername);
			$list = $server->getPlugins();
			foreach($list as $p)
			{
				if(!isset($servers[$p]))
					$servers[$p] = array();
				$servers[$p][] = $servername;
			}
		}
		
		foreach($servers as &$s)
		{
			if(empty($s))
				$s = 'Not used';
			else
				$s = join(', ', $s);
		}
		
		$this->_dispatcher->parser->assign('plugins', Leelabot::$instance->plugins->getInfoFromFiles());
		$this->_dispatcher->parser->assign('loaded', Leelabot::$instance->plugins->getLoadedPlugins());
		$this->_dispatcher->parser->assign('servers', $servers);
		
		return $this->_dispatcher->parser->draw('plugins');
	}
	
	private function updatePluginsList($files)
	{
		$this->_files = array();
		$this->_pluginsInfo = array();
		
		$this->_pluginsInfo = Leelabot::$instance->plugins->getInfoFromFiles($files);
		
		$this->_files = $files;
	}
}
