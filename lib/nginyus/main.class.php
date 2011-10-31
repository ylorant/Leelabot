<?php

class NginyUS extends NginyUS_Framework
{
	private $socket;
	private $siteManager;
	private $addr;
	private $port;
	private $clients = array();
	private $lastClientID = 0;
	private $freedID = array();
	public $serverInfo = array();
	
	public static $continue;
	
	const NAME = NginyUS;
	const VERSION = "0.0.1";
	
	public function __construct()
	{
		$this->siteManager = new NginyUS_SiteManager($this);
		$this->socket = socket_create(AF_INET,SOCK_STREAM, SOL_TCP);
		
		//Applying default listen ports
		$this->addr = '127.0.0.1';
		$this->port = 80;
		
		//Gathering server system info
		$uname = php_uname('s');
		
		if($uname == 'Linux')
		{
			$this->serverInfo['os'] = explode(':', shell_exec('lsb_release -i'), 2);
			$this->serverInfo['os'] = trim($this->serverInfo['os'][1]);
		}
		else
			$this->serverInfo['os'] = $uname;
	}
	
	//Parse string data to return boolean
	public static function parseBool($var)
	{
		if(in_array(strtolower($var), array('1', 'on', 'true', 'yes')))
			return TRUE;
		else
			return FALSE;
	}
	
	//Useful for triggering errors
	public static function message($text, $params = array(), $errorType = E_NOTICE)
	{
		Leelabot::message('NginyUS : '.$text, $params, $errorType);
		if($errorType == E_ERROR)
			exit();
	}
	
	//Sets IP and port
	public function setAddress($addr, $port)
	{
		if(filter_var($addr, FILTER_VALIDATE_IP)) //Match IP
			$this->addr = $addr;
		else
			NgniyUS::message('Address not valid : $0', array($addr), E_WARNING);
		
		if(in_array($port, range(0, 65535))) //Match port
			$this->port = $port;
		else
			NgniyUS::message('Port not valid : $0', array($addr), E_WARNING);
	}
	
	//Requires site manager object
	public function manageSites()
	{
		return $this->siteManager;
	}
	
	//Main loop
	public function connect()
	{
		$this->siteManager->initSites();
		
		NginyUS::message('Binding socket...');
		while(!@socket_bind($this->socket, $this->addr, $this->port))
		{
			NginyUS::message('Address $0 not available, trying $1', array($this->addr.':'.$this->port, $this->addr.':'.($this->port+1)), E_WARNING);
			$this->port++;
		}
		socket_set_nonblock($this->socket);
		NginyUS::message('Socket successfully bound to '.$this->addr.':'.$this->port);
		
		//Setting serverInfo about address and port
		$this->serverInfo['port'] = $this->port;
		socket_getsockname($this->socket, $this->serverInfo['addr']);
		
		NginyUS::message('Listening now...');
	}
	
	public function process()
	{
		if(!socket_listen($this->socket))
			NginyUS::error('Can\'t listen to socket : $0', array(socket_strerror(socket_last_error())), E_ERROR);
		
		//Some client want to connect
		if(($tempSocket = @socket_accept($this->socket)) !== FALSE)
		{
			NginyUS::message('Hey, someone is connecting !', array(), E_DEBUG);
			if(isset($this->freedID[0]))
			{
				$id = $this->freedID[0];
				unset($this->freedID[0]);
				sort($this->freedID);
			}
			else
				$id = count($this->clients);
			$this->clients[$id] = $tempSocket;
		}
		
		//Now we read data from all clients... Only if there is someone to read from
		if(count($this->clients))
		{
			$read = array();
			$write = NULL;
			$exception = NULL;
			foreach($this->clients as $id => $current)
				$read[$id] = $current;
			
			$modified = @socket_select($read, $write, $exception, 0);
			
			if($modified === FALSE) //Errors, yipee...
				NginyUS::error('Can\'t select active clients : $0', array(socket_strerror(socket_last_error())), E_WARNING);
			elseif($modified > 0) //At least one socket sended data
			{
				foreach($read as $id => $client)
				{
					$buffer = '';
					$buffer = socket_read($this->clients[$id], 1024);
					$this->parseQuery($id, $buffer);
				}
			}
		}
	}
	
	public function stop()
	{
		NginyUS::message('Stopping server...');
		
		NginyUS::message('Closing remaining client sockets...', array(), E_DEBUG);
		foreach($this->clients as $id => $socket)
		{
			socket_close($this->clients[$id]);
			unset($this->clients[$id]);
			unset($this->buffers[$id]);
		}
		
		NginyUS::message('Closing main socket...', array(), E_DEBUG);
		socket_close($this->socket);
	}
	
	public function closeConnection($id)
	{
		if(isset($this->clients[$id]))
		{
			$return = socket_close($this->clients[$id]);
			if($return === FALSE)
				NginyUS::error('Cannot close the socket : '.socket_strerror(socket_last_error()), E_WARNING);
			unset($this->clients[$id]);
			unset($this->buffers[$id]);
		}
	}
	
	public function sendData($client, $data)
	{
		socket_write($this->clients[$client], $data);
	}
	
	//Parses HTTP query
	public function parseQuery($id, $query)
	{
		if(!$query)
			return FALSE;
		$data = array();
		socket_getpeername($this->clients[$id],$data['addr']);
		$data['raw'] = $query;
		$query = explode("\r\n\r\n", $query);
		$metadata = explode("\r\n", $query[0]);
		$_POST = array();
		$data['query'] = 'GET';
		$_SERVER = array();
		foreach($metadata as $row)
		{
			$row = explode(' ', $row, 2);
			switch($row[0])
			{
				case 'POST':
					parse_str($query[1], $_POST);
					NginyUS::message("Received POST data : $0", array($this->dumpArray($_POST)), E_DEBUG);
					$data['rawPOST'] = $query[1];
					$_SERVER['REQUEST_METHOD'] = $data['query'] = 'POST';
				case 'GET': //It's a GET request (main parameter)
					$data['page'] = explode(' ', $row[1]);
					$data['page'] = $data['page'][0];
					NginyUS::message('Requested page : '.$data['page']);
					break;
				case 'Host:':
					$host = explode(':',$row[1], 2);
					$data['host'] = $host[0];
					$data['port'] = isset($host[1]) ? $host[1] : NULL;
					break;
				case 'Connection:':
					if($row[1] == 'keep-alive')
						$data['keep-alive'] = TRUE;
					break;
				case 'User-Agent:':
					array_shift($row);
					$data['user-agent'] = join(' ', $row);
					break;
				default:
					$data[substr($row[0], 0, -1)] = $row[1];
					break;
			}
			if(isset($row[1]))
				$_SERVER[substr($row[0],0, -1)] = $row[1];
		}
		$data['url'] = $data['host'].$data['page'];
		$this->siteManager->pageCall($id, $data);
	}
	
	public function dumpArray($array)
	{
		if(!is_array($array))
			$array = array(gettype($array) => $array);
		
		$return = array();
		
		foreach($array as $id => $el)
		{
			if(is_array($el))
				$return[] = $id.'=Array';
			elseif(is_object($el))
				$return[] = $id.'='.get_class($el).' object';
			elseif(is_string($el))
				$return[] = $id.'="'.$el.'"';
			elseif(is_bool($el))
				$return[] = $id.'='.($el ? 'TRUE' : 'FALSE');
			else
				$return[] = $id.'='.(is_null($el) ? 'NULL' : $el);
		}
		
		return join(', ', $return);
	}
	
}
