/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2021 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * Collection of all static event bindings for the server application. Includes the document ready call
 */

// show a help text, use it by <sup class='eventitem' id='showhelptext_xyz'>&#9432</sup> (character should show as 🛈)
function _showHelptext(reference) {
	var helptext_url = "../helpdocs/" + reference + ".html";
	var helptext = "<h3>Oops</h3><p>Der Hilfetext zum Thema " + reference + " konnte unter " + helptext_url + " leider nicht gefunden werden.</p>";
	jQuery.get(helptext_url, function(data) {
		helptext = data;
		cModal.showHtml(helptext);
	});
}

// show a reference record, used in ../pages/db_audit.php 
function _showRecord(reference) {
	var tablename = reference[0];
	var ecrid = reference[1];
	var getRequest = new XMLHttpRequest();
	var recordHtml = "<h4>Anzeige eines Datensatzes aus der Tabelle " + reference[0] + "</h4><p>Der Datensatz wird so, " 
		+ "wie er in der Datenbank hinterlegt ist, angezeigt. Für noch mehr Information, " 
		+ "etwa die Auflösung der UUIDs, verwende bitte den Menüpunkt 'efa Datensatz finden'</p>";
	// provide the callback for a response received
	getRequest.onload = function() {
		if (getRequest.status == 500)
			recordHtml += "<p>Der Server gibt einen allgemeinen Fehlercode zurück (500).</p>";
		else
			recordHtml += "<p>" + getRequest.response + "</p>";
		cModal.showHtml(recordHtml);
	};
	// provide the callback for any error.
	getRequest.onerror = function() {
		recordHtml += "<p>Fehler bei der Abfrage der Daten vom Server.</p>";
		cModal.showHtml(recordHtml);
	};
	// send the GET request
	getRequest.open('GET', "../pages/getrecord.php?table=" + tablename + "&ecrid=" + ecrid, true);
	getRequest.send(null);
}

// will bind all event items by selecting all .menuitems with #do-...
function _bindEvents() {
	$eventItems = $('.eventitem'); // for debugging: do not inline statement.
	$eventItems.unbind();
	$eventItems.click(function() {
		var thisElement = $(this); // for debugging: do not inline statement.
		var id = thisElement.attr("id");
		if (!id)
			return;
		if (id.indexOf("viewrecord_") == 0)
			_showRecord(id.replace("viewrecord_", "").split("_"));
		if (id.indexOf("showhelptext_") == 0)
			_showHelptext(id.replace("showhelptext_", "").split("_"));
	});
}

/**
 * initialization procedures to be performed when document was loaded.
 */
$(document).ready(function() {
	_bindEvents();
});
