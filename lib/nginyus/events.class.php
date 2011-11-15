<?php

//Classe de gestion
class NginyUS_Events
{
	private $_fcts = array();
	private $_fpages = array();
	private $_errorFcts = array();
	public $classes = array();
	private $classList = array();
	
	//Ajoute un évènement
	public function addPage($path, &$class, $fct)
	{
		$this->_fcts[$path] = array($class,$fct);
	}
	
	//Ajoute une page directe sur un fichier
	public function addFilePage($path, $file)
	{
		$this->_fpages[$path] = $file;
	}
	
	public function rmPage($path)
	{
		unset($this->_fcts[$path]);
	}
	
	public function initClasses($main)
	{
		foreach($this->classList as $element)
		{
			NginyUS::message('Loading '.$element.'...');
			$this->classes[$element] = new $element($this, $main);
		}
	}
	
	public function destroyClasses($list)
	{
		foreach($this->classList as $element)
			unset($this->classes[$element]);
	}
	
	public function callPage($id, $data)
	{
		if(!isset($data['page']))
			return FALSE;
		
		$found = FALSE;
		$page = $data['page'];
		
		foreach($this->_fpages as $signal => $fpage)
		{
			if(preg_match('#^'.$signal.'$#', $page, $matches))
			{
				echo $page;
				$data['page'] = preg_replace('#^'.$signal.'$#', $fpage, $page);
				return $this->callFilePage($id, $data);
			}
		}
		
		foreach($this->_fcts as $signal => $fct)
		{
			if(preg_match('#^'.$signal.'$#', $page, $matches))
			{
				$data['matches'] = $matches;
				$fct[0]->$fct[1]($id, $data);
				$found = TRUE;
			}
		}
		
		return $found;
	}
	
	public function callFilePage($id, $data)
	{
		return FALSE;
	}
	
	public function callErrorPage($error, $id, $data)
	{
		if(isset($this->_errorFcts[$error]))
		{
			$class = &$this->_errorFcts[$error][0];
			$fct = $this->_errorFcts[$error][1];
			$class->$fct($id, $data);
			return TRUE;
		}
		else
			return FALSE;
	}
	
	public function addErrorPage($error, $class, $fct)
	{
		$this->_errorFcts[$error] = array(&$class, $fct);
	}
	
	public function rmErrorPage($error)
	{
		unset($this->_errorFcts[$error]);
	}
	
	public function addClasses($classes)
	{
		NginyUS::message('Adding classes $0', array(join(', ', $classes)), E_DEBUG);
		$this->classList = array_merge($this->classList, $classes);
	}
	
	public function resetClases()
	{
		$this->classList = array();
	}
}
