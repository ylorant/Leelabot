<?php

class LeelabotAdminStatus
{
	private $_dispatcher;
	private $_files;
	private $_pluginsInfo;
	private $_loadedPlugins;
	
	public function __construct(&$dispatcher)
	{
		$this->_dispatcher = $dispatcher;
		$this->_pluginsInfo = array();
		$this->_loadedPlugins = array();
		$this->_files = array();
		
		$dispatcher->addPage('plugins/', array($this, 'pluginList'));
	}
	
	public function pluginList($data)
	{
		$files = scandir('../plugins');
		if($files != $this->_files)
			$this->updatePluginsList($files);
		
		//Computing server list for each plugin
		$servers = array();
		foreach($this->_pluginsInfo as $plugin)
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
		
		$this->_dispatcher->parser->assign('plugins', $this->_pluginsInfo);
		$this->_dispatcher->parser->assign('loaded', Leelabot::$instance->plugins->getLoadedPlugins());
		$this->_dispatcher->parser->assign('servers', $servers);
		
		return $this->_dispatcher->parser->draw('plugins');
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
			
			$this->_pluginsInfo[$f] = array('version' => '', 'file' => $f, 'author' => Leelabot::$instance->intl->translate('Anonymous'), 'description' => '', 'name' => $name);
			$content = file_get_contents('../plugins/'.$f);
			
			if(preg_match('#\\\\version (.+)\r?\n#isU', $content, $version))
				$this->_pluginsInfo[$f]['version'] = $version[1];
			if(preg_match('#\\\\file (.+)\r?\n#isU', $content, $file))
				$this->_pluginsInfo[$f]['file'] = $file[1];
			if(preg_match('#\\\\author (.+)\r?\n#isU', $content, $author))
				$this->_pluginsInfo[$f]['author'] = $author[1];
			if(preg_match('#\\\\brief (.+)\r?\n#isU', $content, $description))
				$this->_pluginsInfo[$f]['description'] = $description[1];
		}
		
		$this->_files = $files;
	}
}
