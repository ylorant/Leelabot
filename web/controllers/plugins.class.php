<?php

class LeelabotAdminPlugins
{
	private $_dispatcher;
	private $_files;
	private $_loadedPlugins;
	public $pluginsInfo;
	
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
		
		//Returning the the bot's root, since we're in the webadmin root
		$cwd = getcwd();
		chdir(Leelabot::$instance->root);
		
		//Saving the loaded plugins before loading, so we can get the loaded plugins during operation
		$pluginsBefore = Leelabot::$instance->plugins->getLoadedPlugins();
		
		//Loading the plugin, and processing the result
		if(Leelabot::$instance->plugins->loadPlugin($data['matches'][1], TRUE))
			$ret = 'success:'.join('/',array_diff(Leelabot::$instance->plugins->getLoadedPlugins(), $pluginsBefore));
		else
			$ret = 'error:'.Leelabot::lastError();
		
		chdir($cwd); //Returning to the webadmin root.
		
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
		$this->checkPluginsUpdate();
		
		//Computing server list for each plugin
		$servers = array();
		foreach($this->pluginsInfo as $plugin)
			$servers[$plugin['name']] = array();
		
		foreach(Leelabot::$instance->servers as $servername => $server)
		{
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
		
		$this->_dispatcher->parser->assign('plugins', $this->pluginsInfo);
		$this->_dispatcher->parser->assign('loaded', Leelabot::$instance->plugins->getLoadedPlugins());
		$this->_dispatcher->parser->assign('servers', $servers);
		
		return $this->_dispatcher->parser->draw('plugins');
	}
	
	public function checkPluginsUpdate()
	{
		$files = scandir('../plugins');
		if($files != $this->_files)
			$this->updatePluginsList($files);
	}
	
	private function updatePluginsList($files)
	{
		$this->_files = array();
		$this->_pluginsInfo = array();
		
		//Reading the plugins' directory
		foreach($files as $f)
		{
			if(pathinfo($f, PATHINFO_EXTENSION) != 'php')
				continue;
				
			$name = pathinfo($f, PATHINFO_FILENAME);
			
			$this->pluginsInfo[$f] = array('version' => '', 'file' => $f, 'author' => Leelabot::$instance->intl->translate('Anonymous'), 'description' => '', 'name' => $name, 'dname' => ucfirst($name), 'dependencies' => Leelabot::$instance->intl->translate('None'));
			$content = file_get_contents('../plugins/'.$f);
			
			if(preg_match('#\\\\version (.+)\r?\n#isU', $content, $version))
				$this->pluginsInfo[$f]['version'] = $version[1];
			if(preg_match('#\\\\file (.+)\r?\n#isU', $content, $file))
				$this->pluginsInfo[$f]['file'] = $file[1];
			if(preg_match('#\\\\author (.+)\r?\n#isU', $content, $author))
				$this->pluginsInfo[$f]['author'] = $author[1];
			if(preg_match('#\\\\brief (.+)\r?\n#isU', $content, $description))
				$this->pluginsInfo[$f]['description'] = $description[1];
			if(preg_match('#\\\\depends (.+)\r?\n#isU', $content, $dependencies))
				$this->pluginsInfo[$f]['dependencies'] = $dependencies[1];
			
		}
		
		$this->_files = $files;
	}
}
