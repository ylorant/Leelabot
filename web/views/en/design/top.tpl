<!--
        top.tpl
        
        Copyright 2011 Yohann Lorant <linkboss@gmail.com>
        
        This program is free software; you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation; either version 2 of the License, or
        (at your option) any later version.
        
        This program is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
        GNU General Public License for more details.
        
        You should have received a copy of the GNU General Public License
        along with this program; if not, write to the Free Software
        Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
        MA 02110-1301, USA.
        
        
-->

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
	<title>Leelabot - Urban Terror Admin bot</title>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	<meta name="generator" content="Geany 0.20" />
	<script type="text/javascript" src="/admin/js/jquery.js"></script>
	<script type="text/javascript" src="/admin/js/{$category}{if="!empty($subcategory)"}/{$subcategory}{/if}.js"></script>
	<link rel="stylesheet" media="screen" type="text/css" title="Design" href="/admin/style/design/design.css" />
	<link rel="stylesheet" media="screen" type="text/css" title="Design" href="/admin/style/{$category}{if="!empty($subcategory)"}/{$subcategory}{/if}.css" />
</head>

<body>
	<div class="header">
		<img class="logo" src="/admin/images/logo.png" />
		<div class="menu">
			<ul>
				<li{if="$category == ''"} class="selected"{/if}>Home</li>
				<li{if="$category == 'status'"} class="selected"{/if}>Status</li>
				<li{if="$category == 'servers'"} class="selected"{/if}>Servers</li>
				<li{if="$category == 'plugins'"} class="selected"{/if}><a href="/admin/plugins/">Plugins</a></li>
				<li class="hidden"></li>
			</ul>
		</div>
	</div>
	<div class="middle">
		<div class="left">
			{if="$category == 'plugins'"}
				<h2>Plugins</h2>
				<hr />
				<ul>
					<li{if="$subcategory == ''"} class="selected"{/if}>List</li>
				</ul>
				<hr />
				<ul>
				{loop="$plugins"}
					{if="in_array($value.name, $loaded)"}
						<li{if="$subcategory == $value.name"} class="selected"{/if}><a href="/admin/plugin/{$value.name}/index">{$value.dname}</a></li>
					{/if}
				{/loop}
				</ul>
			{/if}
		</div>
		<div class="content">	
