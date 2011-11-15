<?php

class NginyUS_SystemPages
{
	public function error404($id, $data)
	{
		$this->main->BufferSetReplyCode($id, 404);
		$this->main->BufferAddHeader($id,'Connection', 'close');
		$this->main->BufferAppendData($id, '
			<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN"> 
			<html><head> 
			<title>404 Not Found</title> 
			</head><body> 
			<h1>Not Found</h1> 
			<p>The requested URL '.$data['page'].' was not found on this server.</p> 
			<hr> 
			<address>'.NginyUS::NAME.'/'.NginyUS::VERSION.' ('.$this->main->serverInfo['os'].') Server at '.$data['host'].' Port '.$data['port'].'</address> 
			</body></html>');
		$this->main->sendBuffer($id);
	}
}
