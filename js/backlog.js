/* jslint browser: true */
/* globals $ BASE */

/**
 * Get query string variable value by key
 * @link   https://css-tricks.com/snippets/javascript/get-url-variables/
 * @param  {string} variable
 * @return {string}
 */
function getQueryVariable(variable) {
	var query = window.location.search.substring(1);
	var vars = query.split("&");
	for (var i = 0; i < vars.length; i++) {
		var pair = vars[i].split("=");
		if(pair[0] == variable) {
			return pair[1];
		}
	}
	return(false);
}

var Backlog = {
	updateUrl: BASE + '/backlog/edit',
	projectReceived: 0,
	init: function() {
		Backlog.makeSortable('.sortable');
		$('.sortable').on('dblclick', 'li', function() {
			window.open(BASE + '/issues/' + $(this).data('id'));
		});

		// Handle group filters
		$('.dropdown-menu a[data-user-ids]').click(function(e) {
			var $this = $(this),
				userIds = $this.attr('data-user-ids').split(',');

			$this.parents('ul').children('li').removeClass('active');
			$this.parents('li').addClass('active');

			if (userIds == 'all') {
				$('.list-group-item[data-user-id]').removeClass('hidden-group');
			} else {
				$('.list-group-item[data-user-id]').addClass('hidden-group');
				$.each(userIds, function(i, val) {
					$('.list-group-item[data-user-id=' + val + ']').removeClass('hidden-group');
				});
			}

			Backlog.replaceUrl();
			e.preventDefault();
		});

		// Handle type filters
		$('.dropdown-menu a[data-type-id]').click(function(e) {
			var $this = $(this),
				typeId = $this.attr('data-type-id');
			$this.parents('li').toggleClass('active');
			$('.list-group-item[data-type-id=' + typeId + ']').toggleClass('hidden-type');
			Backlog.replaceUrl();
			e.preventDefault();
		});

		// Apply filters from query string, if any
		var groupId = getQueryVariable('group_id');
		if (groupId) {
			$('.dropdown-menu a[data-group-id=' + groupId + ']').click();
		} else {
			$('.dropdown-menu a[data-my-groups]').click();
		}
		var typeIdString = getQueryVariable('type_id');
		if (typeIdString) {
			$('.list-group-item[data-type-id]').addClass('hidden-type');
			$('.dropdown-menu a[data-type-id]').parents('li').removeClass('active');
			$.each(decodeURIComponent(typeIdString).split(','), function (i, val) {
				$('.dropdown-menu a[data-type-id=' + val + ']').parents('li').addClass('active');
				$('.list-group-item[data-type-id=' + val + ']').removeClass('hidden-type');
			});
		}
	},
	replaceUrl: function() {
		if (window.history && history.replaceState) {
			var state = {};
			state.groupId = $('.dropdown-menu .active a[data-user-ids]').attr('data-group-id');
			state.typeIds = [];
			$('.dropdown-menu .active a[data-type-id]').each(function() {
				state.typeIds.push($(this).attr('data-type-id'));
			});
			state.allStatesApplied = !$('.dropdown-menu li:not(.active) a[data-type-id]').length;

			var path = '/backlog';
			if (state.groupId || (!state.allStatesApplied && state.typeIds)) {
				path += '?';
				if (state.groupId) {
					path += 'group_id=' + encodeURIComponent(state.groupId);
					if (!state.allStatesApplied && state.typeIds) {
						path += '&';
					}
				}
				if (!state.allStatesApplied && state.typeIds) {
					path += 'type_ids=' + encodeURIComponent(state.typeIds.join(','));
				}
			}
			history.replaceState(state, '', BASE + path);
		}
	},
	makeSortable: function(selector) {
		$(selector).sortable({
			items: 'li:not(.unsortable)',
			connectWith: '.sortable',
			start: function(event, ui) {
				// Fade out non-matching types
				/*if($(ui.item).attr('data-type-id')) {
					$('.sortable .list-group-item')
						.filter(':not([data-type-id="' + $(ui.item).attr('data-type-id') + '"])')
						.fadeTo(200, 0.25);
				}*/
			},
			receive: function(event, ui) {
				Backlog.projectReceive($(ui.item), $(ui.sender));
				Backlog.projectReceived = true; // keep from repeating if changed lists
			},
			stop: function(event, ui) {
				// Fade in all items
				/*$('.sortable .list-group-item').fadeTo(150, 1);
				if (Backlog.projectReceived !== true) {
					Backlog.sameReceive($(ui.item));
				} else {
					Backlog.projectReceived = false;
				}*/
			}
		}).disableSelection();
	},
	projectReceive: function(item, sender) {
		var itemId = $(item).attr('data-id'),
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
		var itemId = $(item).attr('data-id'),
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
		console.log(elements);

		$(elements).each(function() {
			var $el = $(this),
				items = [];

			if ($el.attr('data-list-id') === undefined) {
				return;
			}

			$el.find('.list-group-item').each(function() {
				items.push(parseInt($(this).attr('data-id')));
			});

			$.post(BASE + '/backlog/sort', {
				sprint_id: $el.attr('data-list-id'),
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
