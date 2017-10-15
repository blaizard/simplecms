(function($) {
	/**
	 * \brief This function creates a file explorer instance.
	 *
	 * \alias $().irexplorer
	 *
	 * \param {String|Array} [arg] The action to be passed to the function. If the instance is not created,
	 * \a action can be an \see Array that will be considered as the \a options.
	 * Otherwise \a arg must be a \see String with the following value:
	 * \li \b create - Creates the object and associate it to a selector. \code $("#test").irexplorer("create"); \endcode
	 *
	 * \param {Array} [data] The options to pass to the object during its creation. See \see $.fn.irexplorer.defaults for the complete list.
	 *
	 * \return {jQuery}
	 */
	$.fn.irexplorer = function(arg, data) {
		/* This is the returned value */
		var retval;
		/* Go through each objects */
		$(this).each(function() {
			retval = $().irexplorer.x.call(this, arg, data);
		});
		/* Make it chainable, or return the value if any  */
		return (typeof retval === "undefined") ? $(this) : retval;
	};

	/**
	 * This function handles a single object.
	 * \private
	 */
	$.fn.irexplorer.x = function(arg, data) {
		/* Load the default options */
		var options = $.fn.irexplorer.defaults;

		/* --- Deal with the actions / options --- */
		/* Set the default action */
		var action = "create";
		/* Deal with the action argument if it has been set */
		if (typeof arg === "string") {
			action = arg;
		}
		/* If the module is already created and the action is not create, load its options */
		if (this && action != "create" && $(this).data("irexplorer")) {
			options = $(this).data("irexplorer");
		}
		/* If the first argument is an object, this means options have
		 * been passed to the function
		 */
		if (typeof arg === "object") {
			options = $.extend(true, {}, options, arg);
		}

		/* Store the options to the module */
		$(this).data("irexplorer", options);

		/* Handle the different actions */
		switch (action) {
		/* Create action */
		case "create":
			/* Create the object */
			$.fn.irexplorer.create.call(this);
			break;
		/* Return the current path */
		case "path":
			return $.fn.irexplorer.getPath.call(this);
		};
	};

	/**
	 * This function cleans a path (removes duplicated '/', extra '..', ...)
	 */
	$.fn.irexplorer.normalize = function(path) {
		path = path.replace("\\", "/");
		result = [];
		pathArray = path.split("/");
		if (!pathArray[0]) {
			result.push("");
		}
		for (i in pathArray) {
			var dir = pathArray[i];
			if (dir == "..") {
				if (result[result.length - 1] == ".." || !result.pop()) {
					result.push("..");
				}
			}
			else if (dir && dir != ".") {
				result.push(dir);
			}
		}
		if (!pathArray[pathArray.length - 1]) {
			result.push("");
		}
		result = result.join("/");
		return (result == "") ? "/" : result;
	};

	/**
	 * Create an irexplorer dialog
	 * \param result This is either a callback, or an element selector of an input
	 * where the data should be store.
	 */
	$.fn.irexplorer.create = function() {
		/* Read the otpions */
		var options = $(this).data("irexplorer");
		/* If relative is == true, update the value to the path and save the options */
		if (options.relative === true) {
			options.relative = options.irexplorer.current;
		}
		/* Select the first valid view if needed */
		if (typeof options.viewList[options.view] === "undefined") {
			var first;
			for (first in options.viewList) break;
			options.view = first;
		}
		$(this).data("irexplorer", options);
		/* Empty the element */
		$(this).empty();
		/* Create the dynamic table */
		options.create.call(this);
		/* Trigger the first drawing */
		$.fn.irexplorer.fetch.call(this, options.irexplorer.current);
	};

	/**
	 * \private
	 */
	$.fn.irexplorer.onClick = function (path, type) {

		/* Read the options */
		var options = $(this).data("irexplorer");

		/* Generate the path */
		var p = $.fn.irexplorer.getPath.call(this, path);

		/* Call the callback, if it returns false, then do not process the rest of the function  */
		if (options.onClick.call(this, p, type) === false) {
			return false;
		}

		/* If the user click on a folder */
		if (type == "folder") {
			$.fn.irexplorer.fetch.call(this, path);
		}

		return true;
	};

	/**
	 * \private
	 */
	$.fn.irexplorer.onDblClick = function (path, type) {

		/* Read the options */
		var options = $(this).data("irexplorer");

		/* Generate the path */
		var p = $.fn.irexplorer.getPath.call(this, path);

		/* Call the callback, if it returns false, then do not process the rest of the function  */
		if (options.onDblClick.call(this, p, type) === false) {
			return false;
		}

		return true;
	};

	/**
	 * This function will return the path, either the relative or absolute path
	 * depending on the configuration.
	 * Optionally, it takes an argument to convert the given path.
	 */
	$.fn.irexplorer.getPath = function (path) {
		/* Read the options */
		var options = $(this).data("irexplorer");

		/* If no path is given, take the current path */
		if (typeof path === "undefined") {
			path = options.irexplorer.current;
		}

		/* If a relative path is requested */
		var p = path;
		if (options.relative) {
			var base = options.relative;
			var path_x = path.replace(/^\s*\/*|\/*\s*$/g, '').split("/");
			var base_x = base.replace(/^\s*\/*|\/*\s*$/g, '').split("/");
			/* The new path */
			p = "";
			/* Remove the common directories */
			while (typeof path_x[0] !== "undefined" && typeof base_x[0] !== "undefined" && path_x[0] == base_x[0]) {
				path_x.shift();
				base_x.shift();
			}
			/* Add the .. directories to go up */
			while (base_x.pop()) {
				p += "../";
			}
			/* Paste the rest */
			p += path_x.join("/");
			/* Remove the trailing / if any, to normalize the path */
			p = p.replace(/\/+$/, "")
			/* If the path is empty, add a dot and add the trailing slash if it is a directory */
			path = ((p == "") ? "." : p) + ((path.slice(-1) == "/") ? "/" : "");
		}

		/* Return the path */
		return path;
	};

	/**
	 * This function fill the table with data
	 * \private
	 */
	$.fn.irexplorer.data = function (data) {

		/* Read the options */
		var options = $(this).data("irexplorer");
		/* Update the data */
		var rows = [];
		/* Loop through the data */
		for (var i in data['data']) {
			/* This is one data row, corresponding to a file */
			var row = data['data'][i]["d"];
			/* Loop through each data of a file and try to get its display value */
			for (var key in row) {
				row[key] = [(typeof options.format[key] === "function") ? options.format[key].call(this, row[key]) : row[key], row[key]];
			}
			/* Add the update data */
			rows.push({
				d: row,
				a: data['data'][i]["a"]
			});
		}
		/* Print the data */
		options.update.call(this, data["columns"], rows, options.view);
		/* Call the onLoad function */
		options.onLoad.call(this);
	};

	/**
	 * This function will fetch the file information
	 * \private
	 */
	$.fn.irexplorer.fetch = function (path) {
		/* Read the options */
		var options = $(this).data("irexplorer");
		/* Update the current path options */
		options["irexplorer"]["current"] = $.fn.irexplorer.normalize(path + "/");
		/* Save the new options */
		$(this).data("irexplorer", options);

		var view = options.view;
		if (typeof options.viewList[view] !== "object") {
			options.onError.call(this, "Unsupported view `" + view + "'");
			return;
		}
		/* Overwrite the arguments */
		if (typeof options["irexplorer"]["args"] === "undefined") {
			options["irexplorer"]["args"] = [];
		}
		/* Add the required parameters to the irexplorer options */
		for (var i in options.viewList[view]) {
			var p = options.viewList[view][i];
			if (jQuery.inArray(p, options["irexplorer"]["args"]) == -1) {
				options["irexplorer"]["args"].push(p);
			}
		}

		/* call the fetch function */
		options.fetch.call(this, options["irexplorer"]);
	};

	/**
	 * Default list view
	 * \private
	 */
	$.fn.irexplorer.viewList = function (categories, rows) {
		var obj = this;
		var table = document.createElement("table");

		var thead = document.createElement("thead");
		var tr = document.createElement("tr");
		/* Add an empty category for the thumbnails */
		$(tr).append("<th></th>");
		/* Fill in the header (categories) */
		for (i in categories) {
			var th = document.createElement("th");
			$(th).text(categories[i]["name"]);
			$(tr).append(th);
		}
		$(thead).append(tr);

		var tbody = document.createElement("tbody");
		/* Fill in the rows */
		for (i in rows) {
			var tr = document.createElement("tr");
			/* Add the thumbnail here */
			var td = document.createElement("td");
			$(td).append($.fn.irexplorer.thumbnail.call(this, rows[i]["a"]["thumbnail"], 24, 24));
			$(tr).append(td);
			/* Add the categories */
			for (j in categories) {
				var id = categories[j]["id"];
				var td = document.createElement("td");
				if (typeof rows[i]["d"][id] === "object") {
					$(td).text(rows[i]["d"][id][0]);
				}
				$(tr).append(td);
			}
			/* Associate the arguments to the row */
			$(tr).data("irexplorer", rows[i]["a"]);
			/* Add the onclick event */
			$(tr).on("click", function() {
				var args = $(this).data("irexplorer");
				$.fn.irexplorer.onClick.call(obj, args["path"], args["type"]);
			});
			/* Add the ondblclick event */
			$(tr).dblclick(function() {
				var args = $(this).data("irexplorer");
				$.fn.irexplorer.onDblClick.call(obj, args["path"], args["type"]);
			});
			$(tbody).append(tr);
		}
		$(table).append(thead);
		$(table).append(tbody);
		$(this).find(".irexplorer-container").append(table);
	};

	/**
	 * This function will create a thumbnail from a thumbnail argument
	 * passed by irexplorer backend.
	 * This is a helper function.
	 */
	$.fn.irexplorer.thumbnail = function (thumbnail, width, height) {
		/* Read the options */
		var options = $(this).data("irexplorer");
		/* Generate the thumbnail URL */
		var url = Ircom.urlParam(options.apiURL, "irexplorer", "thumbnail");
		url = Ircom.urlParam(url, "irexplorer-thumb", thumbnail);
		url = Ircom.urlParam(url, "irexplorer-size", Math.max(width, height));
		var img = document.createElement("div");
		$(img).addClass("irexplorer-thumbnail");
		$(img).css({
			backgroundImage: "url(" + url + ")",
			backgroundSize: "contain",
			backgroundPosition: "center",
			backgroundRepeat: "no-repeat",
			width: width + "px",
			height: height + "px"
		});
		return img;
	}

	/**
	 * Default list view
	 * \private
	 */
	$.fn.irexplorer.viewThumbnails = function (categories, rows, width, height) {
		var obj = this;
		/* Set default width and height */
		if (typeof width === "undefined") {
			width = 100;
			height = 100;
		}
		/* Read the options */
		var container = document.createElement("div");
		/* Loop through each elements */
		for (i in rows) {
			var thumbnail = document.createElement("div");
			$(thumbnail).css({
				float: "left",
				margin: "10px",
				width: width + "px",
				height: (height + 15) + "px",
				paddingBottom: "20px",
				textAlign: "center",
				overflow: "hidden",
				fontSize: "10px",
				fontWeight: "bold",
				whiteSpace: "nowrap"
			});
			/* Generate the thumbnail */
			$(thumbnail).append($.fn.irexplorer.thumbnail.call(this, rows[i]["a"]["thumbnail"], width, height));
			/* Add the filename */
			var name = document.createElement("div");
			$(name).text(rows[i]["a"]["name"]);
			$(thumbnail).append(name);
			/* Associate events */
			$(thumbnail).data("irexplorer", rows[i]["a"]);
			/* Add the onclick event */
			$(thumbnail).on("click", function() {
				var args = $(this).data("irexplorer");
				$.fn.irexplorer.onClick.call(obj, args["path"], args["type"]);
			});
			/* Add the ondblclick event */
			$(thumbnail).dblclick(function() {
				var args = $(this).data("irexplorer");
				$.fn.irexplorer.onDblClick.call(obj, args["path"], args["type"]);
			});
			$(container).append(thumbnail);
		}
		$(container).append("<div style=\"clear: both;\"></div>");

		$(this).find(".irexplorer-container").append(container);
	};

	/**
	 * \brief Default options, can be overwritten.
	 * \alias $().irexplorer.defaults
	 * \type Object
	 */
	$.fn.irexplorer.defaults = {
		/**
		 * The url of the api
		 */
		apiURL: "api.php",
		/**
		 * Use absolute or relative path
		 */
		relative: false,
		/**
		 * Overwrite the default view. If null, it will select the first valid view.
		 */
		view: "list",
		/**
		 * The different views for the browser
		 */
		viewList: {
			list: ["type", "path", "thumbnail"],
			thumbnails: ["type", "path", "thumbnail", "name"]
		},
		/**
		 * \brief Default irexplorer options, see irexplorer/php for more information.
		 * \type Object
		 */
		irexplorer: {
			current: "/"
		},
		/**
		 * Format table that helps formating specific ids. By doing this on the client side,
		 * it decreases traffic size and hence responsiveness and flexibility.
		 * This function will generate the display string for each values.
		 */
		format: {
			type: function(str) {
				return (str == "folder") ? "File Folder" : str.toUpperCase() + " File";
			},
			size: function(str) {
				if (str === "") {
					return "-";
				}
				var size = parseInt(str);
				var i = -1;
				var byteUnits = [" kB", " MB", " GB", " TB"];
				do {
					size = size / 1024;
					i++;
				} while (i < (byteUnits.length - 1) && size > 1024);
				return Math.max(size, 0.1).toFixed(1) + byteUnits[i];
			},
			date: function(str) {
				var date = new Date(parseInt(str) * 1000);
				return (date.getDate() < 10 ? "0" : "") + date.getDate()
					+ "." + (date.getMonth() + 1 < 10 ? "0" : "") + (date.getMonth()+1)
					+ "." + date.getFullYear()
					+ " " + (date.getHours() < 10 ? "0" : "") + date.getHours()
					+ ":" + (date.getMinutes() < 10 ? "0" : "") + date.getMinutes()
			}
		},
		/**
		 * Create the container of the browser
		 */
		create: function() {
			// Read the options
			var options = $(this).data("irexplorer");
			// Save the instance of the main object for later use
			var obj = this;
			// Create the header that will contain the views
			var header = document.createElement("div");
			$(header).addClass("irexplorer-header");
			// Insert the view
			for (var view in options.viewList) {
				var v = document.createElement("a");
				$(v).addClass("irexplorer-view irexplorer-view-" + view);
				$(v).data("irexplorer", view);
				$(v).text(view);
				$(v).click(function() {
					// Update the options with the new view
					var options = $(obj).data("irexplorer");
					options.view = $(this).data("irexplorer");
					$(obj).data("irexplorer", options);
					// Trigger the change
					$.fn.irexplorer.fetch.call(obj, options.irexplorer.current);
				});
				$(header).append(v);
			}
			$(this).append(header);
			// Create the container that will contain the browser data
			var container = document.createElement("div");
			$(container).addClass("irexplorer-container");
			$(this).append(container);
		},
		/**
		 * Update the content of the table. Note that the view at this point is valid.
		 */
		update: function(categories, rows, view) {
			switch (view) {
			case "list":
				$.fn.irexplorer.viewList.call(this, categories, rows);
				break;
			case "thumbnails":
				$.fn.irexplorer.viewThumbnails.call(this, categories, rows);
				break;
			}
		},
		/**
		 * Fetch data
		 */
		fetch: function(irexplorerOptions) {
			/* Clear everything, to show the user that something is happening */
			$(this).find(".irexplorer-container").empty();
			/* Read the options */
			var options = $(this).data("irexplorer");
			/* Build the Ircom options */
			var ircomOptions = {
				/* Pass the objetc instance as argument */
				args: this,
				/* on success call the data function */
				onSuccess: function(data, options) {
					/* Instance of the object */
					var obj = options.args;
					$.fn.irexplorer.data.call(obj, data);
				},
				/* On error call the error function */
				onError: function(message, options) {
					/* Instance of the object */
					var obj = options.args;
					options = $(obj).data("irexplorer");
					options.onError.call(obj, message);
				}
			};
			/* Update the table */
			Ircom(
				Ircom.urlParam(options.apiURL, "irexplorer", "fetch"),
				irexplorerOptions,
				ircomOptions);
		},
		/**
		 * \brief Function that will define what to do once the user clicks on a file.
		 * Note: "this" refers to the irexplorer instance.
		 * \param {String} path The current path of the file explorer view
		 * \param {String} type The type of file. Value is \b folder if this is a folder, the file extension otherwise
		 */
		onClick: function(path, type) {},
		/**
		 * \brief Function that will define what to do once the user double clicks on a file.
		 * Note: "this" refers to the irexplorer instance.
		 * \param {String} path The current path of the file explorer view
		 * \param {String} type The type of file. Value is \b folder if this is a folder, the file extension otherwise
		 */
		onDblClick: function(path, type) {},
		/**
		 * \brief Callback called once irexplorer has been loaded.
		 * Note: "this" refers to the irexplorer instance.
		 */
		onLoad: function() {},
		/**
		 * \brief Callback called once there is an error.
		 * An error can be raised when the user tries to enter a directory is does not have access to for example.
		 * Note: "this" refers to the irexplorer instance.
		 * \param {String} message The error message
		 */
		onError: function(message) {
			console.log(message);
		}
	};

})(jQuery);
/**
 * IrexplorerDialog class, use to create a irexplorer dialog box
 */
IrexplorerDialog = function (options) {
	/* Merge the default options with the preset if any */
	/* Merge and save the options */
	this.options = $.extend(true, {},
			IrexplorerDialog.defaultOptions,
			IrexplorerDialog.defaultOptions.presets[options.mode],
			options, {
				/* Instance of this object to be saved within the irexplorer options */
				_irexplorerDialog: this,
				/* This will contain the instance of the irexplorer object */
				_irexplorer: document.createElement("span")
			});
	/* Create the dialog */
	var obj = this;
	var container = this.options._irexplorer;
	this.options.dialogCreate.call(this, this.options, function() {
		$(container).irexplorer(obj.options);
		return container;
	});
};

/**
 * Get the irexplorer instance associated with this dialog
 */
IrexplorerDialog.prototype.getIrexplorer = function() {
	return this.options._irexplorer;
};

/**
 * Get the dialog instance
 */
IrexplorerDialog.prototype.getDialog = function() {
	return $(this.getIrexplorer()).parents(".irexplorer-dialog:first");
};

/**
 * Function to be called once a file is selected
 */
IrexplorerDialog.prototype.validate = function() {
	/* Retrieve the path */
	var path = $(this.getDialog()).find(".irexplorer-path").val();
	/* Close the dialog */
	this.options.dialogClose.call(this);
	/* Callback the validate function */
	this.options.onValidate.call(this, path);
};

/**
 * Return the IrexplorerDialog instance from an Irexplorer instance
 */
IrexplorerDialog.fromIrexplorer = function(irexplorerInstance) {
	var options = $(irexplorerInstance).data("irexplorer");
	return options._irexplorerDialog;
};

/**
 * Default options
 */
IrexplorerDialog.defaultOptions = {
	/**
	 * Default title of the dialog
	 */
	title: "File Browser",
	/**
	 * Select the mode, the following are supported:
	 * file: to select a file only
	 * dir: to select a directory only
	 */
	mode: "file",
	/**
	 * These are the preset modes available
	 */
	presets: {
		/**
		 * File selector preset
		 */
		file: {},
		/**
		 * Directory preset
		 */
		directory: {
			irexplorer: {
				show: ["name", "date"],
				showType: ["folder"]
			}
		},
		/**
		 * Image preset
		 */
		image: {
			view: "thumbnails",
			irexplorer: {
				showType: ["folder", "(gif|jpg|png|bmp)"]
			}
		}
	},
	/**
	 * Function that will create the dialog box or container of the explorer
	 * Note that to be compatible with the backend, it should include the followings:
	 * 1. the class "irexplorer-dialog" should be assigned to the top level dialog itself
	 * 2.
	 */
	dialogCreate: function(options, createBody) {
		var obj = this;
		var div = document.createElement("div");
		$(div).addClass("irexplorer-dialog");
		var header = document.createElement("h1");
		$(header).text(options.title);
		$(div).append(header);
		var body = document.createElement("div");
		$(body).append(createBody());
		$(div).append("<div><input type=\"text\" class=\"irexplorer-path\"/></div>");
		$(div).append(body);
		var button = document.createElement("button");
		$(button).attr("type", "button");
		$(button).text("select");
		$(button).click(function() {
			obj.validate();
			$(div).remove();
		});
		$(div).append(button);
		$("body").prepend(div);
	},
	/**
	 * Close the dialog previously created
	 */
	dialogClose: function() {
		$(this.getDialog()).remove();
	},
	/**
	 * Hook to the onclick event
	 */
	onClick: function(path, type) {
		/* Select the file */
		var ex = IrexplorerDialog.fromIrexplorer(this);
		$(ex.getDialog()).find("input.irexplorer-path").val(path);
	},
	onDblClick: function(path, type) {
		var ex = IrexplorerDialog.fromIrexplorer(this);
		ex.validate();
	},
	/**
	 * Callback fired once a file has been selected.
	 * "this" represents the IrexplorerDialog instance 
	 */
	onValidate: function(path) {
		alert(path);
	},
	/**
	 * Hook to the onload event
	 */
	onLoad: function() {
		var ex = IrexplorerDialog.fromIrexplorer(this);
		$(ex.getDialog()).find("input.irexplorer-path").val($(this).irexplorer("path"));
	}
};
/**
 * Addon to support bootstrap
 */
/*
var irexplorerUpdateOriginal = $().irexplorer.defaults.update;
var irexplorerCreateOriginal = $().irexplorer.defaults.create;
(function($) {
	$.fn.irexplorer.defaults.create = function() {
		irexplorerCreateOriginal.call(this);
		// Update the view buttons
		$(this).find(".irexplorer-header").css("text-align", "right");
		$(this).find(".irexplorer-header .irexplorer-view").wrap("<li></li>").parent().wrapAll("<ul class=\"pagination\"></ul>");
		// Update the icons
		$(this).find(".irexplorer-header .irexplorer-view-list").html("&nbsp;<span class=\"glyphicon glyphicon-th-list\" aria-hidden=\"true\"></span>&nbsp;");
		$(this).find(".irexplorer-header .irexplorer-view-thumbnails").html("&nbsp;<span class=\"glyphicon glyphicon-th\" aria-hidden=\"true\"></span>&nbsp;");
	}
	$.fn.irexplorer.defaults.update = function(categories, rows, view) {
		irexplorerUpdateOriginal.call(this, categories, rows, view);
		switch (view) {
		case "list":
			// Add the table class to the table
			$(this).find("table").addClass("table table-hover");
			break;
		case "thumbnails":
			$(this).find(".irexplorer-thumbnail").css({
				borderRadius: "5px",
				border: "1px solid #ccc"
			});
			break;
		}
		// Select the right view
		$(this).find(".irexplorer-header .irexplorer-view").parent().removeClass("active");
		$(this).find(".irexplorer-header .irexplorer-view-" + view).parent().addClass("active");
	}
})(jQuery);
*/
/**
 * Update the dialog
 */
IrexplorerDialog.defaultOptions.dialogCreate = function(options, createBody) {
	var obj = this;

	var modal = document.createElement("div");
	$(modal).addClass("modal fade irexplorer-dialog");
	// Hack as sometimes the dialog is not above all elements
	$(modal).css("z-index", "2147483647");
	$(modal).prop("role", "dialog");
	// Sub containe
	var dialog = document.createElement("div");
	$(dialog).addClass("modal-dialog modal-lg");
	/* Content */
	var content = document.createElement("div");
	$(content).addClass("modal-content");
	/* Header */
	var header = document.createElement("div");
	$(header).addClass("modal-header");
	$(header).html("<h2>" + $("<div/>").html(options.title).text() + "</h2>");
	$(content).append(header);
	/* Body */
	var body = document.createElement("div");
	$(body).addClass("modal-body");
	/* Directory path input */
	var div = document.createElement("div");
	$(div).addClass("input-group");
	$(div).append("<span class=\"input-group-addon\">Path</span>");
	var input = document.createElement("input");
	$(input).prop("type", "text");
	$(input).addClass("form-control irexplorer-path");
	$(div).append(input);
	$(body).append(div);
	/* Generate the body */
	$(body).append(createBody());
	$(content).append(body);
	/* Footer */
	var footer = document.createElement("div");
	$(footer).addClass("modal-footer");
	$(footer).append("<button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Cancel</button>");
	var button = document.createElement("button");
	$(button).addClass("btn btn-primary");
	$(button).attr("type", "button");
	$(button).text("Select");
	$(button).click(function() {
		obj.validate();
	});
	$(footer).append(button);
	$(content).append(footer);
	/* Attach everything */
	$(dialog).append(content);
	$(modal).append(dialog);

	$(modal).modal();
};

IrexplorerDialog.defaultOptions.dialogClose = function() {
	$(this.getDialog()).modal("hide");
};
