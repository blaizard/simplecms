/* irform.bootstrap.js (2016.11.12) by Blaise Lengrand
 */
/* irrequire.min.js (2016.11.04) by Blaise Lengrand
 */
/* irRequire (2016.11.01) by Blaise Lengrand */
!function(r){r.irRequire||(r.irRequire=function(r,e,i,n){var u=-1,t=irRequire;e||(u=r,r=t.r[u][0],e=t.r[u][1],i=t.r[u][2],t.r[u][3]-=100,n=t.r[u][3]),"undefined"==typeof n&&(n=5e3),"string"==typeof r&&(r=[r]);var f=1;try{for(var o in r)if("undefined"==typeof eval(r[o])){f=0;break}}catch(c){f=0}f?(u>=0&&(clearTimeout(t.r[u][4]),t.r[u]=0),e(i)):(0>u&&(t.r.push([r,e,i,n]),u=t.r.length-1),n>0&&(t.r[u][4]=setTimeout(function(){t(u)},100)))},r.irRequire.r=[],r.irRequire.trigger=function(){irRequire.r.forEach(function(r,e){r&&irRequire(e)})})}(window);/**
 * This file handles forms in general. It adds the ability to read form values dynamically and
 * adds multi form functionality (i.e. being able to have arrays in form).
 *
 * When create a new element that can work with Irform, the following methods/events should be implemented 
 * and attached to the tag with the "name" attribute. 
 * - Methods:
 *   - val: Sets or get the value of/from the element
 * - Events
 *   - change: to enable validation on value changed and sub element dynamic creation
 */

/**
 * \brief Irform class, use to manipulate forms and create
 * \param [options] Options to be passed to the form, see \ref Irform.defaultOptions for more details.
 */
var Irform = function (container, formDescription, options) {
	// Trigger require, to make sure that any pending modules are loaded
	irRequire.trigger();
	this.container = $(container);
	this.formDescription = formDescription;
	this.options = $.extend(true, {}, Irform.defaultOptions, options);
	// Empty the container first
	this.container.empty();
	// Set the class
	$(this.container).addClass("irform-layout");
	// Call the hook
	this.options.hookInit.call(this);
	// Create the form
	this.create(container, formDescription);
};

/**
 * Preset used for validation
 */
//! \{
/**
 * \brief Validates a to z characters
 */
Irform.CHAR_A_Z = 0x01;
/**
 * \brief Validates numeric characters
 */
Irform.CHAR_0_9 = 0x02;
/**
 * \brief Validates spaces, including newlines and tabs
 */
Irform.CHAR_SPACE = 0x04;
/**
 * \brief Validates special characters: \code !"#$%&'()*+,-./:;<=>?@[\]^_`{|}~ \endcode
 */
Irform.CHAR_SPECIAL = 0x08;
/**
 * \brief Validates upper case charaters only
 */
Irform.CHAR_UPPERCASE_ONLY = 0x10;
/**
 * \brief Validates lower case characters only
 */
Irform.CHAR_LOWERCASE_ONLY = 0x20;
/**
 * \brief Validates emails
 */
Irform.EMAIL = "^[A-Za-z0-9\._%\+\-]+@[A-Za-z0-9\.\-]+\.[a-zA-Z]{2,}$";
//! \}

/**
 * Default options of the form make. It can be overwritten or updated by modules.
 */
Irform.defaultOptions = {
	/**
	 * Describes the fields that can be used with this form
	 */
	fields: {
		input: function(name, options) {
			return $("<input>", {
				type: "text",
				name: name,
				class: "irform",
				placeholder: options.placeholder
			});
		},
		/**
		 * \brief Create one or multiple checkbox field(s).
		 */
		checkbox: function(name, options) {
			var createCheckbox = function(name, label, inline) {
				var container = $("<div>", {
					class: (inline) ? "irform-inline" : ""
				});
				var input = $("<input>", {
					type: "checkbox",
					name: name,
					class: "irform",
					id: "irform-checkbox-" + (++Irform.unique)
				});
				$(container).append(input);
				$(container).append("<label for=\"" + $(input).prop("id") + "\">" + label + "</label>");
				return container;
			};

			if (!options.list) {
				return createCheckbox(name, "", false);
			}
			if (options.list.length == 1) {
				return createCheckbox(name, options.list[0], false);
			}

			var container = $("<div>", {
				class: "irform-container",
				name: name
			});
			var list = options.list;
			for (var i in list) {
				var checkbox = (list[i] instanceof Array) ?
						createCheckbox(list[i][0], list[i][1], options.inline)
						: createCheckbox(list[i], list[i], options.inline);
				$(container).append(checkbox);
			}

			return container;
		},
		switch: function(name/*, options*/) {
			var container = $("<div>", {
				class: "irform irform-switch"
			});
			var input = $("<input>", {
				type: "checkbox",
				name: name,
				id: "irform-switch-" + (++Irform.unique)
			});
			$(container).append(input);
			$(container).append("<label for=\"" + $(input).prop("id") + "\"></label>");
			return container;
		},
		password: function(name, options) {
			return $("<input>", {
				type: "password",
				name: name,
				class: "irform",
				placeholder: options.placeholder
			});
		},
		textarea: function(name, options) {
			return $("<textarea>", {
				name: name,
				class: "irform",
				placeholder: options.placeholder
			});
		},
		submit: function(name, options) {
			var button = $("<button>", {
				type: "button",
				name: name,
				class: "irform",
			});
			$(button).text(options.value || "Submit");	
			var obj = this;
			$(button).click(function() {
				obj.submit(options.callback);
			});
			return button;
		},
		select: function(name, options) {
			var select = $("<select>", {
				name: name,
				class: "irform"
			});
			var list = options["list"];
			for (var name in list) {
				var opt = $("<option>", {
					value: (list instanceof Array) ? list[name] : name
				});
				$(opt).text(list[name]);
				$(select).append(opt);
			}
			return select;
		}
	},
	/**
	 * This describes the default field type when none is specified
	 */
	defaultType: "input",
	/**
	 * The wrapper to go around the field and keep a common consistency
	 * \param elt
	 * \param options
	 * \param name
	 */
	wrapper: function(elt, options/*, name*/) {
		var wrapper = $("<div>");
		var div = $("<div>", {
			class: "irform-caption"
		});
		$(div).text(options.caption);
		var value = $("<div>", {
			class: "irform-elements"
		});
		$(value).append(elt);
		$(wrapper).append(div);
		$(wrapper).append(value);
		return wrapper;
	},
	/**
	 * This function will be called in case of error (validation, missing value)
	 */
	callbackError: function(errorList) {
		var msg = "";
		// Clean previous error
		$(this.container).find(".irform-item.error").removeClass("error");
		for (var i in errorList) {
			// Set error
			$(errorList[i]["item"]).removeClass("success").addClass("error");
			switch (errorList[i]["type"]) {
			case "required":
				msg += "'" + errorList[i]["name"] + "' is required\n";
				break;
			case "validation":
				msg += "'" + errorList[i]["name"] + "' does not validate\n";
				break;
			}
		}
		alert(msg);
	},
	/**
	 * Called once an item has been validated successfully
	 * \param item
	 * \param name
	 */
	callbackSuccess: function(item/*, name*/) {
		$(item).removeClass("error").addClass("success");
	},
	/**
	 * Called once an item needs to be disabled
	 */
	disable: function(isDisabled, elt) {
		var nameHolder = Irform.findNameHolder(elt);
		$(nameHolder).find("input,textarea,select,button").add(nameHolder).prop("disabled", isDisabled);
		$(elt).find("input,textarea,select,button").filter(".irform").prop("disabled", isDisabled);
		if (isDisabled) {
			$(elt).addClass("disable");
		}
		else {
			$(elt).removeClass("disable");
		}
		Irform.queue(nameHolder, function() {
			$(nameHolder).trigger((isDisabled) ? "disable" : "enable");
		});
	},
	/**
	 * Hook called during the initialization phase
	 */
	hookInit: function() {}
};

Irform.validate = function (presets, value) {
	if (typeof value === "string") {
		/* Check if the upper case condition is set */
		if (presets & Irform.CHAR_UPPERCASE_ONLY && value.test(/[a-z]/g)) {
			return false;
		}
		/* Check if the lower case condition is set */
		if (presets & Irform.CHAR_LOWERCASE_ONLY && value.test(/[A-Z]/g)) {
			return false;
		}
		/* Replace all a-z characters regardless of the case */
		if (presets & Irform.CHAR_A_Z) {
			value = value.replace(/[a-z]/ig, "");
		}
		/* Replace all numbers */
		if (presets & Irform.CHAR_0_9) {
			value = value.replace(/[0-9]/g, "");
		}
		/* Replace all spaces */
		if (presets & Irform.CHAR_SPACE) {
			value = value.replace(/[ ]/g, "");
		}
		/* Replace all special characters */
		if (presets & Irform.CHAR_SPECIAL) {
			value = value.replace(/[!"#\$%&'\(\)\*\+,\-\.\/:;<=>\?@\[\\\]^_`\{\|\}~]/g, "");
		}
		return (value === "") ? true : false;
	}
	return true;
}

/**
 * This function find the name holder element from a parent element
 * \param [name] The optional name attribute
 */
Irform.findNameHolder = function (elt, name) {
	if (typeof name === "undefined") {
		name = ($(elt).hasClass("irform-item")) ? $(elt).attr("data-irform") : null;
	}
	return $(elt).find("[name" + ((name) ? ("=" + name) : "") + "]").addBack("[name" + ((name) ? ("=" + name) : "") + "]").first();
}

/**
 * Creates a form
 */
Irform.prototype.create = function (container, formDescription) {
	// To generate unique Ids
	if (typeof Irform.unique === "undefined") {
		Irform.unique = 0;
	}
	for (var i in formDescription) {
		// Save as local to pass it through callbacks
		var obj = this;
		var itemOptions = $.extend({
			name: "irform" + (++Irform.unique),
			type: this.options.defaultType,
			required: false,
			validate: null,
			disabled: false,
			// By default set item with a name set automatically as ignored
			ignore: (typeof formDescription[i].name === "undefined") ? true : false,
			options: {}
		}, formDescription[i]);
		// Read the name
		var itemName = itemOptions.name;
		// Assign everything back, this must be done due to the automatically generated name
		formDescription[i] = itemOptions;
		// Generate the element
		var type = itemOptions["type"];
		if (typeof this.options.fields[type] !== "function") {
			console.log("'" + type + "' is not supported");
			continue;
		}
		var containerItem = this.options.fields[type].call(this, itemName, itemOptions, function() {
			// Remove the pending tag and execute all the pending items in the queue
			$(this).removeClass("irform-pending");
			Irform.queue(this);
		});
		var nameHolder = Irform.findNameHolder(containerItem, itemName);
		// Define the on-change function
		$(nameHolder).on("change", function() {
			var name = $(this).prop("name") || $(this).attr("name");
			var value = Irform.get(this)[name];
			var item = $(this).parents(".irform-item:first");
			var itemOptions = $(item).data("irform");

			// If there is a validate condition
			if (!Irform.isEmpty(value) && itemOptions.validate) {
				if (obj.validate(item, value)) {
					obj.options.callbackSuccess.call(obj, item, name);
				}
				else {
					obj.options.callbackError.call(obj, [{type: "validation", item: item, name: name, options: itemOptions}]);
				}
			}
			// Validate also if the item is required and the value is non-empty
			else if (!Irform.isEmpty(value) && $(item).hasClass("required")) {
				obj.options.callbackSuccess.call(obj, item, name);
			}
			// Support the onchange callback
			if (typeof itemOptions.onchange == "function") {
				itemOptions.onchange.call(obj, item, value);
			}
			else if (typeof itemOptions.onchange == "object") {
				// Delete all the sub entry from this
				if ($(this).data("nextElement")) {
					$(item).nextUntil($(this).data("nextElement")).remove();
				}
				// Save the value of the next element
				$(this).data("nextElement", $(item).next());
				// Then add a new element
				if (typeof itemOptions.onchange[value] === "object") {
					obj.create(item, itemOptions.onchange[value]);
				}
				else if (typeof itemOptions.onchange["__default__"] === "object") {
					obj.create(item, itemOptions.onchange["__default__"]);
				}
			}
		});
		// Insert the wrapper
		var item = this.options.wrapper.call(this, containerItem, itemOptions, itemName);
		$(item).addClass("irform-item");
		if (itemOptions.width) {
			$(item).css("width", itemOptions.width);
		}
		// Associate the name of the element with the element
		$(item).attr("data-irform", itemName);
		// Check if it has to be set has required
		if (itemOptions.required) {
			$(item).addClass("required");
		}
		// Set flags if needed
		if (itemOptions.disabled) {
			Irform.queue(nameHolder, function() {
				obj.options.disable.call(obj, true, $(this).parents(".irform-item:first"));
			});
		}
		// Save data to this element
		$(item).data("irform", itemOptions);
		// Paste it on the DOM
		($(container).hasClass("irform-item")) ? $(container).after(item) : $(container).append(item);
	}

	// Trigger change to all items
	for (var i in formDescription) {
		var itemName = formDescription[i].name;
		// Check the element
		var nameHolder = Irform.findNameHolder(this.container, itemName);
		// Trigger a value change to add new items if needed
		Irform.queue(nameHolder, function() {
			$(this).trigger("change");
		});
	}
};

/**
 * Submit the form
 */
Irform.prototype.submit = function (callback) {
	// Read teh values, and make sure everything is validated
	var values = this.get();
	if (values === false) {
		return;
	}
	// If a callback is passsed into argument, simply call it
	if (typeof callback === "function") {
		callback.call(this, values);
	}
	// If this is part of a form, submit the form
	else if (this.container.is("form")) {
		var form = $("<form>", {
			action: $(this.container).prop("action"),
			method: $(this.container).prop("method") || "POST",
			enctype: "multipart/form-data",
			style: "display: none;"
		});
		// Re-create the data
		var createDataRec = function(values, prefix) {
			var data = "";
			for (var name in values) {
				if (typeof values[name] === "object") {
					// Cannot send an emty array via post, then if the array is empty, simply do not send it
					data += createDataRec(values[name],
							((prefix) ? prefix + "[" + name + "]" : name));
				}
				else {
					// POST converts all types to string, so no need to make special cases
					data += "<input name=\""
							+ ((prefix) ? prefix + "[" + name + "]" : name)
							+ "\" value=\"" + (values[name] + "").replace(/"/g, '&quot;') + "\"/>";
				}
			}
			return data;
		};
		var data = createDataRec(values, "");
		$(form).html(data);
		// Note: file upload works only with POST and enctype="multipart/form-data"
		$(this.container).find("input[type=file]").each(function() {
			$(this).appendTo(form);
		});
		// Need to append to the DOM the form before submitting it (at least for IE & FF)
		$("body").append(form);
		form.submit();
	}
}

/**
 * Disable all elements of the form
 */
Irform.prototype.disable = function () {
	var disable_fct = this.options.disable;
	this.each(function(item) {
		disable_fct.call(this, true, item);
	});
};

/**
 * Enable all elements of the form
 */
Irform.prototype.enable = function () {
	var disable_fct = this.options.disable;
	this.each(function(item) {
		disable_fct.call(this, false, item);
	});
};

/**
 * Update the form description
 */
Irform.prototype.update = function (formDescription) {
	var values = this.get(function() {}, true);
	this.container.empty();
	this.formDescription = formDescription;
	this.create(this.container, formDescription);
	this.set(values);
};

/**
 * This function ignore a value or not
 */
Irform.prototype.ignore = function (item, isIgnore) {
	var itemOptions = $(item).data("irform");
	itemOptions.ignore = isIgnore;
	$(item).data("irform", itemOptions);
};

/**
 * Validate an element denoted by its item and its value
 */
Irform.prototype.validate = function (item, value) {
	var data = $(item).data("irform");
	// Called the function to test the validation
	if (typeof data.validate === "number") {
		return Irform.validate(data.validate, value);
	}
	else if (typeof data.validate === "string") {
		var re = new RegExp(data.validate, "g");
		return re.test(value);
	}
	else if (typeof data.validate === "function") {
		return data.validate.call(this, value, item);
	}
	// By default the validation passed
	return true;
};

/**
 * Loop through all items displayed on the current form
 */
Irform.prototype.each = function (callback) {
	var obj = this;
	this.container.find(".irform-item").each(function() {
		callback.call(obj, this);
	});
};

/**
 * Get the data from the form. It will in addition make sure that
 * all the required items are correctly filled.
 * In case of error, false will be returned.
 */
Irform.prototype.get = function (callback, force) {
	var isError = false;
	var errorList = [];
	var obj = this;
	// This option will force the reading of values
	if (typeof force === "undefined") {
		force = false;
	}
	// Generate the selection
	var selector = $();
	this.each(function(item) {
		selector = selector.add($("[name=" + $(item).attr("data-irform") + "]"));
	});
	var result = Irform.get(selector, function (key, value) {
		var item = $(this).parents(".irform-item:first");
		var data = item.data("irform");
		if (force) {
			return;
		}
		// If this attribute needs to be ignored
		if (data.ignore) {
			return null;
		}
		// If this is a required element with an empty value
		if (Irform.isEmpty(value) && item.hasClass("required")) {
			errorList.push({type: "required", item: item, name: key, options: data});
			isError = true;
		}
		// If this element needs to be validated
		if (data.validate && !obj.validate(item, value)) {
			errorList.push({type: "validation", item: item, name: key, options: data});
			isError = true;
		}
		// If there is a callback
		if (typeof callback === "function") {
			var result = callback.call(obj, item, key, value);
			if (typeof result !== "undefined") {
				return result;
			}
		}
	});
	if (isError) {
		this.options.callbackError.call(this, errorList);
		return false;
	}
	return result;
};

/**
 * Set the data of the form.
 */
Irform.prototype.set = function (values) {
	var nameUsed = {};
	do {
		var isValueSet = false;
		Irform.set(this.container, values, function(key) {
			// This value has already been set, continue
			if (typeof nameUsed[key] !== "undefined") {
				return null;
			}
			nameUsed[key] = true;
			isValueSet = true;
		});
	} while (isValueSet);
};

/**
 * Update the form description
 */
Irform.queue = function (item, action) {
	// Add an item to the queue or execute it
	if (typeof action === "function") {
		if ($(item).hasClass("irform-pending")) {
			var queue = $(item).data("irform-queue") || [];
			queue.push(action);
			$(item).data("irform-queue", queue);
		}
		else {
			action.call(item);
		}
	}
	// Execute the items from the queue
	else {
		var queue = $(item).data("irform-queue") || [];
		while (queue.length) {
			var action = queue.shift();
			action.call(item);
		}
		$(item).data("irform-queue", []);
	}
};

/**
 * Set the data to a form. This is a static function.
 */
Irform.set = function (selector, values, callback) {

	// Find direct elements only
	var list = $(selector).find("[name]").addBack("[name]").not("a").filter(function() {
		var nearestMatch = $(this).parent().closest("[name]");
		return nearestMatch.length == 0 || ($(selector).find(nearestMatch).length == 0 && $(selector).filter(nearestMatch).length == 0);
	});

	// Set their values
	$(list).each(function() {
		var key = $(this).prop("name") || $(this).attr("name");
		if (typeof values[key] !== "undefined") {
			var value = values[key];
			if (typeof callback === "function") {
				var result = callback.call(this, key, value);
				// Update the value or quit the function depending on the result
				if (typeof result !== "undefined") {
					// If result === null (special value) ignore the value
					if (result === null) {
						return;
					}
					value = result;
				}
			}
			// Support pending state
			Irform.queue(this, function() {
				if ($(this).hasClass("irform-container")) {
					Irform.set($(this).children(), value);
				}
				else if ($(this).is("input[type=checkbox]")) {
					$(this).prop("checked", value);
				}
				else {
					$(this).val(value);
				}
				$(this).trigger("change");
			});
		}
	});
}

/**
 * Get the data from a form. This is a static function.
 */
Irform.get = function (selector, callback) {
	var data = {};

	// Find direct elements only and remove anchors
	var list = $(selector).find("[name]").addBack("[name]").not("a").filter(function() {
		var nearestMatch = $(this).parent().closest("[name]");
		return nearestMatch.length == 0 || ($(selector).find(nearestMatch).length == 0 && $(selector).filter(nearestMatch).length == 0);
	});

	// Read their values
	$(list).each(function() {
		// Support the pending attribute
		if ($(this).hasClass("irform-pending")) {
			return;
		}
		var key = $(this).prop("name") || $(this).attr("name");
		// Set the new value
		if (key) {
			// Support containers and checkboxes
			var value = ($(this).hasClass("irform-container")) ?
					Irform.get($(this).children()) :
					($(this).is("input[type=checkbox]")) ?
					(($(this).is(":checked")) ? 1 : "") : $(this).val();
			// Call the callback if any
			if (typeof callback === "function") {
				var result = callback.call(this, key, value);
				if (typeof result !== "undefined") {
					// If result === null (special value) ignore the value
					if (result === null) {
						return;
					}
					value = result;
				}
			}
			data[key] = value;
		}
	});

	return data;
}

/**
 * Clear the form. This is a static function.
 */
Irform.clear = function (selector) {
	// Handle the irform array if any
	$(selector).find(".irform-array").trigger("array-empty");
	$(selector).find("[name]").val("").trigger("change");
};

/**
 * Check if a value is empty.
 * Objects or arrays are considered empty of all their members evaluates to empty.
 */
Irform.isEmpty = function (value) {
	if (typeof value == "object") {
		var empty = true;
		for (var i in value) {
			empty &= Irform.isEmpty(value[i]);
		}
		return empty;
	}
	return (value) ? false : true;
};/**
 * Convert an element into an array that can be used in a form
 */
(function($) {
	/**
	 * \brief Here goes the brief of irformArray.
	 *
	 * \alias jQuery.irformArray
	 *
	 * \param {String|Array} [action] The action to be passed to the function. If the instance is not created,
	 * \a action can be an \see Array that will be considered as the \a options.
	 * Otherwise \a action must be a \see String with the following value:
	 * \li \b create - Creates the object and associate it to a selector. \code $("#test").irformArray("create"); \endcode
	 *
	 * \param {Array} [options] The options to be passed to the object during its creation.
	 * See \see $.fn.irformArray.defaults a the complete list.
	 *
	 * \return {jQuery}
	 */
	$.fn.irformArray = function(arg, data) {
		// This is the returned value
		var retval;
		// Go through each objects
		$(this).each(function() {
			retval = $().irformArray.x.call(this, arg, data);
		});
		// Make it chainable, or return the value if any
		return (typeof retval === "undefined") ? $(this) : retval;
	};

	/**
	 * This function handles a single object.
	 * \private
	 */
	$.fn.irformArray.x = function(arg/*, data*/) {
		// Load the default options
		var options = $.fn.irformArray.defaults;

		// --- Deal with the actions / options ---
		// Set the default action
		var action = "create";
		// Deal with the action argument if it has been set
		if (typeof arg === "string") {
			action = arg;
		}
		// If the module is already created and the action is not create, load its options
		if (action != "create" && $(this).data("irformArray")) {
			options = $(this).data("irformArray");
		}
		// If the first argument is an object, this means options have
		// been passed to the function. Merge them recursively with the
		// default options.
		if (typeof arg === "object") {
			options = $.extend(true, {}, options, arg);
		}
		// Store the options to the module
		$(this).data("irformArray", options);

		// Handle the different actions
		switch (action) {
		// Create action
		case "create":
			$.fn.irformArray.create.call(this);
			break;
		};
	};

	/**
	 * Delete an item from the array
	 */
	$.fn.irformArray.deleteItem = function(item) {
		// Do nothing if this element is disabled
		if ($(this).prop("disabled") === true) {
			return;
		}
		$(item).remove();
	}

	/**
	 * Move Up/Previous an item from the array
	 */
	$.fn.irformArray.moveItemUp = function(item) {
		// Do nothing if this element is disabled
		if ($(this).prop("disabled") === true) {
			return;
		}
		var prev = $(item).prev(".irform-array-item");
		if (prev.length) {
			$(item).detach();
			prev.before(item);
		}
	}

	/**
	 * Move Down/Next an item from the array
	 */
	$.fn.irformArray.moveItemDown = function(item) {
		// Do nothing if this element is disabled
		if ($(this).prop("disabled") === true) {
			return;
		}
		var next = $(item).next(".irform-array-item");
		if (next.length) {
			$(item).detach();
			next.after(item);
		}
	}

	/**
	 * Add a new item
	 */
	$.fn.irformArray.add = function() {
		// The instanc eof the current object
		var obj = this;
		// Read the options
		var options = $(this).data("irformArray");

		var template = options.template;
		var item = $("<div>", {
			class: "irform-array-item"
		});
		if (options.inline) {
			$(item).addClass("inline");
			$(item).css("display", "inline-block");
		}

		// Delete button
		if (options.isDelete) {
			var del = $("<div>", {
				class: "irform-array-item-del"
			});
			$(del).html(options.delete)
			$(del).click(function() {
				$.fn.irformArray.deleteItem.call(obj, item);
			});
			$(item).append(del);
		}

		// Move (Up/Down) button
		if (options.isMove) {
			// Button Up
			var up = $("<div>", {
				class: "irform-array-item-up"
			});
			$(up).html(options.up);
			$(up).click(function() {
				$.fn.irformArray.moveItemUp.call(obj, item);
			});
			$(item).append(up);
			// Button Down
			var down = $("<div>", {
				class: "irform-array-item-down"
			});
			$(down).html(options.down);
			$(down).click(function() {
				$.fn.irformArray.moveItemDown.call(obj, item);
			});
			$(item).append(down);
		}

		var content = $("<div>", {
			class: "irform-array-item-content"
		});
		if (typeof template === "function") {
			$(content).html(template.call(obj, item));
		}
		else {
			$(content).html($(template).clone(true, true));
		}
		$(item).append(content);

		$(this).find(".irform-array-content:first").append(item);

		// Trigger the hook
		options.hookAdd.call(obj, item);
	}

	/**
	 * Add a new item
	 */
	$.fn.irformArray.clear = function() {
		$(this).find(".irform-array-content:first").empty();
	}

	$.fn.irformArray.create = function() {

		// The instanc eof the current object
		var obj = this;
		// Read the options
		var options = $(this).data("irformArray");

		// Container of the array
		$(obj).empty();
		$(obj).addClass("irform-array");
		$(obj).attr("name", options.name);

		// Create the content array
		var content = $("<div>", {
			class: "irform-array-content",
			style: (options.inline) ? "display:inline-block;" : ""
		});
		$(obj).append(content);

		// Set an event to add new items
		$(obj).on("array-add", function() {
			$.fn.irformArray.add.call(obj);
		});

		// Set an event to clear all items
		$(obj).on("array-empty", function() {
			$.fn.irformArray.clear.call(obj);
		});

		// Set the add button
		if (options.isAdd) {
			var add = $("<div>", {
				class: "irform-array-add",
				style: "display:inline-block;"
			});
			$(add).html(options.add);
			$(add).click(function() {
				// Do nothing if this element is disabled
				if ($(obj).prop("disabled") === true) {
					return;
				}
				$(obj).trigger("array-add");
			});
			$(obj).append(add);
		}
	}

	/**
	 * \brief Default options, can be overwritten. These options are used to customize the object.
	 * Change default values:
	 * \code $().irformArray.defaults.theme = "aqua"; \endcode
	 * \type Array
	 */
	$.fn.irformArray.defaults = {
		/**
		 * The name of the element, this must be set
		 */
		name: null,
		/**
		 * The template to be used for the item
		 */
		template: "<input class=\"irform\" type=\"text\" name=\"text\" />",
		/**
		 * Display the element items inline
		 */
		inline: false,
		/**
		 * If the delete button should be implemented
		 */
		isDelete: true,
		/**
		 * If the add button should be implemented
		 */
		isAdd: true,
		/**
		 * If the Up and Down buttons should be implemented
		 */
		isMove: true,
		/**
		 * HTML to be used for the delete button
		 */
		delete: "<button class=\"irform dock-left\" type=\"button\"><span class=\"icon-cross\"></span></button>",
		/**
		 * HTML to be used for the add button
		 */
		add: "<button class=\"irform\" type=\"button\"><span class=\"icon-plus\"></span> Add</button>",
		/**
		 * HTML to be used for the up button
		 */
		up: "<button class=\"irform dock-right\" type=\"button\"><span class=\"icon-arrow-up\"></span></button>",
		/**
		 * HTML to be used for the down button
		 */
		down: "<button class=\"irform dock-left dock-right\" type=\"button\"><span class=\"icon-arrow-down\"></span></button>",
		/**
		 * Hook called once an item has been added
		 * \param item
		 */
		hookAdd: function(/*item*/) {},
		/**
		 * Hook called once the element value is writen to it.
		 */
		hookValWrite: function(value) {
			return value;
		},
		/**
		 * Hook called once the element value is read.
		 */
		hookValRead: function(value) {
			return value;
		}
	};

	// Override the val function to handle this element
	var originalVal = $.fn.val;
	$.fn.val = function(value) {
		// Read
		if (!arguments.length) {
			if ($(this).hasClass("irform-array")) {
				var value = [];
				$(this).children(".irform-array-content:first").children(".irform-array-item").each(function(i) {
					value[i] = Irform.get(this);
				});
				var options = $(this).data("irformArray");
				return options.hookValRead.call(this, value);
			}
			// Callback the original function
			return originalVal.apply(this, arguments);
		}
		// Write
		// Make this variable local to pas it through the each function, seems to work only this way
		var v = value;
		$(this).each(function() {
			// Hack to make the variable visiable in this scope
			var value = v;
			if ($(this).hasClass("irform-array")) {
				var options = $(this).data("irformArray");
				// Callback used to update the value if needed
				value = options.hookValWrite.call(this, value);
				$(this).trigger("array-empty");
				for (var i in value) {
					$(this).trigger("array-add");
					var selector = $(this).children(".irform-array-content:first").children(".irform-array-item:last");
					Irform.set(selector, value[i]);
				}
			}
			else {
				// Callback the original function
				originalVal.call($(this), value);
			}
		});
		return this;
	};
})(jQuery);

// Add the module to Irform
Irform.defaultOptions.fields.array = function(name, options) {
	var div = $("<div>");
	$(div).irformArray({
		name: name,
		template: options.template
	});
	return div;
};(function($) {
	/**
	 * \brief Preset for keyword module
	 *
	 * \alias jQuery.irformArrayKeywords
	 */
	$.fn.irformArrayKeywords = function(options) {
		$(this).irformArray($.extend(true, $.fn.irformArrayKeywords.defaults, options));
	};

	/**
	 * \brief Default options, can be overwritten. These options are used to customize the object.
	 * Change default values:
	 * \code $().irformArrayKeywords.defaults.theme = "aqua"; \endcode
	 * \type Array
	 */
	$.fn.irformArrayKeywords.defaults = {
		template: "<span>" +
						"<span class=\"irform-array-keywords-edit\">" +
							"<button class=\"irform-array-item-left irform dock-right\" type=\"button\"><span class=\"icon-arrow-left\"></span></button>" +
							"<input type=\"text\" class=\"irform inline dock-left dock-right\" name=\"keyword\"/>" +
							"<button class=\"irform-array-item-right irform dock-left\" type=\"button\"><span class=\"icon-arrow-right\"></span></button>" +
						"</span>" +
						"<span class=\"irform-array-keywords-tag irform border inline clickable\" style=\"display: none;\">" +
							"<span></span>" +
							"<span class=\"irform-array-item-del\" style=\"margin-left: 10px;\"><span class=\"icon-cross\"></span></span>" +
						"</span>" +
					"</span>",
		isMove: false,
		isDelete: false,
		inline: true,
		hookAdd: function(item) {
			var obj = this;
			var edit = $(item).find(".irform-array-keywords-edit");
			var tag = $(item).find(".irform-array-keywords-tag");
			$(item).find("input").on("blur", function() {
				var value = $(this).val();
				// If the value is empty, then delete the item
				if (!value) {
					$().irformArray.deleteItem.call(obj, item);
				}
				// Else hide the input and show the tag
				else {
					$(edit).hide();
					// Show and update the tag
					$(tag).find("span:first").text(value);
					$(tag).show();
				}
			}).on("change", function() {
				$(this).trigger("blur");
			});

			$(tag).click(function() {
				// Do nothing if this element is disabled
				if ($(obj).prop("disabled") === true) {
					return;
				}
				// Once a new element is added, make sure all the others are in non-edit mode
				$(obj).find(".irform-array-item").not(item).find("input").trigger("blur");
				$(this).hide();
				// Show and update the tag
				$(edit).show();
			});

			// Allocate the various events
			$(item).find(".irform-array-item-left").click(function() {
				$().irformArray.moveItemUp.call(obj, item);
			});
			$(item).find(".irform-array-item-right").click(function() {
				$().irformArray.moveItemDown.call(obj, item);
			});
			$(item).find(".irform-array-item-del").click(function() {
				$().irformArray.deleteItem.call(obj, item);
			});

			// Once a new element is added, make sure all the others are in non-edit mode
			$(this).find(".irform-array-item").not(item).find("input").trigger("blur");
		},
		/**
		 * Hook called once the element value is writen to it.
		 */
		hookValWrite: function(value) {
			value = value.split(",");
			var newVal = [];
			for (var i in value) {
				// Clean the value
				var v = value[i].trim();
				if (v) {
					newVal.push({
						keyword: v
					});
				}
			}
			return newVal;
		},
		/**
		 * Hook called once the element value is read.
		 */
		hookValRead: function(value) {
			for (var i in value) {
				value[i] = value[i]["keyword"];
			}
			return value.join(", ");
		}
	};
})(jQuery);

/* Add the module to Irform */
Irform.defaultOptions.fields.keywords = function(name) {
	var div = $("<div>");
	div.irformArrayKeywords({name: name});
	return div;
};
/**
 * Convert an element into an array that can be used in a form
 */
(function($) {
	/**
	 * \brief Support tinymce API v4.3 and above\n
	 * 
	 * \alias jQuery.irformTinymce
	 *
	 * \param {String|Array} [action] The action to be passed to the function. If the instance is not created,
	 * \a action can be an \see Array that will be considered as the \a options.
	 * Otherwise \a action must be a \see String with the following value:
	 * \li \b create - Creates the object and associate it to a selector. \code $("#test").irformTinymce("create"); \endcode
	 *
	 * \param {Array} [options] The options to be passed to the object during its creation.
	 * See \see $.fn.irformTinymce.defaults a the complete list.
	 *
	 * \return {jQuery}
	 */
	$.fn.irformTinymce = function(arg, data) {
		/* This is the returned value */
		var retval;
		/* Go through each objects */
		$(this).each(function() {
			retval = $().irformTinymce.x.call(this, arg, data);
		});
		/* Make it chainable, or return the value if any  */
		return (typeof retval === "undefined") ? $(this) : retval;
	};

	/**
	 * This function handles a single object.
	 * \private
	 * \param arg
	 * \param data
	 */
	$.fn.irformTinymce.x = function(arg/*, data*/) {
		/* Load the default options */
		var options = $.fn.irformTinymce.defaults;

		/* --- Deal with the actions / options --- */
		/* Set the default action */
		var action = "create";
		/* Deal with the action argument if it has been set */
		if (typeof arg === "string") {
			action = arg;
		}
		/* If the module is already created and the action is not create, load its options */
		if (action != "create" && $(this).data("irformTinymce")) {
			options = $(this).data("irformTinymce");
		}
		/* If the first argument is an object, this means options have
		 * been passed to the function. Merge them recursively with the
		 * default options.
		 */
		if (typeof arg === "object") {
			options = $.extend(true, {}, options, arg);
		}
		/* Store the options to the module */
		$(this).data("irformTinymce", options);

		/* Handle the different actions */
		switch (action) {
		/* Create action */
		case "create":
			$.fn.irformTinymce.create.call(this);
			break;
		};
	};

	$.fn.irformTinymce.create = function() {

		/* Id counter, need to be initialized as a static value */
		if (typeof $.fn.irformTinymce.create.id === "undefined") {
			$.fn.irformTinymce.create.id = 0;
		}

		/* The instance of the current object */
		var obj = this;
		/* Read the options */
		var options = $(this).data("irformTinymce");
		/* Add the class */
		$(this).addClass("irform-tinymce irform-pending");
		$(this).attr("name", options.name);

		/* Generate an id if it does not exists */
		if (!$(this).attr("id")) {
			$(this).attr("id", "irform-tinymce-id" + ($.fn.irformTinymce.create.id++));
		}

		// Update the options
		var tinymce_o = $.extend(true, {
			selector: "#" + $(this).attr("id"),
			// Do not convert URLs
			convert_urls: false,
			// To support the custom browser function (field_name, url, type, win)
			file_browser_callback: function (field_name, url, type) {
				/* Call the browser */
				options.callbackBrowser.call(obj, type, function(path) {
					$("#" + field_name).val(path);
				});
			},
			// Call this function once the editor is ready
			init_instance_callback: function() {
				// Call the callback to tell that the editor is ready
				options.callbackIsReady.call(obj);
			},
			setup : function(editor) {
				editor.on('change', function() {
					$(obj).trigger("change");
				});
			}
		}, options.tinymce);

		/* Set the disable and enable events */
		$(this).on("disable", function() {
			tinyMCE.get($(this).prop("id")).setMode("readonly");
		});
		$(this).on("enable", function() {
			tinyMCE.get($(this).prop("id")).setMode("design");
		});

		/* Add the CSS file and class if any */
		if (options.css) {
			tinymce_o["content_css"] = options.css;
		}
		if (options.cssClass) {
			tinymce_o["body_class"] = options.cssClass;
		}

		/* If the document has a base path */
		if (options.baseURL) {
			tinymce_o.document_base_url = options.baseURL;
		}
		/* Set the base URL */
		if (options.tinymceBase) {
			tinymce.baseURL = options.tinymceBase;
		}
		
		/* Hack to let tinymce look for the minimzed versions of the plugin */
		tinymce.suffix = ".min";
		/* creates the module */
		setTimeout(function() {
			tinymce.init(tinymce_o);
		}, 100);
	}

	/**
	 * \brief Default options, can be overwritten. These options are used to customize the object.
	 * Change default values:
	 * \code $().irformTinymce.defaults.theme = "aqua"; \endcode
	 * \type Array
	 */
	$.fn.irformTinymce.defaults = {
		/**
		 * The name of the element, this must be set
		 */
		name: null,
		/**
		 * Base directory where tinymce is located
		 */
		tinymceBase: null,
		/**
		 * Applies a base URL to the editor, so that ressources (images...) will be relative to this URL.
		 */
		baseURL: null,
		/**
		 * A path to a CSS file that will be used for the style sheet of the document
		 */
		css: null,
		/**
		 * The CSS class that will be used for the style sheet of the document
		 */
		cssClass: null,
		/**
		 * Callback to tell notfity once the editor is ready
		 */
		callbackIsReady: function() {},
		/**
		 * Callback browser.
		 * \param type can be file, image or flash
		 * \param callback the function to be called once the value is available. This function takes into argument
		 * the path
		 */
		callbackBrowser: function() {},
		/**
		 * Default options to be passed to tinymce
		 */
		tinymce: {
			/* Hide the menu bar */
			menubar: false,
			statusbar: false,
			autoresize_max_height: ($(window).innerHeight() - 100), //< For the toolbar
			toolbar: "undo redo | styleselect | bold italic | forecolor | removeformat | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | code | fullscreen",
			plugins: [ "advlist autolink autoresize link image lists charmap print preview hr anchor pagebreak spellchecker",
				"searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
				"save table contextmenu directionality emoticons template paste textcolor"
			]
		}
	};

	/* Override the val function to handle this element */
	var originalVal = $.fn.val;
	$.fn.val = function(value) {
		if (arguments.length) {
			$(this).each(function() {
				if ($(this).hasClass("irform-tinymce")) {
					return tinyMCE.get($(this).prop("id")).setContent(value);
				}
				return originalVal.call(this, value);
			});
			return $(this);
		}
		if ($(this).hasClass("irform-tinymce")) {
			return tinyMCE.get($(this).prop("id")).getContent();
		}
		return originalVal.apply(this, arguments);
	};
})(jQuery);

/* Add the module to Irform */
Irform.defaultOptions.fields.htmleditor = function(name, options, callback) {
	var div = $("<div>");
	$(div).irformTinymce($.extend(true, {}, {
		name: name,
		callbackIsReady: function() {
			callback.call(this);
		}
	}, options["options"]));
	return div;
};
/**
 * Convert an element into a file uploader, selector or anything to select a file or a link
 */
(function($) {
	/**
	 * \brief .\n
	 * 
	 * \alias jQuery.irformFile
	 *
	 * \param {String|Array} [action] The action to be passed to the function. If the instance is not created,
	 * \a action can be an \see Array that will be considered as the \a options.
	 * Otherwise \a action must be a \see String with the following value:
	 * \li \b create - Creates the object and associate it to a selector. \code $("#test").irformFile("create"); \endcode
	 *
	 * \param {Array} [options] The options to be passed to the object during its creation.
	 * See \see $.fn.irformFile.defaults a the complete list.
	 *
	 * \return {jQuery}
	 */
	$.fn.irformFile = function(arg, data) {
		/* This is the returned value */
		var retval;
		/* Go through each objects */
		$(this).each(function() {
			retval = $().irformFile.x.call(this, arg, data);
		});
		/* Make it chainable, or return the value if any  */
		return (typeof retval === "undefined") ? $(this) : retval;
	};

	/**
	 * This function handles a single object.
	 * \private
	 * \param arg
	 * \param data
	 */
	$.fn.irformFile.x = function(arg) {
		/* Load the default options */
		var options = $.fn.irformFile.defaults;

		/* --- Deal with the actions / options --- */
		/* Set the default action */
		var action = "create";
		/* Deal with the action argument if it has been set */
		if (typeof arg === "string") {
			action = arg;
		}
		/* If the module is already created and the action is not create, load its options */
		if (action != "create" && $(this).data("irformFile")) {
			options = $(this).data("irformFile");
		}
		/* If the first argument is an object, this means options have
		 * been passed to the function. Merge them recursively with the
		 * default options.
		 */
		if (typeof arg === "object") {
			options = $.extend(true, {}, options, arg);
		}
		/* Store the options to the module */
		$(this).data("irformFile", options);

		/* Handle the different actions */
		switch (action) {
		/* Create action */
		case "create":
			$.fn.irformFile.create.call(this);
			break;
		};
	};


	$.fn.irformFile.create = function() {
		/* Read the options */
		var options = $(this).data("irformFile");
		/* Reference to the main object */
		var obj = this;
		/* Create the container */
		var container = $("<div>", {
			class: "irform-group"
		});
		/* Create the input field */
		var input = $("<input>", {
			name: options.name,
			class: "irform"
		});
		$(container).append(input);
		/* Add the button(s) */
		for (var i in options.buttonList) {
			var preset = options.presets[options.buttonList[i]];
			var button = $("<button>", {
				class: "irform",
				type: "button"
			});
			$(button).text(preset.caption);
			$(button).data("irformFile", preset);
			$(button).click(function() {
				var callback = function(value) {
					$(input).val(value);
				};
				var options = $(obj).data("irformFile");
				var preset = $(this).data("irformFile");
				// Cleanup the previous action if any
				if (options.clean) {
					options.clean.call(obj, callback);
				}
				// Save the new clean function if any
				options.clean = (preset.clean) ? preset.clean : null;
				$(obj).data("irformFile", options);

				// Call the action
				preset.action.call(obj, callback, preset.options);
			});
			$(container).append(button);
		}
		$(this).append(container);
	}

	/**
	 * \brief Default options, can be overwritten. These options are used to customize the object.
	 * Change default values:
	 * \code $().irformFile.defaults.theme = "aqua"; \endcode
	 * \type Array
	 */
	$.fn.irformFile.defaults = {
		/**
		 * The name of the element, this must be set
		 */
		name: "file",
		/**
		 * Button types
		 */
		buttonList: ["upload"],
		/**
		 * Overwrite the type of file supported, a string supporting regexpr format.
		 */
		fileType: null,
		/**
		 * List of presets
		 */
		presets: {
			"upload": {
				caption: "Upload",
				action: function(callback, presetOptions) {
					var options = $(this).data("irformFile");
					var file = $("<input>", {
						type: "file",
						class: "irform-file-upload",
						name: options.name + ((presetOptions.mode == "multi") ? "[]" : ""),
						multiple: (presetOptions.mode == "multi") ? true : false
					});
					$(file).on("change", function() {
						var name = "";
						if (typeof this.files === "object") {
							for (var i = 0; i<this.files.length; i++) {
								name += ((name) ? " " : "") + "\"" + this.files.item(i).name + "\"";
							}
						}
						callback(name);
					});
					$(file).hide();
					$(this).append(file);
					$(file).trigger("click");
				},
				options: {mode: "multi"}, // or single
				clean: function(callback) {
					// Reset the value and destroy the previous upload if any
					callback("");
					$(this).find(".irform-file-upload").remove();
				}
			}
		}
	};

})(jQuery);

/* Add the module to Irform */
Irform.defaultOptions.fields.file = function(name, options) {
	var div = $("<div>");
	var o = {name: name};
	if (typeof options["options"] === "object") {
		o = $.extend(true, o, options["options"]);
	}
	$(div).irformFile(o);
	return div;
};(function($) {
	/**
	 * \brief .\n
	 * 
	 * \alias jQuery.irformModal
	 *
	 * \param {String|Array} [action] The action to be passed to the function. If the instance is not created,
	 * \a action can be an \see Array that will be considered as the \a options.
	 * Otherwise \a action must be a \see String with the following value:
	 * \li \b create - Creates the object and associate it to a selector. \code $("#test").irformModal("create"); \endcode
	 *
	 * \param {Array} [options] The options to be passed to the object during its creation.
	 * See \see $.fn.irformModal.defaults a the complete list.
	 *
	 * \return {jQuery}
	 */
	$.fn.irformModal = function(arg, data) {
		/* This is the returned value */
		var retval;
		/* Go through each objects */
		$(this).each(function() {
			retval = $().irformModal.x.call(this, arg, data);
		});
		/* Make it chainable, or return the value if any  */
		return (typeof retval === "undefined") ? $(this) : retval;
	};

	/**
	 * This function handles a single object.
	 * \private
	 * \param arg
	 * \param data
	 */
	$.fn.irformModal.x = function(arg) {
		/* Load the default options */
		var options = $.fn.irformModal.defaults;

		/* --- Deal with the actions / options --- */
		/* Set the default action */
		var action = "create";
		/* Deal with the action argument if it has been set */
		if (typeof arg === "string") {
			action = arg;
		}
		/* If the module is already created and the action is not create, load its options */
		if (action != "create" && $(this).data("irformModal")) {
			options = $(this).data("irformModal");
		}
		/* If the first argument is an object, this means options have
		 * been passed to the function. Merge them recursively with the
		 * default options.
		 */
		if (typeof arg === "object") {
			options = $.extend(true, {}, options, arg);
		}
		/* Store the options to the module */
		$(this).data("irformModal", options);

		/* Handle the different actions */
		switch (action) {
		/* Create action */
		case "create":
			$.fn.irformModal.create.call(this);
			break;
		};
	};

	/**
	 * \brief Default options, can be overwritten.
	 */
	$.fn.irformModal.defaults = {
		zIndex: 99999
	};

	$.fn.irformModal.create = function() {
		// Options of the current irformModal
		var options = $(this).data("irformModal");

		var modal = $("<div>", {
			class: "irform-modal-content"
		});
		$(modal).append(this);
		$(modal).click(function(e) {
			e.stopPropagation();
		});

		var container = $("<div>", {
			class: "irform-modal"
		});
		$(container).css({
			top: 0,
			left: 0,
			width: "100%",
			height: "100%",
			position: "fixed",
			zIndex: options.zIndex
		});
		$(container).append(modal);

		// Save the container instance
		options.container = container;
		options.overflow = $("body").css("overflow");
		$(this).data("irformModal", options);

		// Remove the element on click
		var obj = this;
		$(container).click(function() {
			$.fn.irformModal.close.call(obj);
		});

		// Append the modal
		$("body").append(container);
		$("body").css("overflow", "hidden");
	};

	/**
	 * This function closes the dialog
	 */
	$.fn.irformModal.close = function () {
		// Options of the current irformModal
		var options = $(this).data("irformModal");
		$(options.container).remove();
		$("body").css("overflow", options.overflow);
	}

})(jQuery);
irRequire("IrexplorerDialog", function() {
	/**
	 * This file is use to map the irform with the Irexplorer
	 */
	var IrformFileIrexplorer = function(callback, presetOptions) {
		if (typeof options === "undefined") {
			options = {};
		}
		// Update the file type if set i the global options
		var options = $(this).data("irformFile");
		if (options && options.fileType) {
			presetOptions = $.extend(true, presetOptions, {irexplorer: {showType: ["folder", options.fileType]}});
		}

		// Launch the explorer
		new IrexplorerDialog($.extend(true, {}, {
				relative: true,
				onValidate: function(path) {
					callback(path);
				}
			}, presetOptions));
	};

	/* Override default options */
	$().irformTinymce.defaults.callbackBrowser = function(type, callback) {
		// Change mode
		var mode = "file";
		switch (type) {
		case "image":
			mode = "image";
			break;
		}
		IrformFileIrexplorer(callback, {mode: mode});
	};
	/* Add support for the irform file */
	$().irformFile.defaults.buttonList.push("browse");
	$().irformFile.defaults.presets["browse"] = {
		caption: "Browse",
		action: IrformFileIrexplorer
	};
	$().irformFile.defaults.presets["image"] = {
		caption: "Select Image",
		options: {mode: "image"},
		action: IrformFileIrexplorer
	};
	$().irformFile.defaults.presets["directory"] = {
		caption: "Select Directory",
		options: {mode: "directory"},
		action: IrformFileIrexplorer
	};
});// Update the form layout
Irform.defaultOptions.hookInit = function() {
	// Make sure the container is a form, if not create one and update the container
	if (!$(this.container).is("form")) {
		var form = $("<form>");
		$(this.container).append(form);
		this.container = form;
	}
	$(this.container).addClass("form-horizontal");
}
Irform.defaultOptions.wrapper = function(elt, options, name) {
	var wrapper = $("<div>", {
		class: "form-group",
		style: "clear:both;"
	});
	var a = $("<a>", {
		name: "irform-anchor-" + name
	});
	var label = $("<label>", {
		class: "col-sm-2 control-label"
	});
	$(label).text(options.caption);
	var col = $("<fieldset>", {
		class: "col-sm-10"
	});
	if ($(elt).is("input,textarea,select")) {
		$(elt).addClass("form-control");
	}
	if ($(elt).is("button")) {
		$(elt).addClass("btn btn-default");
	}
	$(col).append(elt);
	if (typeof options.description !== "undefined") {
		var description = $("<small>", {
			class: "form-text text-muted"
		});
		$(description).text(options.description);
		$(col).append(description);
	}
	$(wrapper).append(a);
	$(wrapper).append(label);
	$(wrapper).append(col);

	return wrapper;
};
Irform.defaultOptions.callbackError = function(errorList) {
	var msg = "";
	for (var i in errorList) {
		switch (errorList[i]["type"]) {
		case "required":
			msg += "<div class=\"irform-id-" + errorList[i]["name"] + "\"><a class=\"alert-link\" href=\"#" + errorList[i]["name"] + "\">" + errorList[i]["options"].caption + "</a> is required</div>";
			break;
		case "validation":
			msg += "<div class=\"irform-id-" + errorList[i]["name"] + "\"><a class=\"alert-link\" href=\"#" + errorList[i]["name"] + "\">" + errorList[i]["options"].caption + "</a> does not validate</div>";
			break;
		}
		$(errorList[i]["item"]).removeClass("has-success").addClass("has-error");
	}
	$(this.container).find(".irform-error").remove();
	$(this.container).prepend("<div class=\"bs-component irform-error\"><div class=\"alert alert-danger alert-dismissible\" role=\"alert\"><button type=\"button\" class=\"close\" data-dismiss=\"alert\">x</button><div class=\"irform-message\">" + msg + "</div></div></div>");
};
Irform.defaultOptions.callbackSuccess = function(item, name) {
	$(item).removeClass("has-error").addClass("has-success");
	// Update main message
	var error = $(this.container).find(".irform-error");
	$(error).find(".irform-message .irform-id-" + name).remove();
	if (!$(error).find(".irform-message").text()) {
		$(error).remove();
	}
};
/**
 * Called once an item needs to be disabled
 */
var IrformBootstrapSave = Irform.defaultOptions.disable;
Irform.defaultOptions.disable = function(isDisabled, elt) {
	IrformBootstrapSave.call(this, isDisabled, elt);
	$(elt).find("fieldset").prop("disabled", isDisabled);
};

/* Update the plugins */
(function($) {
	$.fn.irformArray.defaults.template = "<input type=\"text\" name=\"text\" class=\"form-control\" />";
	$.fn.irformArray.defaults.add = "<button type=\"button\" class=\"btn btn-info\"><span class=\"glyphicon glyphicon-plus\" aria-hidden=\"true\"></span> Add</button>";	
	$.fn.irformArray.defaults.delete = "<button type=\"button\" class=\"btn btn-default \"><span class=\"glyphicon glyphicon-remove\" aria-hidden=\"true\"></span>&nbsp;</button>";	
	$.fn.irformArray.defaults.up = "<button type=\"button\" class=\"btn btn-default \"><span class=\"glyphicon glyphicon-arrow-up\" aria-hidden=\"true\"></span>&nbsp;</button>";	
	$.fn.irformArray.defaults.down = "<button type=\"button\" class=\"btn btn-default \"><span class=\"glyphicon glyphicon-arrow-down\" aria-hidden=\"true\"></span>&nbsp;</button>";	
	$.fn.irformArray.defaults.hookAdd = function(item) {
		$(item).css({
			display: "flex",
			flexFlow: "row nowrap",
			marginBottom: "5px"
		});
		$(item).find(".irform-array-item-content").css({
			width: "100%",
			flexShrink: 1,
			order: 1,
			marginRight: "5px"
		});
		$(item).find(".irform-array-item-up").css({
			flexShrink: 0,
			order: 2
		});
		$(item).find(".irform-array-item-down").css({
			flexShrink: 0,
			order: 3
		});
		$(item).find(".irform-array-item-del").css({
			flexShrink: 0,
			order: 4
		});
	};
	$.fn.irformArrayKeywords.defaults.template = "<span style=\"margin-right: 10px;\">" +
						"<span class=\"irform-array-keywords-edit\">" +
							"<span class=\"irform-array-item-left glyphicon glyphicon-triangle-left\" aria-hidden=\"true\"></span>" +
							"<form class=\"form-inline\" style=\"display: inline-block;\"><input type=\"text\" class=\"form-control\" name=\"keyword\"/></form>" +
							"<span class=\"irform-array-item-right glyphicon glyphicon-triangle-right\" aria-hidden=\"true\"></span>" +
						"</span>" +
						"<button type=\"button\" class=\"irform-array-keywords-tag btn btn-default\" style=\"display: none;\">" +
							"<span></span>" +
							"<span class=\"irform-array-item-del glyphicon glyphicon-remove\" style=\"margin-left: 10px;\" aria-hidden=\"true\"></span>" +
						"</button>" +
					"</span>";
	$.fn.irformFile.create = function() {
		// Read the options
		var options = $(this).data("irformFile");
		// Reference to the main object
		var obj = this;
		// Create the container
		var container = $("<div>", {
			class: "input-group"
		});
		// Create the input field
		var input = $("<input>", {
			name: options.name,
			type: "text",
			class: "form-control"
		});
		$(container).append(input);
		// Create the button addon
		var addon = $("<div>", {
			class: "input-group-btn"
		});
		// Add the button
		var button = $("<button>", {
			type: "button",
			class: "btn btn-default dropdown-toggle"
		});
		$(button).attr("data-toggle", "dropdown");
		if (options.buttonList.length > 1) {
			$(button).html("<span class=\"glyphicon glyphicon-folder-open\" aria-hidden=\"true\"></span>&nbsp;&nbsp;<span class=\"caret\"></span>");
		}
		else {
			var preset = options.presets[options.buttonList[0]];
			$(button).text(preset.caption);
			$(button).click(function() {
				preset.action.call(obj, function(value) {
					$(input).val(value);
				}, preset.options);
			});
		}
		$(addon).append(button);
		if (options.buttonList.length > 1) {
			// Add the list
			var ul = $("<ul>", {
				class: "dropdown-menu"
			});
			// Add the button(s)
			for (var i in options.buttonList) {
				var preset = options.presets[options.buttonList[i]];
				var li = $("<li>");
				var a = $("<a>");
				$(a).text(preset.caption);
				$(a).click(function() {
					preset.action.call(obj, function(value) {
						$(input).val(value);
					}, preset.options);
				});
				$(li).append(a);
				$(ul).append(li);
			}
			$(addon).append(ul);
		}
		$(container).append(addon);
		$(this).html(container);
	};
})(jQuery);
