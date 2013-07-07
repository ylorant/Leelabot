<?php

include('plugins.class.php');
include('rain.tpl.class.php');

class LeelabotAdmin
{
	private $_site;
	private $_pages = array();
	private $_corePages;
	private $_design = TRUE;
	private $_noCache = FALSE;
	private $_main;
	private $_authentication = FALSE;
	private $_authfile;
	
	public $parser;
	
	public function __construct(&$site, &$main)
	{
		$this->_site = $site;
		$this->_main = $main;
		$this->_corePages = array();
		
		//Initializing template parser
		RainTPL::$cache_dir = 'web/views/tmp/';
		RainTPL::$tpl_ext = 'tpl';
		
		//We set the webadmin class into the OuterAPI class, for adding properly methods
		Leelabot::$instance->outerAPI->setWAObject($this);
		
		$site->addFilePage('/favicon.ico', 'web/static/images/favicon.ico');
		$site->addFilePage('/style/(.+)', 'web/static/style/$1');
		$site->addFilePage('/js/(.+)', 'web/static/js/$1');
		$site->addFilePage('/images/(.+)', 'web/static/images/$1');
		$site->addPage('/(.*)', $this, 'process');
		$site->addErrorPage(404, $this, 'error404');
		
		//Core pages loading
		$this->_corePages['plugins'] = new LeelabotAdminPlugins($this);
	}
	
	public function error404($id, $data)
	{
		RainTPL::$tpl_dir = 'web/views/'.Leelabot::$instance->intl->getLocale().'/';
		$this->parser = new RainTPL();
		
		$this->_main->BufferSetReplyCode($id, 404);
		if(isset($data['matches']))
			$content = $this->addDesign(strtolower($data['matches'][1]), '<h1>404 Error</h1><p>Sorry, the page you requested cannot be found.</p>');
		else
			$content = $this->addDesign(strtolower($data['page']), '<h1>404 Error</h1><p>Sorry, the page you requested cannot be found.</p>');
		$this->_main->BufferAppendData($id, $content);
		$this->_main->sendBuffer($id);
	}
	
	public function addPluginPage($page, $callback)
	{
		return $this->addPage('plugin/'.$page, $callback);
	}
	
	public function addPage($page, $callback)
	{
		if(isset($this->_pages[$page]))
			return FALSE;
		
		$this->_pages[$page] = $callback;
		
		return TRUE;
	}
	
	public function disableDesign()
	{
		$this->_design = FALSE;
	}
	
	public function disableCache()
	{
		$this->_noCache = TRUE;
	}
	
	public function setAuthentication($a, $f)
	{
		$this->_authentication = $a;
		$this->_authfile = $f;
	}
	
	public function userCheck($user, $passwd)
	{
		//~ //Returning the the bot's root, since we're in the webadmin root
		//~ $cwd = getcwd();
		//~ chdir(Leelabot::$instance->root);
		//~ 
		//Parsing password file
		$userFile = parse_ini_file($this->_authfile);
		
		//~ chdir($cwd); //Returning to the webadmin root.
		
		if(!isset($userFile[$user]) || $userFile[$user] != $passwd)
			return FALSE;
		
		return TRUE;
	}
	
	public function process($id, $data)
	{
		//Before processing anything, we check if the user has been authenticated
		if($this->_authentication)
		{
			//If the user has not been authenticated, we return a blank page (the request has been return by authenticate())
			if(!$this->_main->authenticate($id, $data, array($this, 'userCheck')))
				return TRUE;
		}
		
		foreach($this->_pages as $regex => $call)
		{
			if(preg_match('#^'.$regex.'$#', strtolower($data['matches'][1]), $matches))
			{
				//Setting initial environment for page process
				$page = strtolower($data['matches'][1]);
				$data['matches'] = $matches;
				$this->_design = TRUE;
				$this->_noCache = FALSE;
				$this->parser = new RainTPL();
				RainTPL::$tpl_dir = 'web/views/'.Leelabot::$instance->intl->getLocale().'/';
				
				
				
				//Calling the page controller
				$fctrep = $call[0]->$call[1]($data);
				
				//Adding the design
				if($this->_design)
					$content = $this->addDesign($page, $fctrep);
				else
					$content = $fctrep;
				
				//Sending the final page
				$this->_main->BufferSetReplyCode($id, 200);
				
				if($this->_noCache)
					$this->_main->BufferAddHeader($id, 'Cache-Control', 'no-store, no-cache, must-revalidate');
				
				$this->_main->BufferAppendData($id, $content);
				$this->_main->sendBuffer($id);
				
				return TRUE;
			}
		}
		
		$this->_site->callErrorPage(404, $id, $data);
	}
	
	public function addDesign($page, $content)
	{
		$page = explode('/', $page, 3);
		
		$this->parser->assign('plugins', Leelabot::$instance->plugins->getInfoFromFiles());
		$this->parser->assign('loaded', Leelabot::$instance->plugins->getLoadedPlugins());
		$this->parser->assign('category', $page[0]);
		if(isset($page[1]))
			$this->parser->assign('subcategory', $page[1]);
		else
			$this->parser->assign('subcategory', '');
		
		$data = $this->parser->draw('design/top');
		$data .= $content;
		$data .= $this->parser->draw('design/bottom');
		
		return $data;
	}
}

$this->addClasses(array('LeelabotAdmin'));
