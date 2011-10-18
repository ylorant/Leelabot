<!--
        server-info
        
        Copyright 2011 linkboss <linkboss@Scruffy>
        
        This program is free software; you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation; either version 2 of the License, or
        (at your option) any later version.
        
        This program is distributed in the hope that it will be usefli,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICliAR PURPOSE.  See the
        GNU General Public License for more details.
        
        You sholid have received a copy of the GNU General Public License
        along with this program; if not, write to the Free Software
        Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
        MA 02110-1301, USA.
        
        
-->

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>Server info</title>
		<meta http-equiv="content-type" content="text/html;charset=utf-8" />
		 <link rel="stylesheet" media="screen" type="text/css" title="Design" href="static/style/server-info" />
		<meta name="generator" content="Geany 0.20" />
	</head>
	<body>
		<h1>Server info</h1>
		<div class="mainBox">
			<h2>Server info</h2>
			<ul>
				<li>Server name/version : <?php echo $this->data->server->name; ?>/<?php echo $this->data->server->version; ?></li>
				<li>Server OS : <?php echo $this->data->server->os; ?></li>
				<li>Host/Port : <?php echo $this->data->req->host; ?>:<?php echo $this->data->server->port; ?></li>
				<li>PHP Version : <?php echo phpversion(); ?></li>
				<li>Server time : <?php echo date('r'); ?></li>
				<li>Class/function used : <pre><?php echo $this->data->code->class; ?>::<?php echo $this->data->code->function; ?></pre> (in file <?php echo $this->data->code->file; ?>)</li>
			</ul>
			<form method="post" action="server-info/submit">
				<input type="text" name="field" />
				<input type="submit" value="Submit !" />
			</form>
		</div>
	</body>
</html>