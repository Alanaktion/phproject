/* jslint browser: true */
/* globals $ BASE showAlert */

/**
 * Get query string variable value by key
 * @link   https://css-tricks.com/snippets/javascript/get-url-variables/
 * @param  {string} variable
 * @return {string}
 */
function getQueryVariable(variable) {
	var query = window.location.search.substring(1);
	var vars = query.split('&');
	for (var i = 0; i < vars.length; i++) {
		var pair = vars[i].split('=');
		if(pair[0] == variable) {
			return pair[1];
		}
	}
	return(false);
}

function refreshPoints(){
	$('.panel').each(function() {
		// calculates total points in the backlog and sprints
		var points = 0;
		$('.list-group-item:not(.hidden-group):not(.hidden-type)', this).each(function() {
			var val = parseInt($(this).attr('data-points'), 10);
			if (val > 0) {
				points += val;
			}
		});

		// calculates completed points in sprints
		var completedPoints = 0;
		$('.list-group-item.completed:not(.hidden-group):not(.hidden-type)', this).each(function() {
			var cval = parseInt($(this).attr('data-points'), 10);
			if (cval > 0) {
				completedPoints += cval;
			}
		});

		// if no total points than hide points displayed
		if (points) {
			$('.panel-head-points', this).show();
		} else {
			$('.panel-head-points', this).hide();
		}

		// adds points to span
		$('.points-label', this).text(points);

		// adds completed points to span
		$('.points-label-completed', this).text(completedPoints);
	});
	$('.panel').each(function() {
	});
}

var Backlog = {
	init: function() {
		// Initialize sorting
		if (window.sortBacklog) {
			$('.sortable').each(function() {
				new Sortable(this, {
					group: 'backlog',
					ghostClass: 'placeholder',
					filter: '.hidden-group,.hidden-type',
					scrollSensitivity: 90,
					onAdd: function(event) {
						var $item = $(event.item);
						$.post(BASE + '/backlog/edit', {
							id: $item.attr('data-id'),
							sprint_id: $item.parents('.list-group').attr('data-list-id')
						}).fail(function() {
							showAlert('Failed to save new sprint assignment');
						});
					},
					onSort: function(event) {
						Backlog.saveSortOrder(event.target);
					}
				});
			});
		}

		// Open issue in new window on double-click
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
					if (val) {
						$('.list-group-item[data-user-id=' + val + ']').removeClass('hidden-group');
					}
				});
			}
			refreshPoints();
			Backlog.updateUrl();
			e.preventDefault();
		});

		// Handle type filters
		$('.dropdown-menu a[data-type-id]').click(function(e) {
			var $this = $(this),
				typeId = $this.attr('data-type-id');
			$this.parents('li').toggleClass('active');
			$('.list-group-item[data-type-id=' + typeId + ']').toggleClass('hidden-type');
			refreshPoints();
			Backlog.updateUrl();
			e.preventDefault();
		});

		// Apply filters from query string, if any
		var typeIdString = getQueryVariable('type_ids');
		if (typeIdString) {
			$('.list-group-item[data-type-id]').addClass('hidden-type');
			$('.dropdown-menu a[data-type-id]').parents('li').removeClass('active');
			$.each(decodeURIComponent(typeIdString).split(','), function (i, val) {
				$('.dropdown-menu a[data-type-id=' + val + ']').parents('li').addClass('active');
				$('.list-group-item[data-type-id=' + val + ']').removeClass('hidden-type');
			});
		}
		var groupId = getQueryVariable('group_id');
		if (groupId) {
			$('.dropdown-menu a[data-group-id=' + groupId + ']').click();
		} else {
			$('.dropdown-menu a[data-my-groups]').click();
		}

		// Un-hide backlog
		$('body').removeClass('is-loading');
	},
	updateUrl: function() {
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
		// Updates href attr if group_id is set
		$('.sprint-board').each(function() {
			var clickGroupId = $('.dropdown-menu .active a[data-user-ids]').attr('data-group-id');
			var hrefLink = $(this).attr('data-base-href');
			if (clickGroupId) {
				$(this).attr('href', hrefLink + '/' + clickGroupId);
			} else {
				$(this).attr('href', hrefLink);
			}
		});
	},
	saveSortOrder: function(element) {
		var $el = $(element),
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
		}).fail(function() {
			showAlert('An error occurred saving the sort order.');
		});
		refreshPoints();
	}
};

$(function() {
	Backlog.init();
	refreshPoints();
});
