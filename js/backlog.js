/* jslint browser: true */
/* globals $ BASE */

function cleanId(identifier, id) {
	return (id.replace(identifier + '_', ''));
}

function sanitizeSortableArray(identifier, sortableString) {
	sortableString = sortableString.replace(/&/g, '');

	var sortableArray = [];
	sortableArray = sortableString.split(identifier + '[]=');
	sortableArray.splice(0, 1);

	return sortableArray;
}

var Backlog = {
	updateUrl: BASE + '/backlog/edit',
	projectReceived: 0,
	init: function() {
		Backlog.makeSortable('.sortable');
		$('.sortable').on('dblclick', 'li', function() {
			window.open(BASE + '/issues/' + $(this).data('id'));
		});
	},
	makeSortable: function(selector) {
		$(selector).sortable({
			items: 'li:not(.unsortable)',
			connectWith: '.sortable',
			receive: function(event, ui) {
				Backlog.projectReceive($(ui.item), $(ui.sender), sanitizeSortableArray('project', $(this).sortable('serialize')));
				Backlog.projectReceived = true; // keep from repeating if changed lists
			},
			stop: function(event, ui) {
				if (Backlog.projectReceived !== true) {
					Backlog.sameReceive($(ui.item), sanitizeSortableArray('project', $(this).sortable('serialize')));
				} else {
					Backlog.projectReceived = false;
				}
				Backlog.saveSortOrder();
			}
		}).disableSelection();
	},
	projectReceive: function(item, sender, receiverSerialized) {
		var itemId = cleanId('project', $(item).attr('id')),
			receiverId = $(item).parent().attr('data-list-id'),
			senderId = $(sender).attr('data-list-id');
		if (typeof($(sender).attr('data-list-id') !== 'undefined')) {
			var senderSerialized = sanitizeSortableArray('project', $(sender).sortable('serialize')),
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
		var itemId = cleanId('project', $(item).attr('id')),
			receiverId = $(item).parent().attr('data-list-id'),
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
		var projectId = data.itemId;
		Backlog.block(projectId, item);
		$.ajax({
			type: 'POST',
			url: Backlog.updateUrl,
			data: data,
			success: function() {
				Backlog.unBlock(projectId, item);
			},
			error: function() {
				Backlog.unBlock(projectId, item);
				Backlog.showError(projectId, item);
			}
		});
	},
	saveSortOrder: function() {
		var userId = $('#panel-0 .list-group').attr('data-user-id');

		if(!userId)
			return;

		$('.panel-body > .list-group.sortable').each(function() {
			var items = [];
			$(this).find('.list-group-item').each(function() {
				items.push(parseInt($(this).attr('data-id')));
			});

			$.post(BASE + '/backlog/sort', {
				user: userId,
				sprint_id: $(this).attr('data-list-id'),
				items: JSON.stringify(items)
			}).error(function() {
				console.error('An error occurred saving the sort order.');
			});
		});
	},
	block: function(projectId, item) {
		var project = $('#project_' + projectId);
		project.append('<div class="spinner"></div>');
		item.addClass('unsortable');
		Backlog.makeSortable('.sortable'); //redo this so it is disabled
	},
	unBlock: function(projectId, item) {
		var project = $('#project_' + projectId);
		project.find('.spinner').remove();
		item.removeClass('unsortable');
		Backlog.makeSortable('.sortable'); //redo this so it is disabled
	},
	showError: function(projectId, item) {
		var project = $('#project_' + projectId);
		project.css({
			'opacity': '.8'
		});
		project.append('<div class="error" title="An error occured while saving the task!"></div>');
		item.addClass('unsortable');
		Backlog.makeSortable('.sortable'); //redo this so it is disabled
	}
};

$(function() {
	Backlog.init();
});
