/* jslint browser: true */
/* globals $ BASE */

function cleanId(identifier, id) {
	return (id.replace(identifier + '_', ''));
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
			start: function(event, ui) {
				// Fade out non-matching types
				if($(ui.item).attr('data-type-id')) {
					$('.sortable .list-group-item')
						.filter(':not([data-type-id="' + $(ui.item).attr('data-type-id') + '"])')
						.fadeTo(200, 0.25);
				}
			},
			receive: function(event, ui) {
				Backlog.projectReceive($(ui.item), $(ui.sender));
				Backlog.projectReceived = true; // keep from repeating if changed lists
			},
			stop: function(event, ui) {
				// Fade in all items
				$('.sortable .list-group-item').fadeTo(150, 1);
				if (Backlog.projectReceived !== true) {
					Backlog.sameReceive($(ui.item));
				} else {
					Backlog.projectReceived = false;
				}
			}
		}).disableSelection();
	},
	projectReceive: function(item, sender) {
		var itemId = cleanId('project', $(item).attr('id')),
			receiverId = $(item).parent().attr('data-list-id'),
			senderId = $(sender).attr('data-list-id');
		if ($(item).parent().attr('data-list-id') !== undefined) {
			var data = {
					itemId: itemId,
					sender: {
						senderId: senderId
					},
					reciever: {
						receiverId: receiverId
					}
				};

			Backlog.ajaxUpdateBacklog(data, item);
			Backlog.saveSortOrder([sender, $(item).parents('.sortable')]);
		}
	},
	sameReceive: function(item) {
		var itemId = cleanId('project', $(item).attr('id')),
			receiverId = $(item).parent().attr('data-list-id'),
			data = {
				itemId: itemId,
				reciever: {
					receiverId: receiverId
				}
			};

		Backlog.ajaxUpdateBacklog(data, item);
		Backlog.saveSortOrder([$(item).parents('.sortable')]);
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
	saveSortOrder: function(elements) {
		var userId = $('#panel-0 .list-group').attr('data-user-id');

		if(!userId)
			return;

		$(elements).each(function() {
			var $el = $(this),
				itemsByType = {};

			if ($el.attr('data-list-id') === undefined) {
				return;
			}

			$el.find('.list-group-item').each(function() {
				var type = $(this).attr('data-type-id');
				if (!(type in itemsByType)) {
					itemsByType[type] = [];
				}
				itemsByType[type].push(parseInt($(this).attr('data-id')));
			});

			$.each(itemsByType, function(type, items) {
				$.post(BASE + '/backlog/sort', {
					user_id: userId,
					sprint_id: $el.attr('data-list-id'),
					type_id: type,
					items: JSON.stringify(items)
				}).error(function() {
					console.error('An error occurred saving the sort order.');
				});
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
