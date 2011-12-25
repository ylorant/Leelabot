<?php

class NginyUS_Framework
{
	protected $buffers = array();
	protected $replyStr = array(
		'200' => 'OK',
		'401' => 'Authorization Required',
		'403' => 'Forbidden',
		'404' => 'File not found');
	
	//Append data to a buffer
	public function BufferAppendData($id, $data)
	{
		if(!isset($this->buffers[$id]))
			$this->buffers[$id] = array('data' => '', 'headers' => array(), 'code' => 200);
		$this->buffers[$id]['data'] .= $data;
	}
	
	//Adds/Replace metadata entry of a buffer
	public function BufferAddHeader($id, $title, $content)
	{
		if(!isset($this->buffers[$id]))
			$this->buffers[$id] = array('data' => '', 'headers' => array(), 'code' => 200);
		
		$this->buffers[$id]['headers'][$title] = $content;
	}
	
	//Set the reply type for a buffer
	public function BufferSetReplyCode($id, $code)
	{
		if(!isset($this->buffers[$id]))
			$this->buffers[$id] = array('data' => '', 'headers' => array(), 'code' => 200);
		
		$this->buffers[$id]['code'] = $code;
	}
	
	//Return TRUE if the specified header has already been defined
	public function BufferHeaderExists($id, $header)
	{
		return isset($this->buffers[$id]['headers'][$header]);
	}
	
	public function addAutomaticHeaders($id)
	{
		$this->BufferAddHeader($id, 'Date', date('r'));
		$this->BufferAddHeader($id, 'Server',NginyUS::NAME.'/'.NginyUS::VERSION);
		$this->BufferAddHeader($id, 'Connection', 'keep-alive');
		$this->BufferAddHeader($id, 'Content-Length', strlen($this->buffers[$id]['data']));
		$this->BufferAddHeader($id, 'Expires', date('r', strtotime('+5 mins', time())));
		$this->BufferAddHeader($id, 'Last-Modified', date('r'));
		
		if(!$this->BufferHeaderExists($id, 'Content-type'))
			$this->BufferAddHeader($id, 'Content-type', 'text/html');
	}
	
	//Sends raw data to the buffer
	public function BufferSetRawData($id, $data)
	{
		if(!isset($this->buffers[$id]))
			$this->buffers[$id] = array('data' => '', 'headers' => array(), 'code' => 200);
		
		$this->buffers[$id]['raw'] = $data;
	}
	
	//Send the content of a file (as-is) to the client
	public function sendFileContents($id, $file)
	{
		//Getting mimetype for the browser
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $file);
		
		$this->BufferSetReplyCode($id, 200);
		$this->addAutomaticHeaders($id);
		$this->BufferAddHeader($id, 'Content-type', $mime);
		
		switch(pathinfo($file, PATHINFO_EXTENSION))
		{
			case 'css':
				$this->BufferAddHeader($id, 'Content-type', 'text/css');
				break;
			case 'js':
				$this->BufferAddHeader($id, 'Content-type', 'text/javascript');
				break;
		}
		
		$this->BufferAppendData($id, file_get_contents($file));
		$this->sendBuffer($id);
	}
	
	//Sends a buffer to the related client
	public function sendBuffer($id)
	{
		if(!isset($this->buffers[$id]))
			return FALSE;
		
		if(!isset($this->buffers[$id]['raw']))
		{		
			$this->addAutomaticHeaders($id);
			
			NginyUS::message('Reply code : $0', array($this->buffers[$id]['code']), E_DEBUG);
			
			$text = 'HTTP/1.1 '.$this->buffers[$id]['code']." ".$this->replyStr[$this->buffers[$id]['code']]."\n";
			foreach($this->buffers[$id]['headers'] as $ref => $header)
				$text .= $ref.': '.$header."\r\n";
			
			$text .= "\r\n".$this->buffers[$id]['data'];
		}
		else
			$text = $this->buffers[$id]['raw'];
		
		$this->sendData($id, $text);
		unset($this->buffers[$id]);
		//$this->closeConnection($id);
	}
	
	//Authenticate client
	public function authenticate($id, $data, $callback, $type = NginyUS::AUTH_BASIC)
	{
		if($type == NginyUS::AUTH_BASIC)
			return $this->authBasic($id, $data, $callback);
		
	}
	
	public function authBasic($id, $data, $callback)
	{
		if(isset($data['Authorization'])) //Checking authorization if there is one
		{
			$secret = explode(' ', $data['Authorization']);
			$couple = explode(':', base64_decode($secret[1]));
			if(call_user_func($callback, $couple[0], $couple[1]))
				return TRUE;
		}
		
		$this->BufferSetReplyCode($id, 401);
		$this->BufferAddHeader($id, 'WWW-Authenticate', 'Basic realm="'.$data['host'].'"');
		$this->sendBuffer($id);
		
		return FALSE;
	}
	
	public function downloadFile($id, $file)
	{
		if(is_file($file))
			return FALSE;
		
		$this->BufferSetReplyCode($id, 200);
		$this->BufferAddHeader($id, 'Content-Description', 'File Transfer');
		$this->BufferAddHeader($id, 'Content-Type', 'application/octet-stream');
		$this->BufferAddHeader($id, 'Content-Disposition', 'attachment; filename='.basename($file));
		$this->BufferAddHeader($id, 'Content-Transfer-Encoding', 'binary');
		$this->BufferAddHeader($id, 'Expires', '0');
		$this->BufferAddHeader($id, 'Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
		$this->BufferAddHeader($id, 'Pragma: public');
		$this->BufferAddHeader($id, 'Content-Length', filesize($file));
		ob_clean();
		ob_start();
		readfile($file);
		$text = ob_get_flush();
		$this->BufferAppendData($id, $text);
		$this->sendBuffer($id);
	}
}
