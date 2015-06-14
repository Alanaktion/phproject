/*jslint browser: true, ass: true, debug: true, eqeq: true, newcap: true, nomen: true, plusplus: true, unparam: true, sloppy: true, sub: true, vars: true, white: true */

$(function() {
	Backlog.init();
});

var Backlog = {
	updateUrl: BASE + "/backlog/edit",
	projectReceived: 0,
	init: function() {
		Backlog.makeSortable(".sortable");
		$(".sortable").on("dblclick", "li", function(e) {
			window.open(BASE + "/issues/" + $(this).data("id"));
		});
	},
	makeSortable: function(selector) {
		$(selector).sortable({
			items: "li:not(.unsortable)",
			connectWith: '.sortable',
			receive: function(event, ui) {
				Backlog.projectReceive($(ui.item), $(ui.sender), sanitizeSortableArray("project", $(this).sortable('serialize')));
				Backlog.projectReceived = true; //keep from repeating if changed lists
			},
			stop: function(event, ui) {
				if (Backlog.projectReceived !== true) {
					Backlog.sameReceive($(ui.item), sanitizeSortableArray("project", $(this).sortable('serialize')));
				} else {
					Backlog.projectReceived = false;
				}
			}
		}).disableSelection();
	},
	projectReceive: function(item, sender, receiverSerialized) {
		var itemId = cleanId("project", $(item).attr("id")),
			receiverId = cleanId("sprint", $(item).parent().attr("data-list-id")),
			senderId = $(sender).attr("data-list-id");
		if (typeof($(sender).attr("data-list-id") !== "undefined")) {
			var senderSerialized = sanitizeSortableArray("project", $(sender).sortable('serialize')),
				data = {
					itemId: itemId,
					sender: {
						senderId: senderId,
						senderSerialized: senderSerialized
					},
					reciever: {
						receiverId: receiverId,
						receiverSerialized: receiverSerialized
					}
				};

			Backlog.ajaxUpdateBacklog(data, item);
		}
	},
	sameReceive: function(item, receiverSerialized) {
		var itemId = cleanId("project", $(item).attr("id")),
			receiverId = cleanId("sprint", $(item).parent().attr("data-list-id")),
			data = {
				itemId: itemId,
				reciever: {
					receiverId: receiverId,
					receiverSerialized: receiverSerialized
				}
			};

		Backlog.ajaxUpdateBacklog(data, item);
	},
	ajaxUpdateBacklog: function(data, item) {
		// console.log(data);
		var projectId = data.itemId;
		// console.log(data);
		Backlog.block(projectId, item);
		$.ajax({
			type: "POST",
			url: Backlog.updateUrl,
			data: data,
			success: function(data, textStatus, jqXHR) {
				Backlog.unBlock(projectId, item);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				Backlog.unBlock(projectId, item);
				Backlog.showError(projectId, item);
			}
		});
	},
	block: function(projectId, item) {
		var project = $('#project_' + projectId);
		project.append('<div class="spinner"></div>');
		item.addClass("unsortable");
		Backlog.makeSortable('.sortable'); //redo this so it is disabled
	},
	unBlock: function(projectId, item) {
		var project = $('#project_' + projectId);
		project.find('.spinner').remove();
		item.removeClass("unsortable");
		Backlog.makeSortable('.sortable'); //redo this so it is disabled
	},
	showError: function(projectId, item) {
		var project = $('#project_' + projectId);
		project.css({
			"opacity": ".8"
		});
		project.append('<div class="error" title="An error occured while saving the task!"></div>');
		item.addClass("unsortable");
		Backlog.makeSortable('.sortable'); //redo this so it is disabled
	}
};


function isNumber(n) {
	return !isNaN(parseFloat(n)) && isFinite(n);
}

function checkLength(o, n, min, max) {
	if (o.val().length > max || o.val().length < min) {
		o.addClass("ui-state-error");
		// updateTips("Length of " + n + " must be between " + min + " and " + max + "."); // updateTips() function does not exist
		return false;
	}
	return true;
}

function checkRegexp(o, regexp, n) {
	if (!(regexp.test(o.val()))) {
		o.addClass("ui-state-error");
		// updateTips(n); // updateTips() function does not exist
		return false;
	}
	return true;
}

jQuery.fn.serializeObject = function() {
	var arrayData, objectData;
	arrayData = this.serializeArray();
	objectData = {};

	$.each(arrayData, function() {
		var value;

		if (this.value !== null) {
			value = this.value;
		} else {
			value = '';
		}

		if (objectData[this.name] !== null) {
			if (!objectData[this.name].push) {
				objectData[this.name] = [objectData[this.name]];
			}

			objectData[this.name].push(value);
		} else {
			objectData[this.name] = value;
		}
	});

	return objectData;
};

function cleanId(identifier, id) {
	return (id.replace(identifier + "_", ""));
}

function sanitizeSortableArray(identifier, sortableString) {
	sortableString = sortableString.replace(/&/g, "");

	var sortableArray = [];
	sortableArray = sortableString.split(identifier + "[]=");
	sortableArray.splice(0, 1);

	return sortableArray;
}
