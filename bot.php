<?php

//Including all components needed by the program.
require('core/db.class.php');
require('core/innerapi.class.php');
require('core/outerapi.class.php');
require('core/plugins.class.php');
require('core/RCon.class.php');
require('core/intl.class.php');
require('core/leelabot.class.php');

$main = new Main();
$main->init($argv);

$main->run();
