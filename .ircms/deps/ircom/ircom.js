/**
 * This module is used to communicate between the client and the server.
 */

/**
 * \brief This function will send and/or receive data to/from a server.
 *
 * \param {String} url The URL where the transcation should happend
 * \param {String|Number|Array|Object} data The data to transmit (null, if no data).
 * \param {Object} [options] Custom options to overwrite the default ones, \see ircomDefaults for a complete list.
 */
var Ircom = function (url, data, options) {
	/* Merge the specific options with the default ones */
	options = $.extend(Ircom.defaultOptions, options);

	/* Call the timeout function already if needed */
	options.timeoutInst = null;
	if (options.timeout != -1) {
		options.timeoutInst = setTimeout(function() {
			/* Call the onTimeout callback */
			options.onTimeout(options);
			/* Call the onComplete callback */
			options.onComplete("timeout", null, options);
		}, options.timeout * 1000);
	}

	/* Ajax options */
	var ajaxOptions = {
		type: options.method,
		url: url,
		dataType: 'json',
		error: function(xhr, status, error) {
			/* Kill the timeout instance */
			clearTimeout(options.timeoutInst);
			/* Make sure the error is not from an abortion */
			if (xhr.statusText == "abort") {
				return;
			}
			try {
				error = eval("(" + xhr.responseText + ")");
			}
			catch(e) {
				error = xhr.responseText;
				if (!error) {
					error = xhr.statusText;
				}
			}
			options.onError(error, options);

			/* Call the onComplete callback */
			options.onComplete("error", error, options);
		},
		success: function(data, status, xhr) {
			/* Kill the timeout instance */
			clearTimeout(options.timeoutInst);
			/* Call the onSuccess callback */
			options.onSuccess(data, options);
			/* Call the onComplete callback */
			options.onComplete("success", data, options);
		}
	};
	/* Add the data if any */
	if (typeof data !== "undefined" && data != null) {
		ajaxOptions["data"] = {ircom: JSON.stringify(data)};
	}
	/* Call the Ajax */
	$.ajax(ajaxOptions);
};

/**
 * \brief Default options for the ircom module
 * \type Object
 */
Ircom.defaultOptions = {
	/**
	 * \brief The default method used to communicate with the server.
	 * \type String
	 */
	method: "post",
	/**
	 * \brief Timeout for the transaction in seconds.
	 * Choose -1 for unlimited timeout.
	 * \type Integer
	 */
	timeout: 30,
	/**
	 * \brief The callback once the transaction has been completed, either after a
	 * failure, timeout or a successful transaction.
	 *
	 * \param {String} status Can have one the following values:
	 * \b success, \b error, \b timeout.
	 * \param {String|Number|Array|Object} data The data recieved.
	 * \param {Object} options Option used for the transaction, \see ircomDefaults for a complete list.
	 */
	onComplete: function (status, data, options) {},
	/**
	 * \brief Function called once the transaction has been successfully completed.
	 *
	 * \param {String|Number|Array|Object} data Data received back if any.
	 * \param {Object} options Option used for the transaction, \see ircomDefaults for a complete list.
	 */
	onSuccess: function(data, options) {},
	/**
	 * \brief Function called once the transaction encountered a failure.
	 *
	 * \param {String} message The failure message.
	 * \param {Object} options Option used for the transaction, \see ircomDefaults for a complete list.
	 */
	onError: function(message, options) {
		console.log(message);
	},
	/**
	 * \brief Function called once the transaction timed out.
	 *
	 * \param {Object} options Option used for the transaction, \see ircomDefaults for a complete list.
	 */
	onTimeout: function(options) {
	},
	/**
	 * \brief User defined arguments to be associated with the transaction. It can be anything.
	 * The value will be found in the option parameter of the callback functions.
	 */
	args: null
};

/**
 * This function adds or update a parameter to an URL.
 * Code taken from http://stackoverflow.com/questions/486896/adding-a-parameter-to-the-url-with-javascript
 * Slightly modified form its origin
 */
Ircom.urlParam = function(url, key, value) {
	key = encodeURI(key);
	value = encodeURI(value);

	var urlSeparation = url.indexOf("?");
	var kvp = (urlSeparation === -1) ? [] : url.substr(urlSeparation + 1).split("&");
	var url = (urlSeparation === -1) ? url : url.substr(0, urlSeparation); 

	var i = kvp.length;
	var x;
	while (i--) {
		x = kvp[i].split('=');
		if (x[0] == key) {
			x[1] = value;
			kvp[i] = x.join('=');
			break;
		}
	}
	if (i < 0) {
		kvp[kvp.length] = [key, value].join('=');
	}
	return url + "?" + kvp.join('&'); 
}