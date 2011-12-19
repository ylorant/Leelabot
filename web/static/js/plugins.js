function loadPlugin(plugin)
{
	$('#status_'+plugin).attr('onmouseover', '');
	$('#status_'+plugin).removeClass('red');
	$('#status_'+plugin).addClass('yellow');
	
	var img = $('#status_'+plugin+' img');
	$('#status_'+plugin).text('Loading...');
	img.attr('src', '/admin/images/load.gif');
	$('#status_'+plugin).append(img);
	$('#loadbox_'+plugin).fadeOut(300);
	$.ajax({
		url: "/admin/plugins/load/"+plugin,
		success: function(data){
			$('#status_'+plugin).attr('onmouseover', "$('#loadbox_"+plugin+"').fadeIn(300);");
			var image = $('#status_'+plugin+' img');
			image.attr('src', '/admin/images/ticks.png');
			$('#status_'+plugin).removeClass('yellow');
			$('#status_'+plugin).addClass('green');
			$('#status_'+plugin).text('Loaded');
			$('#status_'+plugin).append(image);
			
			image = $('#loadbox_'+plugin+' img');
			$('#loadbox_'+plugin).removeClass('rgreen');
			$('#loadbox_'+plugin).addClass('orange');
			$('#loadbox_'+plugin).text('Unload');
			$('#loadbox_'+plugin).attr('onclick', 'unloadPlugin('+plugin+');')
			$('#loadbox_'+plugin).append(image);
		}
	});
}

function unloadPlugin(plugin)
{
	$('#status_'+plugin).attr('onmouseover', '');
	$('#status_'+plugin).removeClass('green');
	$('#status_'+plugin).addClass('yellow');
	
	var img = $('#status_'+plugin+' img');
	$('#status_'+plugin).text('Unloading...');
	img.attr('src', '/admin/images/load.gif');
	$('#status_'+plugin).append(img);
	$('#loadbox_'+plugin).fadeOut(300);
	$.ajax({
		url: "/admin/plugins/load/"+plugin,
		success: function(data){
			$('#status_'+plugin).attr('onmouseover', "$('#loadbox_"+plugin+"').fadeIn(300);");
			var image = $('#status_'+plugin+' img');
			image.attr('src', '/admin/images/cross.png');
			$('#status_'+plugin).removeClass('yellow');
			$('#status_'+plugin).addClass('red');
			$('#status_'+plugin).text('Not loaded');
			$('#status_'+plugin).append(image);
			
			image = $('#loadbox_'+plugin+' img');
			$('#loadbox_'+plugin).removeClass('orange');
			$('#loadbox_'+plugin).addClass('rgreen');
			$('#loadbox_'+plugin).text('Load');
			$('#loadbox_'+plugin).attr('onclick', 'loadPlugin('+plugin+');')
			$('#loadbox_'+plugin).append(image);
		}
	});
}
