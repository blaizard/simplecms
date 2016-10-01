var form = null;

/**
 * Open the file browser and select which address to go to
 */
function browseAndGo() {
	new IrexplorerDialog({
		relative: false,
		onValidate: function(path) {
			document.location.href = "?admin_path=" + encodeURI(path);
		}
	});
}

/**
 * Go to the address set in the main input pat box
 */
function go() {
	var path = $("#admin-path").val();
	document.location.href = "?admin_path=" + encodeURI(path);
}

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
	var item = $(this).parents(".irform-field:first");
	modal("<div class=\"alert alert-warning\" role=\"alert\"><strong>Warning!</strong> By unlocking this field, you will overwrite its value locally and hence any update will not be inherited from its parent values.</div>", function() {
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
	mainFormDescription[result["name"]] = {
		caption: result["name"],
		type: result["type"]
	};

	mainForm.update(mainFormDescription);
}

function addElement()
{
	/* Empty the content of the modal */
	var container = $("#modalNewField").find("fieldset:first");
	$(container).empty();
	/* Generate the form */
	form = new Irform(container, {
		name: {caption: "Name*", required: true, validate: Irform.CHAR_A_Z, placeholder: "Name", description: "Unique identifier that identifies this field, it should contain only a-z characters with no spaces.", type: "input"},
		type: {caption: "Type*", type: "select", description: "The type of field that will be created", select: {
				input: "Input",
				textarea: "Textarea",
				keywords: "Keywords",
				select: "Select",
				htmleditor: "HTML Editor"
			},
			onchange: {
				input: {
					placeholder: {caption: "Placeholder", type: "input", placeholder: "Placeholder", description: "Short hint that describes the expected value"}
				},
				select: {
					select: {caption: "Data", type: "array", template: "<input type=\"text\" placeholder=\"Name\" name=\"name\"/><input type=\"text\" placeholder=\"Caption\" name=\"caption\"/>"}
				},
				htmleditor: {
					css: {caption: "CSS", type: "input"},
					cssclass: {caption: "Class", type: "input"}
				}
			}
		},
		description: {caption: "Description", type: "textarea", placeholder: "Description", description: "Description of the purpose of this field"}
	});
	/* Show the modal */
	$("#modalNewField").modal();
}

/* Update the submit button */
function submitContent()
{
	var result = mainForm.get(function(item, key, value) {
		var args = $(item).data("irform");
		/* Some items should be removed if they are present */
		for (var id in {"onchange":1, "disabled":1, "ignore":1, "validate":1, "options":1}) {
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

function clearContent()
{
	Irform.clear("#ircms-admin-content");
}
