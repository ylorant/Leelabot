<?php

include('vemplator.php');

class Local
{
	private $site;
	private $main;
	
	public function __construct(&$site, &$main)
	{
		$this->site = $site;
		$this->main = $main;
		
		$site->addPage('/', 'Local', 'mainPage');
		$site->addPage('/server-info', 'Local', 'serverInfo');
		$site->addPage('/server-info/submit', 'Local', 'serverInfoSubmit');
		$site->addPage('/favicon.ico', 'Local', 'favicon');
		$site->addPage('/static/(.*)', 'Local', 'staticCall');
	}
	
	public function staticCall($id, $data)
	{
		$this->main->sendFileContents($id, $data['matches'][1]);
	}
	
	public function mainPage($id, $data)
	{
		$this->main->BufferSetReplyCode($id, 200);
		$this->main->BufferAppendData($id, 'Hello, world ! <a href="server-info">Server Info</a>');
		$this->main->sendBuffer($id);
	}
	
	public function serverInfo($id, $data)
	{
		$template = new vemplator();
		$template->assign('server', new Storage(array_merge($this->main->serverInfo, array('name' => NginyUS::NAME, 'version' => NginyUS::VERSION))));
		$template->assign('req', new Storage($data));
		$template->assign('code', new Storage(array('class' => __CLASS__, 'function' => __FUNCTION__, 'file' => __FILE__)));
		$page = $template->output('server-info.html');
		$this->main->BufferSetReplyCode($id, 200);
		$this->main->BufferAppendData($id, $page);
		$this->main->sendBuffer($id);
	}
	
	public function serverInfoSubmit($id, $data)
	{
		$this->main->BufferSetReplyCode($id, 200);
		$this->main->BufferAppendData($id, 'Posted ! Value : '.$_POST['field']);
		$this->main->sendBuffer($id);
	}
	
	public function favicon($id, $data)
	{
		$this->main->sendFileContents($id,'favicon.png');
	}
}

$this->addClasses(array('Local'));
