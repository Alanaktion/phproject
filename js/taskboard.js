/*jslint browser: true, ass: true, debug: true, eqeq: true, newcap: true, nomen: true, plusplus: true, unparam: true, sloppy: true, sub: true, vars: true, white: true */

$(function() {
	Taskboard.init();
});

var Taskboard = {
	updateURL: '/taskboard/edit', //AJAX Update Route (id is added dynamically)
	addURL: '/taskboard/add', //AJAX Add Route
	taskReceived: false, //used for checking tasks resorted within the same list or to another
	newTaskId: 0, //keep track of new tasks in case there are a few error tasks
	init: function() {

		//initialize drag / drop
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
						stack: ".card.task"
					})
				);
				Taskboard.TaskboardReceive($(ui.draggable));
			}
		});

		//initialize issue editing handler
		$("#taskboard").on("click", ".card a", function(e) {
			e.stopPropagation();
		});
		$(".card.task").click(function(e) {
			Taskboard.modalEdit($(this));
		});

		//initialize modal window
		$("#task-dialog").dialog({
			autoOpen: false,
			height: 430,
			width: 550,
			modal: true,
			buttons: {
				Cancel: function() {
					$(this).dialog("close");
				},
				"Save": function() {

				}
			},
			close: function() {

			}
		});

		//temporary fix until moving to bootstrap modal
		$('.ui-dialog .ui-dialog-buttonset button:last').attr("class", "btn btn-danger btn-xs");
		$('.ui-dialog .ui-dialog-buttonset button:first').attr("class", "btn btn-default btn-sm");

		//initialize add buttons on stories
		$(".add-task").click(function() {
			Taskboard.modalAdd($(this));
		});

	},
	makeDraggable: function(card) {
		$(card).draggable({
			//helper: "clone",
			cursoer: "move",
			containment: "#task-table",
			revert: "invalid",
			stack: ".card.task",
			start: function() {
				$(this).css("opacity", ".5");
			},
			stop: function() {
				$(this).css("opacity", "1");
			}
		});
	},
	modalEdit: function(data) {
		var user = $(data).find('.owner').text().trim(),
			userColor = $(data).css('border-color'),
			taskId = $(data).attr('id').replace('task_', ''),
			title = $(data).find('.title').text().trim(),
			description = $(data).find('.description').text().trim(),
			hours = $(data).find('.hours').text().trim(),
			date = $(data).find('.dueDate').text().trim(),
			priority = $(data).find('.priority').data('value');

		Taskboard.changeModalPriority(priority);
		$("#task-dialog input#taskId").val(taskId);
		$("#task-dialog input#title").val(title);
		$("#task-dialog textarea#description").val(description);
		$("#task-dialog input#hours").val(hours);
		$("#task-dialog input#dueDate").val(date);
		$("#task-dialog").find("#dueDate").datepicker();
		Taskboard.setOptionByText("#task-dialog", user);
		Taskboard.setOptionByText("#priority", priority);

		$("#task-dialog").dialog({
			title: "Edit Task"
		});
		$("#task-dialog").dialog("open");
		$("#task-dialog").dialog({
			buttons: {
				Cancel: function() {
					$(this).dialog("close");
				},
				"Save": function() {
					$('.ui-error').remove();
					$(".input-error").removeClass(".input-error");
					if ($('#hours').val() == '' || isNumber($('#hours').val())) {
						Taskboard.updateCard(data, $("form#task-dialog").serializeObject());
						$("#task-dialog").dialog("close");
					} else {
						$("#hours").before("<label style='color:red;display:block;' class='ui-error'>Value must be a number!</label>");
						$("#hours").addClass("input-error");
					}
				}
			}
		});
		Taskboard.changeModalColor(userColor);
	},
	modalAdd: function(data) {
		var storyId = data.parents('.tb-row').attr("data-story-id");
		Taskboard.changeModalPriority("normal");
		$("#task-dialog input").val("");
		$("#task-dialog textarea").val("");
		$("#task-dialog #priority").val($("#task-dialog #priority option:first").val());
		Taskboard.setOptionByText("#task-dialog", "");
		Taskboard.changeModalColor("#E7E7E7");
		$("#task-dialog").dialog({
			title: "Add Task"
		});
		$("#task-dialog").dialog("open");
		$("#task-dialog").find("#dueDate").datepicker();
		$("#task-dialog").dialog({
			buttons: {
				Cancel: function() {
					$(this).dialog("close");
				},
				"Save": function() {
					Taskboard.addCard(data, $("form#task-dialog").serializeObject(), storyId);
					$("#task-dialog").dialog("close");
				}
			}
		});
	},
	changeModalColor: function(userColor) {
		$(".ui-dialog").css("border", "7px solid " + userColor);
	},
	changeModalPriority: function(priority) {
		/*if(priority == 0) {
			$('.ui-dialog-title').css("color", Taskboard.priorityColors.normal);
		} elseif(priority < 0) {
			$('.ui-dialog-title').css("color", Taskboard.priorityColors.low);
		} elseif(priority > 0) {
			$('.ui-dialog-title').css("color", Taskboard.priorityColors.high);
		}*/
	},
	updateCardPriority: function(priority, card) {
		if(priority == 0) {
			$(card).find('.priority').attr("class", "priority normal");
			$(card).find('.priority').text("Normal Priority");
		} else if(priority < 0) {
			$(card).find('.priority').attr("class", "priority low");
			$(card).find('.priority').text("Low Priority");
		} else if(priority > 0) {
			$(card).find('.priority').attr("class", "priority high");
			$(card).find('.priority').text("High Priority");
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
	updateCard: function(card, data) {
		$(card).find(".title").text(data.title);

		if (isNumber(data.hours) && data.hours > 0) {
			$(card).find(".hours").text(parseFloat(data.hours).toFixed(1));
			$(card).find(".hours").show();
		} else {
			$(card).find(".hours").hide();
		}

		$(card).find(".description").text(data.description);
		$(card).find(".dueDate").text(data.dueDate);
		$(card).find(".owner").text($("#task-dialog #assigned option[value='" + data.assigned + "']").first().text());
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
	TaskboardReceive: function(task) { //if the task changes statuses/stories
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
				$(card).click(function() {
					//add binding for click on new card only if the id is set
					Taskboard.modalEdit($(this));
				});
				Taskboard.makeDraggable(card);
			},
			error: function(jqXHR, textStatus, errorThrown) {
				Taskboard.newUnBlock(taskId);
				Taskboard.newShowError(taskId);
				$(card).draggable("option", "disabled", true);
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
		$('#task_' + taskId).css({
			"opacity": ".8"
		}).append('<div class="error" title="An error occured while saving the task!"></div>');
	},
	newShowError: function(taskId) {
		$('#new_task_' + taskId).css({
			"opacity": ".8"
		}).append('<div class="error" title="An error occured while saving the task!"></div>');
	}
};

function isNumber(n) {
	return !isNaN(parseFloat(n)) && isFinite(n);
}

function checkLength(o, n, min, max) {
	if (o.val().length > max || o.val().length < min) {
		o.addClass("ui-state-error");
		// updateTips("Length of " + n + " must be between " min + " and " + max + "."); // updateTips() function does not exist
		return false;
	}
	return true;
}

function checkRegexp(o, regexp, n) {
	if (!(regexp.test(o.val()))) {
		o.addClass("ui-state-error");
		//updateTips(n); // updateTips() function does not exist
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

		if (this.value != null) {
			value = this.value;
		} else {
			value = '';
		}

		if (objectData[this.name] != null) {
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
