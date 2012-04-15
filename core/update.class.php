<?php

include('lib/archive.lib.php'); // Including the archive extraction library, allowing us to extract the updates archives.

/**
 * \file core/update.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Updater class file.
 *
 * \section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * \section DESCRIPTION
 *
 * This file hosts the Updater class, allowing the bot to automatically update itself when an update is available.
 */

/**
 * \brief Updater class for leelabot.
 * 
 * This class allow Leelabot to automatically update itself. If it is actived, it will check the updates on leelabot servers and update the
 * bot if necessary.
 * 
 */
class Updater
{
	private $_mirror; ///< Mirror used to check and download updates.
	private $_automatic; ///< Automatic updates trigger.
	private $_updates; ///< Available updates list.
	private $_scheduled; ///< Scheduled updates, along with their progress & data.
	
	/** Constructor for the updater.
	 * The constructor for the class (this method) checks the config passed in parameter and sets the update mirror regarding it.
	 * If no mirror configuration has been set, the default is http://leelabot.com/
	 * 
	 * \param $config The update config section array.
	 * \param $plugins The plugin manager class, used to set the routine.
	 */
	public function __construct($config, $plugins)
	{
		if(isset($config['Mirror']))
		{
			Leelabot::message('Checking update mirror $0', $config['Mirror']);
			if($this->getLastVersion($config['Mirror']))
				$this->_mirror = $config['Mirror'];
			else
			{
				Leelabot::message('Can\'t get version from mirror, falling back to default.', array(), E_WARNING);
				$this->_mirror = 'http://leelabot.com/';
			}
		}
		else
			$this->_mirror = 'http://leelabot.com/';
		
		$this->_scheduled = array();
		
		$plugins->addEventListener('update', 'Update');
		$plugins->addRoutine($this, 'checkUpdate', 3600, TRUE);
		$plugins->addRoutine($this, 'updateRoutine', -1, TRUE);
		$plugins->addEvent('command', 'core', 'update', array($this, 'checkUpdate'), 100);
		
		if(Leelabot::$instance->outerAPI)
		{
			$WAObject = Leelabot::$instance->outerAPI->getWAObject();
			$WAObject->addPage('updates/?', array($this, 'WAPageUpdate'));
			$WAObject->addPage('updates/check', array($this, 'WAPageCheckUpdates'));
			$WAObject->addPage('updates/install/([0-9]+)', array($this, 'WAPageInstallUpdate'));
		}
	}
	
	/** Update routine.
	 * This function represents the update routine, which will perform updates in background while the bot runs normally.
	 * 
	 * \return Nothing.
	 */
	public function updateRoutine()
	{
		foreach($this->_scheduled as $i => &$update)
		{
			if(!$update['started'])
			{
				Leelabot::message("Downloading update $0...", array($this->_updates[$update['i']]->rev));
				
				if(!is_dir('tmp'))
					mkdir('tmp');
				
				$update['started'] = TRUE;
				$update['infp'] = fopen($this->_updates[$update['i']]->url, 'r');
				$update['outfp'] = fopen('tmp/update_'.$this->_updates[$update['i']]->rev.'.tar.gz', 'w+');
			}
			
			//If we haven't downloaded all the file
			if(!feof($update['infp']))
			{
				fputs($update['outfp'], fread($update['infp'], 1024));
				return;
			}
			
			
			Leelabot::message("Installing update $0...", array($this->_updates[$update['i']]->rev));
			
			$gz = new gzip_file("tmp/update_".$this->_updates[$update['i']]->rev.'.tar.gz');
			$gz->set_options(array('overwrite' => 1));
			$gz->extract_files();
			
			Leelabot::message("Update $0 done.", array($this->_updates[$update['i']]->rev));
			
			unset($this->_scheduled[$i]);
		}
	}
	
	/** Gets the last version available.
	 * This method queries the update mirror to get the last available version for download.
	 * 
	 * \param $mirror The mirror from which get the last version. Default is the mirror set in the config loaded at instanciation.
	 * 
	 * \return The data for the last version get.
	 */
	public function getLastVersion($mirror = NULL)
	{
		$versions = $this->getVersions($mirror);
		usort($versions, array($this, 'compareUpdates'));
		return $versions[count($versions)-1];
	}
	
	/** Get all available versions of Leelabot.
	 * This function queries the update mirror to get all available versions of Leelabot. It will return an array of objects containing versions
	 * data.
	 * 
	 * \param $mirror The mirror from which get the last version. Default is the mirror set in the config loaded at instanciation.
	 * 
	 * \return The data for Leelabot versions.
	 */
	public function getVersions($mirror = NULL)
	{
		if($mirror === NULL)
			$mirror = $this->_mirror;
		
		$mirror .= substr($mirror, -1) == '/' ? '' : '/';
		
		$xml = file_get_contents($mirror.'update/versions');
		
		if(!$xml)
			return FALSE;
		
		$data = simplexml_load_string($xml);
		
		if(!$data)
			return FALSE;
		
		$versions = array();
		foreach($data->version as $rev)
			$versions[] = new Storage(array('rev' => (int)$rev->rev, 'url' => (string)$rev->url));
		
		return $versions;
	}
	
	/** Routine : checks updates on the mirror.
	 * This method is bound to a routine call, which will check the availability of update for the bot, and download them if necessary.
	 * If automatic updates are enabled, it will also install them.
	 * 
	 * \return Nothing.
	 */
	public function checkUpdate()
	{
		if(!preg_match('/: ([0-9]+) \$/', Leelabot::REVISION, $matches))
			return FALSE;
		
		Leelabot::message('Checking updates...', null, E_DEBUG);
		
		$currentVersion = $matches[1];
		$last = $this->getLastVersion();
		if($last->rev <= $currentVersion)
			return;
			
		$versions = $this->getVersions();
		usort($versions, array($this, 'compareUpdates'));
		
		//Getting the last updated version
		$i = 0;
		for(; $versions[$i]->rev <= $currentVersion; $i++);
		$this->_updates = array_slice($versions, $i);
		
		Leelabot::$instance->plugins->callEvent('update', 'available');
	}
	
	public function WAPageUpdate($data)
	{
		$parser = Webadmin::getTemplateParser();
		
		$parser->assign('updates', $this->_updates);
		
		return $parser->draw('updates');
	}
	
	public function WAPageCheckUpdates()
	{
		Webadmin::disableDesign(); //We disable the design, because of AJAX
		Webadmin::disableCache();
		$this->checkUpdate();
		$parser = Webadmin::getTemplateParser();
		
		$parser->assign('updates', $this->_updates);
		
		return $parser->draw('updates');
	}
	
	public function WAPageInstallUpdate($data)
	{
		Webadmin::disableDesign(); //We disable the design, because of AJAX
		Webadmin::disableCache();
		if($this->doUpdate($data['matches'][1]))
			return 'success';
		else
			return 'error:Version not found/Update already pending';
	}
	
	/** Compare 2 updates on their rev number.
	 * This function compares the revision number of the 2 updates given in parameter and returns which one is newer than the other.
	 * 
	 * \param $version1 The first version.
	 * \param $version2 The second version.
	 * 
	 * \return An integer < 0 if the first version is newer than the other, and > 0 if the second version is newer.
	 */
	public function compareUpdates($version1, $version2)
	{
		return $version1->rev - $version2->rev;
	}
	
	/** List available updates.
	 * This function returns the list of all available updates for leelabot.
	 *
	 * \return An array containing all the available updates for leelabot and their info.
	 */
	public function getUpdates()
	{
		return $this->_updates;
	}
	
	/** Set up an update to be done.
	 * This function sets up an update, so the update files will be downloaded, installed, and the admin will be prompted to restart the bot.
	 * 
	 * \param $revision The update revision number to update to. By default it will update to the first next version.
	 * 
	 * \return TRUE if the update set up correctly, FALSE otherwise.
	 */
	public function doUpdate($revision = NULL)
	{
		if($revision !== NULL)
		{
			if(isset($this->_scheduled[$revision]))
				return FALSE;
			
			$found = FALSE;
			foreach($this->_updates as $i => $update)
			{
				if($update->rev == $revision)
				{
					$found = $i;
					break;
				}
			}
			if($found === FALSE)
				return FALSE;
		}
		else
		{
			if(empty($this->_updates))
				return FALSE;
			
			$found = 0;
			$revision = $this->_updates[0]->revision;
		}
		
		$this->_scheduled[] = array('i' => $found, 'started' => FALSE);
		
		return TRUE;
	}
	
	
}

/** InnerAPI class for updating.
 * This class can be used by bot plugins to handle updates (get them, install them).
 */
class Update
{
	private static $_instance; ///< Self-reference to the class (to make a static singleton)
	
	/** Returns the instance of the class.
	 *This function returns the auto-reference to the singleton instance of the class. It should not be called by other classes.
	 * 
	 * \return The auto-reference to the singleton.
	 */
	public static function getInstance()
	{
		if(!(self::$_instance instanceof self))
            self::$_instance = new self();
 
        return self::$_instance;
	}
	
	/** List available updates
	 * This function lists available updates for the bot.
	 * 
	 * \return An array containing the list of available updates for leelabot and their info.
	 */
	public function getList()
	{
		$self = self::getInstance();
		
		return $self->getUpdates();
	}
}
