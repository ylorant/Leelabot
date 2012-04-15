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
					var name = $('#pluginMenu-'+ curPlug).html();
					$('#pluginMenu-'+ curPlug).html('<a href="/admin/plugin/'+curPlug+'">'+name+'</a>');
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
			$('#status_'+plugin).attr('onmouseover', "$('#loadbox_"+plugin+"').fadeIn(300);");
			if(ret[0] == 'success')
			{
				data = ret[1].split('/');
				
				for(var i in data)
				{
					var curPlug = data[i];
					
					var image = $('#status_'+curPlug+' img');
					image.attr('src', '/admin/images/cross.png');
					$('#status_'+curPlug).removeClass('yellow');
					$('#status_'+curPlug).addClass('red');
					$('#status_'+curPlug).text('Not loaded');
					$('#status_'+curPlug).append(image);
					
					image = $('#loadbox_'+curPlug+' img');
					$('#loadbox_'+curPlug).removeClass('orange');
					$('#loadbox_'+curPlug).addClass('rgreen');
					$('#loadbox_'+curPlug).text('Load');
					$('#loadbox_'+curPlug).attr('onclick', 'loadPlugin("'+curPlug+'");')
					$('#loadbox_'+curPlug).append(image);
					$('#pluginMenu-'+ curPlug).html(ucfirst(curPlug));
				}
				
				if(data.length > 1)
					alert('Unloaded automatically loaded dependencies : '+ data.join(', '));
			}
			else
			{
				alert('Error : '+ret[1]);
			}
		}
	});
}
