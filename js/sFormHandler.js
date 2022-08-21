/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2021 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

/**
 * Handle the display of forms for efaCloud Server data manipulation.
 */

var sFormHandler = {

	/**
	 * prepare the lookup fields for damage entry (persons, boat).
	 */
	editDamage_prepare : function() {
		bLists.readCsv("efa2boatdamages", "efaWeb_boats");
		var input = $("#bFormInput-BoatId")[0];
		var options = Object.keys(bLists.names.efaWeb_boats_names);
		autocomplete(input, options, "efaWeb_boats");
	},

}