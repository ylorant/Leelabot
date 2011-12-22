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
		success: function(ret){
			ret = ret.split(':');
			$('#status_'+plugin).attr('onmouseover', "$('#loadbox_"+plugin+"').fadeIn(300);");
			var image = $('#status_'+plugin+' img');
			image.attr('src', '/admin/images/cross.png');
			$('#status_'+plugin).removeClass('yellow');
			$('#status_'+plugin).addClass('red');
			$('#status_'+plugin).text('Not loaded');
			$('#status_'+plugin).append(image);
			if(ret[0] == 'success')
			{
				data = ret[1].split('/');
				
				for(var i in data)
				{
					var curPlug = data[i];
					var image = $('#status_'+curPlug+' img');
					image.attr('src', '/admin/images/ticks.png');
					$('#status_'+curPlug).removeClass('red');
					
					$('#status_'+curPlug).addClass('green');
					$('#status_'+curPlug).text('Loaded');
					$('#status_'+curPlug).append(image);
					
					image = $('#loadbox_'+curPlug+' img');
					$('#loadbox_'+curPlug).removeClass('rgreen');
					$('#loadbox_'+curPlug).addClass('orange');
					$('#loadbox_'+curPlug).text('Unload');
					$('#loadbox_'+curPlug).attr('onclick', 'unloadPlugin("'+curPlug+'");')
					$('#loadbox_'+curPlug).append(image);
				}
			}
			else
			{
				alert('Error : '+ret[1]);
			}
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
		url: "/admin/plugins/unload/"+plugin,
		success: function(ret){
			ret = ret.split(':');
			$('#status_'+plugin).attr('onmouseover', "$('#loadbox_"+plugin+"').fadeIn(300);");
			var image = $('#status_'+plugin+' img');
			image.attr('src', '/admin/images/ticks.png');
			$('#status_'+plugin).removeClass('yellow');
			$('#status_'+plugin).addClass('green');
			$('#status_'+plugin).text('Loaded');
			$('#status_'+plugin).append(image);
			$('#loadbox_'+plugin).fadeOut(300);
			if(ret[0] == 'success')
			{
				data = ret[1].split('/');
				
				for(var i in data)
				{
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
					$('#loadbox_'+plugin).attr('onclick', 'loadPlugin("'+plugin+'");')
					$('#loadbox_'+plugin).append(image);
				}
			}
			else
			{
				alert('Error : '+ret[1]);
			}
		}
	});
}
