/**
 * \file lib/mlpfim/mlpfimclient.js
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief MLPFIMClient class file.
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
 * This is the file containing the MLPFIM client class.
 */

/** Constructor. Initializes a client.
 * This function constructs a MLPFIMClient object, with the given URL to query.
 * 
 * \param url The server webservice url to query.
 * 
 * \return a MLPFIMClient object.
 */
function MLPFIMClient(url)
{
	this.url = url;
	this.replyMethod = null;
}

MLPFIMClient.prototype.onreply = null; ///< Reply method when a query success. Set it to your preferred method.

/** Performs a query/
 * This function performs an MLPFIM query on the server.
 * 
 * \param method The method to call
 * \param parameters An array of parameters to give to the server method on the other side.
 * 
 * \return Nothing.
 */
MLPFIMClient.prototype.query = function(method, parameters)
{
	var xhr = new XMLHttpRequest();
	xhr.open('POST', this.url);
	xhr.mlpfim = this;
	xhr.onreadystatechange = this.__onReply;
	xhr.send(JSON.stringify({ 'request': method, 'parameters': parameters}));
}

/** Internal. Reply method for XHR.
 * This function is the reply method called by XMLHttpRequest. It calls the reply function defined in the class.
 */
MLPFIMClient.prototype.__onReply = function()
{
	if(this.readyState == 4 && this.status == 200)
	{
		var json = JSON.parse(this.responseText);
		if(this.mlpfim.onreply != null)
			this.mlpfim.onreply(json.query, json.response);
	}
}
