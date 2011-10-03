<?php

class NginyUS_Framework
{
	protected $buffers = array();
	
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
	
	private function addAutomaticHeaders($id)
	{
		$this->BufferAddHeader($id, 'Date', date('r'));
		$this->BufferAddHeader($id, 'Server',NginyUS::NAME.'/'.NginyUS::VERSION);
		$this->BufferAddHeader($id, 'Connection', 'close');
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
		
		$this->BufferAddHeader($id, 'Content-type', $mime);
		$this->BufferSetReplyCode($id, 200);
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
			
			$text = 'HTTP/1.1 '.$this->buffers[$id]['code'];
			foreach($this->buffers[$id]['headers'] as $ref => $header)
				$text .= $ref.': '.$header."\r\n";
			
			$text .= "\r\n".$this->buffers[$id]['data'];
		}
		else
			$text = $this->buffers[$id]['raw'];
		
		$this->sendData($id, $text);
		$this->closeConnection($id);
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
