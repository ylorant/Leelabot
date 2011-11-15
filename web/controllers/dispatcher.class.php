<?php

include('status.class.php');
include('rain.tpl.class.php');

class LeelabotAdmin
{
	private $_site;
	private $_pages = array();
	private $_corePages;
	
	public $parser;
	
	public function __construct(&$site, &$main)
	{
		$this->_site = $site;
		$this->_main = $main;
		$this->_corePages = array();
		
		//Initializing template parser
		$this->parser = new RainTPL();
		RainTPL::$cache_dir = 'views/tmp/';
		RainTPL::$tpl_ext = 'tpl';
		
		//We set the webadmin class into the OuterAPI class, for adding properly methods
		Leelabot::$instance->outerAPI->setWAObject($this);
		
		$site->addFilePage('/favicon.ico', 'static/images/favicon.ico');
		$site->addFilePage('/style/(.+)', 'static/style/$1');
		$site->addFilePage('/jquery', 'static/js/jquery.js');
		$site->addFilePage('/images/(.+)', 'static/images/$1');
		$site->addPage('/(.*)', $this, 'process');
		$site->addErrorPage(404, $this, 'error404');
		
		//Core pages loading
		$this->_corePages['status'] = new LeelabotAdminStatus($this);
	}
	
	public function error404($id, $data)
	{
		RainTPL::$tpl_dir = 'views/'.Leelabot::$instance->intl->getLocale().'/';
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
		return $this->addPage('plugins/'.$page, $callback);
	}
	
	public function addPage($page, $callback)
	{
		if(isset($this->_pages[$page]))
			return FALSE;
		
		$this->_pages[$page] = $callback;
		
		return TRUE;
	}
	
	public function process($id, $data)
	{
		if(isset($this->_pages[strtolower($data['matches'][1])]))
		{
			$page = &$this->_pages[strtolower($data['matches'][1])];
			
			RainTPL::$tpl_dir = 'views/'.Leelabot::$instance->intl->getLocale().'/';
			$this->_main->BufferSetReplyCode($id, 200);
			$content = $this->addDesign(strtolower($data['matches'][1]), $page[0]->$page[1]($data));
			$this->_main->BufferAppendData($id, $content);
			$this->_main->sendBuffer($id);
		}
		else
			$this->_site->callErrorPage(404, $id, $data);
	}
	
	public function addDesign($page, $content)
	{
		$page = explode('/', $page, 3);
		
		$this->parser->assign('category', $page[0]);
		
		if(isset($page[1]))
			$this->parser->assign('subcategory', $page[1]);
		
		$data = $this->parser->draw('design/top');
		$data .= $content;
		$data .= $this->parser->draw('design/bottom');
		
		return $data;
	}
}

$this->addClasses(array('LeelabotAdmin'));
