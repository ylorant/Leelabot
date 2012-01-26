<h1>Plugin list</h1>

{loop="$plugins"}
	<div class="pluginbox">
		<div class="status {if="in_array($value.name, $loaded)"}green{else}red{/if}" id="status_{$value.name}" onmouseover="$('#loadbox_{$value.name}').fadeIn(300);">
			{if="in_array($value.name, $loaded)"}
				Loaded <img src="/admin/images/ticks.png" />
			{else}
				Not loaded <img src="/admin/images/cross.png" />
			{/if}
		</div>
		<div class="loadbox {if="in_array($value.name, $loaded)"}orange" onclick="unloadPlugin('{$value.name}');"{else}rgreen"  onclick="loadPlugin('{$value.name}');"{/if} id="loadbox_{$value.name}" onmouseout="$('#loadbox_{$value.name}').fadeOut(300);" >
			{if="in_array($value.name, $loaded)"}
				Unload
			{else}
				Load
			{/if}
			<img src="/admin/images/power.png" />
		</div>
		<h2>{$value.dname}</h2>
		
		<p>
			<strong>Author :</strong> {$value.author}<br />
			<strong>Version :</strong> {$value.version}<br />
			<strong>File :</strong> {$value.file}<br />
			<strong>Dependencies :</strong> {$value.dependencies}<br />
			<strong>Description :</strong> {$value.description}
		</p>
			
		<div class="serverbox">
			<strong>Used on :</strong> {$servers[$value.name]}
		</div>
	</div>
{/loop}
