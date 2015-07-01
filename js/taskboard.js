/*jslint browser: true, ass: true, debug: true, eqeq: true, newcap: true, nomen: true, plusplus: true, unparam: true, sloppy: true, sub: true, vars: true, white: true */

$(function() {
	Taskboard.init();
});

var Taskboard = {
	updateURL: BASE + '/taskboard/edit', // AJAX Update Route (id is added dynamically)
	addURL: BASE + '/taskboard/add', // AJAX Add Route
	taskReceived: false, // used for checking tasks resorted within the same list or to another
	newTaskId: 0, // keep track of new tasks in case there are a few error tasks
	init: function() {

		// Initialize drag / drop
		Taskboard.makeDraggable($(".card.task"));

		$(".droppable").droppable({
			accept: ".card.task",
			over: function() {
				$(this).append('<div class="card task placeholder"></div>');
			},
			out: function() {
				$(this).find('.placeholder').remove();
			},
			drop: function(event, ui) {
				$(this).find('.placeholder').remove();
				$(this).append(
					$(ui.draggable)
					.css({
						"position": "",
						"left": "",
						"top": ""
					}).draggable({
						revert: "invalid",
						stack: ".card.task",
						distance: 10
					})
				);
				Taskboard.TaskboardReceive($(ui.draggable));
			}
		});

		// Initialize issue editing handler
		$("#task-table").on("click", ".card.task", function(e) {
			if(!$(e.target).is("a")) {
				Taskboard.modalEdit($(this));
			}
		}).on("touchstart", ".card.task", function(e) {
			if(!$(e.target).is("a")) {
				$(this).popover("show");
			}
		}).on("touchend", ".card.task", function(e) {
			if(!$(e.target).is("a")) {
				$(this).popover("hide");
				Taskboard.modalEdit($(this));
			}
		});

		// Initialize add buttons on stories
		$(".add-task").click(function(e) {
			Taskboard.modalAdd($(this).parents('.tb-row').data("story-id"));
			e.preventDefault();
		});

		// Handle add/edit form submission
		$('#task-dialog form').submit(function(e) {
			e.preventDefault();
			var $this = $(this),
				data = $('#task-dialog form').serializeObject();
			$this.find(".has-error").removeClass("has-error");
			if($this.find('#taskId').val()) {
				if ($('#hours').val() === '' || isNumber($('#hours').val())) {
					Taskboard.updateCard($("#task_" + data.taskId), data);
					$("#task-dialog").modal('hide');
				} else {
					$("#hours").parents(".form-group").addClass("has-error");
				}
			} else {
				Taskboard.addCard($("#project_" + $this.data("story-id")), data, $this.data("story-id"));
				$("#task-dialog").modal('hide');
			}
			return false;
		});

	},
	makeDraggable: function(card) {
		$(card).draggable({
			helper: "clone",
			cursoer: "move",
			containment: "#task-table",
			revert: "invalid",
			stack: ".card.task",
			start: function() {
				$(this).css("opacity", ".5");
			},
			stop: function() {
				$(this).css("opacity", "1");
			},
			distance: 10
		});
	},
	modalEdit: function(data) {
		var user = $(data).find('.owner').data('id'),
			userColor = $(data).css('border-left-color'),
			taskId = $(data).attr('id').replace('task_', ''),
			title = $(data).find('.title').text().trim(),
			description = $(data).find('.description').text().trim(),
			hours = $(data).find('.hours').text().trim(),
			date = $(data).find('.dueDate').text().trim(),
			priority = $(data).find('.priority').data('val'),
			repeat_cycle = $(data).find('.repeat_cycle').text();

		$("#task-dialog input#taskId").val(taskId);
		$("#task-dialog input#title").val(title);
		$("#task-dialog textarea#description").val(description);
		$("#task-dialog input#hours").val(hours);
		$("#task-dialog input#hours_spent").val('');
		$("#task-dialog input#comment").val('');
		$("#task-dialog select#repeat_cycle").val(repeat_cycle);
		$("#task-dialog input#dueDate").val(date);
		$("#task-dialog").find("#dueDate").datepicker({
			format: 'mm/dd/yyyy',
			language: datepicker_language,
			autoclose: true
		});
		Taskboard.setOptionByVal("#task-dialog", user);
		Taskboard.setOptionByVal("#priority", priority);

		$("#task-dialog .modal-title").text("Edit Task");
		$("#task-dialog").modal("show");
		Taskboard.changeModalColor(userColor);
	},
	modalAdd: function(storyId) {
		$("#task-dialog input, #task-dialog textarea").not("#sprintId").val("");
		$("#task-dialog #priority").val(0);
		$("#task-dialog #assigned").val($("#task-dialog #assigned").data("default-value"));
		Taskboard.changeModalColor($("#task-dialog #assigned").data("default-color"));
		$("#task-dialog .modal-title").text("Add Task");
		$("#task-dialog form").data("story-id", storyId);
		$("#task-dialog").modal("show");
		$("#task-dialog").find("#dueDate").datepicker({
			format: 'mm/dd/yyyy',
			language: datepicker_language,
			autoclose: true
		});
	},
	changeModalColor: function(userColor) {
		$("#task-dialog .modal-content").css("border", "3px solid " + userColor);
	},
	updateCardPriority: function(priority, card) {
		if(priority === 0) {
			$(card).find('.priority').attr("class", "priority normal");
			$(card).find('.priority').text("Normal");
		} else if(priority < 0) {
			$(card).find('.priority').attr("class", "priority low");
			$(card).find('.priority').text("Low");
		} else if(priority > 0) {
			$(card).find('.priority').attr("class", "priority high");
			$(card).find('.priority').text("High");
		}
	},
	changeUser: function(selected) {
		var color = $(selected).find("option:selected").attr("data-color");
		Taskboard.changeModalColor(color);
	},
	setOptionByText: function(selectId, text) {
		$(selectId + " option").filter(function() {
			return $(this).text() == text;
		}).prop('selected', true);
	},
	setOptionByVal: function(selectId, val) {
		$(selectId + " option").filter(function() {
			return $(this).attr("value") == val;
		}).prop('selected', true);
	},
	updateCard: function(card, data) {
		$(card).find(".title").text(data.title);

		if (isNumber(data.hours_spent) && data.burndown && data.hours > 0) {
			$(card).find(".hours").text(parseFloat(data.hours).toFixed(1) - parseFloat(data.hours_spent));
			$(card).find(".hours").show();
		} else if (isNumber(data.hours) && data.hours > 0) {
			$(card).find(".hours").text(parseFloat(data.hours).toFixed(1));
			$(card).find(".hours").show();
		} else {
			$(card).find(".hours").hide();
		}

		$(card).find(".description").text(data.description);
		$(card).find(".dueDate").text(data.dueDate);
		$(card).find(".owner").text($("#task-dialog #assigned option[value='" + data.assigned + "']").first().text()).data('id', data.assigned);
		$(card).css("border-color", $("#task-dialog #assigned option[value='" + data.assigned + "']").first().attr("data-color"));
		Taskboard.updateCardPriority(data.priority, card);
		Taskboard.ajaxUpdateTask(data);
	},
	addCard: function(story, data, storyId) {
		var row = $(story).parents('.tb-row');
		var cell = row.find("td.column-2"); // put new tasks in the new column
		cell.append($(".cloneable:last").clone());
		var card = cell.find(".cloneable:last");

		$(card).find(".title").text(data.title);
		$(card).find(".repeat_cycle").text(data.repeat_cycle);

		if (isNumber(data.hours) && data.hours > 0) {
			$(card).find(".hours").text(parseFloat(data.hours).toFixed(1));
			$(card).find(".hours").show();
		} else {
			$(card).find(".hours").hide();
		}

		$(card).find(".description").text(data.description);
		// $(card).find(".dueDate").text(data.dueDate);
		$(card).find(".owner").text($("#task-dialog #assigned option[value='" + data.assigned + "']").first().text());
		$(card).css("border-color", $("#task-dialog #assigned option[value='" + data.assigned + "']").first().attr("data-color"));
		$(card).removeClass("cloneable");
		$(card).attr("id", "new_task_" + Taskboard.newTaskId);
		Taskboard.updateCardPriority(data.priority, card);

		data.storyId = storyId;
		data.newTaskId = Taskboard.newTaskId;
		Taskboard.ajaxAddTask(data, card);
		Taskboard.newTaskId++;

		card.show();
	},
	TaskboardReceive: function(task) { // if the task changes statuses/stories
		var taskId = $(task).attr("id").replace("task_", ""),
			receiverStatus = $(task).parent().attr("data-status"),
			receiverStory = $(task).parents('.tb-row').attr("data-story-id"),
			data = {
				taskId: taskId,
				receiver: {
					"story": receiverStory,
					"status": receiverStatus
				}
			};
		Taskboard.ajaxSendTaskPosition(data);
	},
	TaskboardSame: function(task, receiverSerialized) {
		var taskId = $(task).attr("id").replace("task_", ""),
			receiverStatus = $(task).parent().attr("data-status"),
			receiverStory = $(task).parents('.tb-row').attr("data-story-id"),
			data = {
				taskId: taskId,
				receiver: {
					"story": receiverStory,
					"status": receiverStatus,
					"sortingOrder": receiverSerialized
				}
			};
		Taskboard.ajaxSendTaskPosition(data);
	},
	ajaxSendTaskPosition: function(data) {
		var taskId = data.taskId;
		Taskboard.block(taskId);
		$.ajax({
			type: "POST",
			url: Taskboard.updateURL + "/" + taskId,
			data: data,
			success: function(data, textStatus, jqXHR) {
				Taskboard.unBlock(taskId);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				Taskboard.unBlock(taskId);
				Taskboard.showError(taskId);
				$("#task_" + taskId).draggable("option", "disabled", true);
			}
		});
	},
	ajaxUpdateTask: function(data) {
		var taskId = data.taskId;
		Taskboard.block(taskId);
		$.ajax({
			type: "POST",
			url: Taskboard.updateURL + "/" + taskId,
			data: data,
			success: function(data, textStatus, jqXHR) {
				Taskboard.unBlock(taskId);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				Taskboard.unBlock(taskId);
				Taskboard.showError(taskId);
				$("#task_" + taskId).draggable("option", "disabled", true);
			}
		});
	},
	ajaxAddTask: function(data, card) {
		var taskId = data.newTaskId;
		Taskboard.newBlock(taskId);
		$.ajax({
			type: "POST",
			url: Taskboard.addURL,
			data: data,
			success: function(data, textStatus, jqXHR) {
				Taskboard.newUnBlock(taskId);
				$(card).find(".task-id").html('<a href="/issues/' + data.taskId + '" target="_blank">' + data.taskId + '</a>');
				$(card).attr("id", "task_" + data.taskId);
				Taskboard.makeDraggable(card);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				Taskboard.newUnBlock(taskId);
				Taskboard.showError(taskId);
				$(card).draggable("option", "disabled", true);
			}
		});
	},
	block: function(taskId) {
		$('#task_' + taskId).append('<div class="spinner"></div>');
	},
	unBlock: function(taskId) {
		setTimeout(function(){$('#task_' + taskId).find('.spinner').remove();}, 500);
	},
	newBlock: function(taskId) {
		$('#new_task_' + taskId).append('<div class="spinner"></div>');
	},
	newUnBlock: function(taskId) {
		$('#new_task_' + taskId).find('.spinner').remove();
	},
	showError: function(taskId) {
		$('#task_' + taskId + ', #new_task_' + taskId).css({
			"opacity": ".8"
		}).append('<div class="error text-danger" title="An error occured while saving the task."><span class="glyphicon glyphicon-floppy-remove"></span></div>');
	}
};

function isNumber(n) {
	return !isNaN(parseFloat(n)) && isFinite(n);
}

function checkLength(o, n, min, max) {
	if (o.val().length > max || o.val().length < min) {
		if(o.parents(".form-group").length) {
			o.parents(".form-group").addClass("has-error");
		} else {
			o.addClass("has-error");
		}
		return false;
	} else {
		if(o.parents(".form-group").length) {
			o.parents(".form-group").removeClass("has-error");
		} else {
			o.removeClass("has-error");
		}
	}
	return true;
}

function checkRegexp(o, regexp, n) {
	if (!(regexp.test(o.val()))) {
		if(o.parents(".form-group").length) {
			o.parents(".form-group").addClass("has-error");
		} else {
			o.addClass("has-error");
		}
		return false;
	} else {
		if(o.parents(".form-group").length) {
			o.parents(".form-group").removeClass("has-error");
		} else {
			o.removeClass("has-error");
		}
	}
	return true;
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
