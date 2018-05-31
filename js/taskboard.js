/*jslint browser: true, ass: true, debug: true, eqeq: true, newcap: true, nomen: true, plusplus: true, unparam: true, sloppy: true, sub: true, vars: true, white: true */
/* globals $ BASE datepickerLanguage */

function isNumber(n) {
	return !isNaN(parseFloat(n)) && isFinite(n);
}

$.fn.serializeObject = function() {
	var o = {};
	var a = this.serializeArray();
	$.each(a, function() {
		if (o[this.name]) {
			if (!o[this.name].push) {
				o[this.name] = [o[this.name]];
			}
			o[this.name].push(this.value || '');
		} else {
			o[this.name] = this.value || '';
		}
	});
	return o;
};

var Taskboard = {
	updateURL: BASE + '/taskboard/edit', // AJAX Update Route (id is added dynamically)
	addURL: BASE + '/taskboard/add', // AJAX Add Route
	taskReceived: false, // used for checking tasks resorted within the same list or to another
	newTaskId: 0, // keep track of new tasks in case there are a few error tasks
	init: function() {

		// Initialize drag / drop
		Taskboard.makeDraggable($('.task'));

		$('.droppable').droppable({
			accept: '.task',
			over: function(e, ui) {
				let $task = ui.draggable;
				let height = $task.height();
				if ($task.closest('td')[0] != this) {
					$(this).append('<div class="panel panel-default placeholder" style="height: ' + height + 'px;"><div class="panel-body"></div></div>');
				}
			},
			out: function() {
				$(this).find('.placeholder').remove();
			},
			drop: function(event, ui) {
				$(this).find('.placeholder').remove();
				$(this).append(
					$(ui.draggable)
					.css({
						'position': '',
						'left': '',
						'top': ''
					}).draggable({
						revert: 'invalid',
						stack: '.task',
						distance: 10
					})
				);
				Taskboard.TaskboardReceive($(ui.draggable));
			}
		});

		// Initialize issue editing handler
		$('#taskboard').on('click', '.task', function(e) {
			let $target = $(e.target);
			if(!$target.is('a') && !$target.is('img')) {
				Taskboard.modalEdit($(this));
			}
		});

		// Initialize add buttons on stories
		$('.add-task').click(function(e) {
			e.preventDefault();
			Taskboard.modalAdd($(this).parents('.tb-row').data('story-id'));
		});

		// Handle add/edit form submission
		$('#task-dialog form').submit(function(e) {
			e.preventDefault();
			var $this = $(this),
				data = $('#task-dialog form').serializeObject();
			$this.find('.has-error').removeClass('has-error');
			if($this.find('#taskId').val()) {
				if ($('#hours').val() === '' || isNumber($('#hours').val())) {
					Taskboard.updateCard($('#task_' + data.taskId), data);
					$('#task-dialog').modal('hide');
				} else {
					$('#hours').parents('.form-group').addClass('has-error');
				}
			} else {
				Taskboard.addCard($('#project_' + $this.data('story-id')), data, $this.data('story-id'));
				$('#task-dialog').modal('hide');
			}
			return false;
		});

		// Initialize priority tooltips
		$('#taskboard').tooltip({
			title: function() {
				let $this = $(this);
				return $this.attr('data-prefix') + ' — ' + $this.text();
			},
			container: 'body',
			selector: '.task .priority',
			placement: 'auto right',
		});
	},
	makeDraggable: function(card) {
		$(card).draggable({
			helper: 'clone',
			cursor: 'move',
			containment: '#taskboard',
			revert: 'invalid',
			stack: '.task',
			start: function(e, ui) {
				$(this).css('opacity', '.5');

				// Make helper background semi-transparent
				let $el = ui.helper;
				let opacity = 0.95;
				if ('CSS' in window && CSS.supports("(backdrop-filter: blur(15px)) or (-webkit-backdrop-filter: blur(15px))")) {
					opacity = 0.75;
				}
				$el.css('background-color', $el.css('background-color').replace(')', ', ' + opacity + ')').replace('rgb', 'rgba'));
			},
			stop: function() {
				$(this).css('opacity', '1');
			},
			distance: 10,
			zIndex: 999
		});
	},
	modalEdit: function($el) {
		let user = $el.find('.owner').data('id');
		let userColor = $el.css('border-left-color');
		let taskId = $el.attr('id').replace('task_', '');
		let title = $el.find('.title').text().trim();
		let description = $el.find('.description').text().trim();
		let hours = $el.find('.hours').text().trim();
		let date = $el.find('.dueDate').text().trim();
		let priority = $el.find('.priority').data('val');
		let repeatCycle = $el.find('.repeat_cycle').text();

		$('#task-dialog input#taskId').val(taskId);
		$('#task-dialog input#title').val(title);
		$('#task-dialog textarea#description').val(description);
		$('#task-dialog input#hours').val(hours);
		$('#task-dialog input#hours_spent').val('');
		$('#task-dialog input#comment').val('');
		$('#task-dialog select#repeat_cycle').val(repeatCycle);
		$('#task-dialog input#dueDate').val(date);
		$('#task-dialog').find('#dueDate').datepicker({
			format: 'mm/dd/yyyy',
			language: datepickerLanguage,
			todayHighlight: true,
			autoclose: true
		});
		Taskboard.setOptionByVal('#task-dialog', user);
		Taskboard.setOptionByVal('#priority', priority);

		$('#task-dialog .modal-title').text('Edit Task');
		$('#task-dialog').modal('show');
		Taskboard.changeModalColor(userColor);
	},
	modalAdd: function(storyId) {
		$('#task-dialog input, #task-dialog textarea').not('#sprintId').val('');
		$('#task-dialog #priority').val(0);
		$('#task-dialog #assigned').val($('#task-dialog #assigned').data('default-value'));
		Taskboard.changeModalColor($('#task-dialog #assigned').data('default-color'));
		$('#task-dialog .modal-title').text('Add Task');
		$('#task-dialog form').data('story-id', storyId);
		$('#task-dialog').modal('show');
		$('#task-dialog').find('#dueDate').datepicker({
			format: 'mm/dd/yyyy',
			language: datepickerLanguage,
			todayHighlight: true,
			autoclose: true
		});
	},
	changeModalColor: function(userColor) {
		$('#task-dialog .modal-content').css('border', '3px solid ' + userColor);
	},
	updateCardPriority: function(priority, card) {
		let $priority = $(card).find('.priority');
		let priorityName = $('#priority').find('option[value=' + priority + ']').text();
		$priority.data('val', priority).text(priorityName).removeClass('low high normal');
		if (!priority) {
			$priority.attr('class', 'priority normal');
		} else if (priority < 0) {
			$priority.attr('class', 'priority low');
		} else if (priority > 0) {
			$priority.attr('class', 'priority high');
		}
	},
	changeUser: function(selected) {
		let color = $(selected).find('option:selected').attr('data-color');
		Taskboard.changeModalColor(color);
	},
	setOptionByText: function(selectId, text) {
		$(selectId + ' option').filter(function() {
			return $(this).text() == text;
		}).prop('selected', true);
	},
	setOptionByVal: function(selectId, val) {
		$(selectId + ' option').filter(function() {
			return $(this).attr('value') == val;
		}).prop('selected', true);
	},
	updateCard: function(card, data) {
		let $card = $(card);
		$card.find('.title').text(data.title);
		$card.find('.repeat_cycle').text(data.repeat_cycle);

		if (isNumber(data.hours_spent) && parseInt(data.burndown) && data.hours > 0) {
			$card.find('.hours').text(parseFloat(data.hours) - parseFloat(data.hours_spent));
			$card.find('.hours').show();
		} else if (isNumber(data.hours) && data.hours > 0) {
			$card.find('.hours').text(parseFloat(data.hours));
			$card.find('.hours').show();
		} else {
			$card.find('.hours').hide();
		}

		$card.find('.description').text(data.description);
		$card.find('.dueDate').text(data.dueDate);

		let $user = $('#task-dialog #assigned option[value="' + data.assigned + '"]');
		$card.find('.owner')
			.text($user.text())
			.data('id', data.assigned);
		if ($card.find('.owner').parent('a').length) {
			$card.find('.owner').parent('a').attr('href', BASE + '/user/' + $user.attr('data-username'));
		}
		if ($card.find('.owner').siblings('img').length) {
			$card.find('.owner').siblings('img').attr('src', BASE + '/avatar/48/' + data.assigned + '.png');
		}

		$card.css('border-left-color', $('#task-dialog #assigned option[value="' + data.assigned + '"]').first().attr('data-color'));
		Taskboard.updateCardPriority(data.priority, card);
		Taskboard.ajaxUpdateTask(data);
	},
	addCard: function(story, data, storyId) {
		var row = $(story).parents('.tb-row');
		var cell = row.find('td.column-2'); // put new tasks in the new column
		cell.append($('.cloneable:last').clone());
		var card = cell.find('.cloneable:last');

		$(card).find('.title').text(data.title);
		$(card).find('.repeat_cycle').text(data.repeat_cycle);

		if (isNumber(data.hours) && data.hours > 0) {
			$(card).find('.hours').text(parseFloat(data.hours));
			$(card).find('.hours').show();
		} else {
			$(card).find('.hours').hide();
		}

		$(card).find('.description').text(data.description);
		// $(card).find('.dueDate').text(data.dueDate);
		$(card).find('.owner').text($('#task-dialog #assigned option[value="' + data.assigned + '"]').first().text());
		$(card).css('border-left-color', $('#task-dialog #assigned option[value="' + data.assigned + '"]').first().attr('data-color'));
		$(card).removeClass('cloneable');
		$(card).attr('id', 'new_task_' + Taskboard.newTaskId);
		Taskboard.updateCardPriority(data.priority, card);

		data.storyId = storyId;
		data.newTaskId = Taskboard.newTaskId;
		Taskboard.ajaxAddTask(data, card);
		Taskboard.newTaskId++;

		card.show();
	},
	TaskboardReceive: function(task) { // if the task changes statuses/stories
		var taskId = $(task).attr('id').replace('task_', ''),
			receiverStatus = $(task).parent().attr('data-status'),
			receiverStory = $(task).parents('.tb-row').attr('data-story-id'),
			data = {
				taskId: taskId,
				receiver: {
					'story': receiverStory,
					'status': receiverStatus
				}
			};
		if ($(task).parents('.column').hasClass('completed')) {
			$(task).find('.hours').text(0);
		}
		Taskboard.ajaxSendTaskPosition(data);
	},
	TaskboardSame: function(task, receiverSerialized) {
		var taskId = $(task).attr('id').replace('task_', ''),
			receiverStatus = $(task).parent().attr('data-status'),
			receiverStory = $(task).parents('.tb-row').attr('data-story-id'),
			data = {
				taskId: taskId,
				receiver: {
					'story': receiverStory,
					'status': receiverStatus,
					'sortingOrder': receiverSerialized
				}
			};
		Taskboard.ajaxSendTaskPosition(data);
	},
	ajaxSendTaskPosition: function(data) {
		var taskId = data.taskId;
		Taskboard.block(taskId);
		$.ajax({
			type: 'POST',
			url: Taskboard.updateURL + '/' + taskId,
			data: data,
			success: function() {
				Taskboard.unBlock(taskId);
			},
			error: function() {
				Taskboard.unBlock(taskId);
				Taskboard.showError(taskId);
				$('#task_' + taskId).draggable('option', 'disabled', true);
			}
		});
	},
	ajaxUpdateTask: function(data) {
		var taskId = data.taskId;
		Taskboard.block(taskId);
		$.ajax({
			type: 'POST',
			url: Taskboard.updateURL + '/' + taskId,
			data: data,
			success: function() {
				Taskboard.unBlock(taskId);
			},
			error: function() {
				Taskboard.unBlock(taskId);
				Taskboard.showError(taskId);
				$('#task_' + taskId).draggable('option', 'disabled', true);
			}
		});
	},
	ajaxAddTask: function(data, card) {
		var taskId = data.newTaskId;
		Taskboard.newBlock(taskId);
		$.ajax({
			type: 'POST',
			url: Taskboard.addURL,
			data: data,
			success: function(result) {
				Taskboard.newUnBlock(taskId);
				$(card).find('.task-id').html('<a href="/issues/' + result.id + '" target="_blank">' + result.id + '</a>');
				$(card).attr('id', 'task_' + result.id);
				Taskboard.makeDraggable(card);
			},
			error: function() {
				Taskboard.newUnBlock(taskId);
				Taskboard.showError(taskId);
				$(card).draggable('option', 'disabled', true);
			}
		});
	},
	block: function(taskId) {
		$('#task_' + taskId).append('<div class="spinner"></div>');
	},
	unBlock: function(taskId) {
		$('#task_' + taskId).find('.spinner').remove();
	},
	newBlock: function(taskId) {
		$('#new_task_' + taskId).append('<div class="spinner"></div>');
	},
	newUnBlock: function(taskId) {
		$('#new_task_' + taskId).find('.spinner').remove();
	},
	showError: function(taskId) {
		$('#task_' + taskId + ', #new_task_' + taskId).css({
			'opacity': '.8'
		}).append('<div class="error text-danger" title="An error occured while saving the task."><span class="fa fa-exclamation-triangle" aira-hidden="true"></span></div>');
	}
};

$(function() {
	Taskboard.init();
});
