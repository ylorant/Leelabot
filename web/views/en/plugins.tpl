<h1>Plugin list</h1>

<!--<table class="table">
	<tr>
		<th>Name</th>
		<th>Description</th>
		<th>Author</th>
		<th>Status</th>
	</tr>-->
	{loop="$plugins"}
		<!--<tr {if="in_array($value.name, $loaded)"}class="green"{else}class="red"{/if}>
			<td>{$value.name}</td>
			<td>{$value.description}</td>
			<td>{$value.author}</td>
			<td></td>
		</tr>-->
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
