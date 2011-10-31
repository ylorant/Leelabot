<?php

class NginyUS_SiteManager extends NginyUS_SystemPages
{
	private $siteList;
	private $defaultSite;
	public $main;
	
	public function __construct(&$main)
	{
		$this->main = $main;
		$this->defaultSite = new NginyUS_Site($this);
		$this->siteList['default'] = &$this->defaultSite;
	}
	
	public function newSite($name)
	{
		if(isset($this->siteList[$name]))
			return FALSE;
		
		$this->siteList[$name] = clone $this->defaultSite;
		$this->siteList[$name]->setName($name);
	}
	
	public function getSite($name)
	{
		if(isset($this->siteList[$name]))
			return $this->siteList[$name];
		else
			return FALSE;
	}
	
	public function loadConfig($siteName, $siteConfig)
	{
		NginyUS::message('Parsing config for $0...', array($siteName));
		//$instance = &$this->siteList[$siteName];
		
		if(isset($siteConfig['Alias']))
			$this->siteList[$siteName]->setAliases(array_map('trim',explode(',', $siteConfig['Alias'])));
		if(isset($siteConfig['DocumentRoot']))
			$this->siteList[$siteName]->setDocumentRoot($siteConfig['DocumentRoot']);
		if(isset($siteConfig['ProcessFiles']))
			$this->siteList[$siteName]->loadFiles(array_map('trim',explode(',', $siteConfig['ProcessFiles'])));
		if(isset($siteConfig['SiteRoot']))
			$this->siteList[$siteName]->setSiteRoot($siteConfig['SiteRoot']);
		
		if(isset($siteConfig['ShowIndexes']))
		{
			if(NginyUS::parseBool($siteConfig['ShowIndexes']))
				$this->siteList[$siteName]->enableIndexes();
			else
				$this->siteList[$siteName]->disableIndexes();
		}
	}
	
	public function initSites()
	{
		if(count($this->siteList))
		{
			foreach($this->siteList as $site)
				$site->initClasses($this->main);
		}	
	}
	
	public function pageCall($id, $data)
	{
		if(!isset($data['host']))
			$data['host'] = $this->defaultSite->getSiteRoot();
		
		//Test for default site first
		/*if($this->defaultSite->getSiteRoot() == $data['host'] || in_array($data['host'], $this->defaultSite->getAliases()))
			$site = $this->defaultSite;*/
		
		$exec = FALSE;
		$found = FALSE;
		
		NginyUS::message('Checking matches in sites...', array(), E_DEBUG);
		
		//Run through all sites to get the good one
		if(count($this->siteList))
		{
			foreach($this->siteList as $name => $site)
			{
				//Skip default site
				if($name == 'default')
					continue;
		
				$siteHost = $site->getSiteRoot();
				$length = strlen($siteHost);
				$host = substr($data['url'], 0, $length);
				
				if($siteHost == $host)
				{
					$exec = &$site;
					$data['page'] = substr($data['url'], $length);
					if($data['page'] == '')
							$data['page'] = '/';
					break;
				}
				
				foreach($site->getAliases() as $alias)
				{
					
					$length = strlen($alias);
					$host = substr($data['url'], 0, $length);
					if($alias == $host)
					{
						$exec = &$site;
						$data['page'] = substr($data['url'], $length);
						if($data['page'] == '')
							$data['page'] = '/';
						break 2;
					}
				}
			}
		}
		
		if($exec)
		{
			NginyUS::message('Found match in $0...', array($name), E_DEBUG);
			$localDir = getcwd();
			chdir($exec->getDocumentRoot());
			$found = $exec->callPage($id, $data);
			chdir($localDir);
		}
		
		//If not found, send 404
		if(!$found)
		{
			$errorFound = FALSE;
			if($exec)
				$errorFound = $exec->callErrorPage(404, $id, $data);
			
			if(!$errorFound)
				$this->error404($id, $data);
		}
	}
}

class NginyUS_Site extends NginyUS_Events
{
	private $documentRoot = './';
	private $siteRoot;
	private $aliases;
	private $processFile;
	private $showIndexes;
	private $name;
	private $siteManager;
	private $fileinfo;
	
	public function __construct(&$siteManager)
	{
		$this->siteManager = $siteManager;
		$this->fileinfo = finfo_open(FILEINFO_MIME_TYPE);
	}
	
	public function setName($name)
	{
		$this->name = $name;
	}
	
	public function setAliases($list)
	{
		NginyUS::message('[$0] New aliases : $1', array($this->name, join(', ',$list)), E_DEBUG);
		$this->aliases = $list;
	}
	
	public function getAliases()
	{
		return !empty($this->aliases) ? $this->aliases :array();
	}
	
	public function setDocumentRoot($root)
	{
		NginyUS::message('[$0] DocumentRoot is now $1', array($this->name, $root), E_DEBUG);
		if($root[strlen($root)-1] == '/')
			$root = substr($root, 0, strlen($root)-1);
		$this->documentRoot = $root;
	}
	
	public function getDocumentRoot()
	{
		return $this->documentRoot;
	}
	
	public function loadFiles($fileList)
	{
		NginyUS::message('[$0] Loading files : $1', array($this->name, join(', ',$fileList)), E_DEBUG);
		foreach($fileList as $file)
			include($this->documentRoot.'/'.$file);
	}
	
	public function setSiteRoot($root)
	{
		NginyUS::message('[$0] New SiteRoot $1', array($this->name, $root), E_DEBUG);
		$this->siteRoot = $root;
	}
	
	public function getSiteRoot()
	{
		return $this->siteRoot;
	}
	
	public function enableIndexes()
	{
		$this->showIndexes = TRUE;
	}
	
	public function disableIndexes()
	{
		$this->showIndexes = FALSE;
	}
	
	public function callFilePage($id, $data)
	{
		if(!is_file($data['page']))
			return FALSE;
		
		$file = file_get_contents($data['page']);
		$fileinfo = finfo_file($this->fileinfo, $data['page']);
		$this->siteManager->main->BufferSetReplyCode($id, 200);
		$this->siteManager->main->addAutomaticHeaders($id);
		$this->siteManager->main->BufferAddHeader($id, 'Content-type', $fileinfo);
		$this->siteManager->main->BufferAppendData($id, $file);
		$this->siteManager->main->sendBuffer($id);
	}
}
