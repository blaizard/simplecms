var form = null;

/**
 * Creates a generic modal form
 */
function modal(content, callbackOk) {
	$("#modalGP .modal-body").html(content);
	$("#modalGP").modal();
	$("#modalGP .ircms-modal-ok").off("click");
	$("#modalGP .ircms-modal-ok").on("click", callbackOk);
}

/**
 * Unlock an item from the field list
 */
function unlock() {
	var item = $(this).parents(".irform-item:first");
	modal("<div class=\"alert alert-warning\" role=\"alert\"><strong>Warning!</strong> By unlocking this item, you will overwrite its value locally and hence any update will not be inherited from its parent values.</div>", function() {
		$(item).find("fieldset.col-sm-9:first").removeClass("col-sm-9").addClass("col-sm-10");
		$(item).find("div.col-sm-1").remove();
		mainForm.options.disable.call(mainForm, false, item);
		mainForm.ignore(item, false);
	});
}

function createrElement()
{
	var result = form.get();
	if (result === false) {
		return;
	}
	$("#modalNewField").modal("hide");

	/* Update the main form */
	mainFormDescription.push(result);

	mainForm.update(mainFormDescription);
}

function addElement()
{
	/* Empty the content of the modal */
	var container = $("#modalNewField").find("fieldset:first");
	$(container).empty();
	/* Generate the form */
	form = new Irform(container, [
		{name: "name", caption: "Name*", required: true, validate: Irform.CHAR_A_Z, placeholder: "Name", description: "Unique identifier that identifies this item, it should contain only a-z characters with no spaces.", type: "input"},
		{name: "type", caption: "Type*", type: "select", description: "The type of field that will be created", select: {
				input: "Input",
				textarea: "Textarea",
				keywords: "Keywords",
				select: "Select",
				filelist: "File List",
				menulinks: "Menu Links",
				htmleditor: "HTML Editor"
			},
			onchange: {
				input: [
					{name: "placeholder", caption: "Placeholder", type: "input", placeholder: "Placeholder", description: "Short hint that describes the expected value"}
				],
				select: [
					{name: "select", caption: "Data", type: "array", template: "<input type=\"text\" placeholder=\"Name\" name=\"name\"/><input type=\"text\" placeholder=\"Caption\" name=\"caption\"/>"}
				],
				htmleditor: [
					{name: "css", caption: "CSS", type: "file", options: { buttonList: ["browse"], fileType: "css" } },
					{name: "cssClass", caption: "Class", type: "input"}
				]
			}
		},
		{name: "description", caption: "Description", type: "textarea", placeholder: "Description", description: "Description of the purpose of this item"}
	]);
	/* Show the modal */
	$("#modalNewField").modal();
}

/* Update the submit button */
function submitContent()
{
	var result = mainForm.get(function(item, key, value) {
		var args = $(item).data("irform");
		/* Some items should be removed if they are present */
		for (var id in {"name":1, "onchange":1, "disabled":1, "ignore":1, "validate":1, "options":1}) {
			if (typeof args[id] !== "undefined") {
				delete args[id];
			}
		}
		/* Return the new value */
		return {
			value: value,
			args: args
		};
	});
	new Ircom("/admin/api.php?type=content&savepath=" + encodeURI(adminPath), result, {
		onError: function(message, options) {
			$(".ircms-message").empty();
			$(".ircms-message").html("<div class=\"alert alert-danger\" role=\"alert\">" + message + "</div>");
		},
		onSuccess: function(data, options) {
			var d = new Date();
			var date_str = d.getHours() + ":" + d.getMinutes() + ":" + d.getSeconds();
			$(".ircms-message").empty();
			$(".ircms-message").html("<div class=\"alert alert-success\" role=\"alert\">Saved at " + date_str + "</div>");
		}
	});
	console.log(result);
}

/* Add the menu links module to Irform */
Irform.defaultOptions.fields.menulinks = function(name) {
	var div = document.createElement("div");
	$(div).irformArray({
		name: name,
		template: function() {
			var container = document.createElement("div");
			/* Left part */
			var container_text = document.createElement("div");
			$(container_text).addClass("col-sm-6");
			var input = document.createElement("input");
			$(input).addClass("form-control");
			$(input).prop("name", "text");
			$(input).prop("type", "text");
			$(input).prop("placeholder", "Menu Text");
			$(container_text).append(input);
			/* Right part */
			var container_link = document.createElement("div");
			$(container_link).addClass("col-sm-6");
			$(container_link).irformFile({
				name: "link"
			});

			/* Append to the main container */
			$(container).append(container_text);
			$(container).append(container_link);

			return container;
		}
	});
	return div;
};

/* Add the file list module to Irform */
Irform.defaultOptions.fields.filelist = function(name) {
	var div = document.createElement("div");
	$(div).irformArray({
		name: name,
		template: function() {
			var container = document.createElement("div");
			$(container).irformFile({
				name: "link"
			});
			return container;
		}
	});
	return div;
};