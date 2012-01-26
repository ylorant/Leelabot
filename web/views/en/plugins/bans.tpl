<h1>Bans</h1>

<h2>Last 5 bans</h2>
	
	{loop="$lastbans"}
		<div class="ban">
			<div class="ban_alias">{$value.Aliases.0}</div>
			<div class="ban_realm">From: {$value.Realm}</div>
			<div class="ban_start">Date: {function="date('d/m/Y H:i:s', $value.Begin)"}</div>
			<div class="ban_desc">Description: {$value.Description}</div>
		</div>
	{/loop}
</table>
