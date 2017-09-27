function ircms_layout_toggle_menu()
{
	$("div.ircms_menu").slideToggle();
}

function ircms_layout_show_menu()
{
	$("div.ircms_menu").show();
}

function ircms_layout_hide_menu()
{
	$("div.ircms_menu").hide();
}

/**
 * Open the file browser and select which address to go to
 */
function browseAndGo() {
	new IrexplorerDialog({
		mode: "directory",
		relative: false,
		onValidate: function(path) {
			document.location.href = "?admin_path=" + encodeURI(path);
		}
	});
}