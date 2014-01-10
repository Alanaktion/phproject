$(function() {
	TaskUpdate.init();
});

TaskUpdate = {
	updateURL:'test.json',//AJAX Update Route (id is added dynamically)
        addURL:'test.json',//AJAX Add Route
	taskReceived:false,//used for checking tasks resorted within the same list or to another
        newTaskId:0,//keep track of new tasks in case there are a few error tasks
	init: function(){
		
                //initialize sorting / drag / drop
		$("td.droppable").sortable({
			connectWith: 'td.droppable',
			placeholder: "card task placeholder",
			receive: function(event, ui){
				TaskUpdate.TaskUpdateReceive($(ui.item), $(ui.sender), $(this).sortable('serialize'));
				TaskUpdate.taskReceived = true;//keep from repeating if changed lists
			},
			stop: function(event, ui){
                            if(TaskUpdate.taskReceived !== true){
                                TaskUpdate.TaskUpdateSame($(ui.item), $(this).sortable('serialize'));
                            }
                            else{
                                TaskUpdate.taskReceived = false;
                            }
			}
		}).disableSelection();
		
                //initialize modal double clicks
		$(".card.task").dblclick(function(){
			TaskUpdate.modalEdit($(this));
		});	
                
                //initialize modal window
		$( "#task-dialog" ).dialog({
		  autoOpen: false,
		  height: 600,
		  width: 350,
		  modal: true,
		  buttons: {
			"Update": function() {
				
			 },
			Cancel: function() {
			  $( this ).dialog( "close" );
			}
		  },
		  close: function() {
			
		  }
		});
                
                //initialize add buttons on stories
                $( ".add-task" ).click(function(){
                    TaskUpdate.modalAdd($(this));
                });
                
	},
	modalEdit: function(data){
            user = $(data).find('.owner').text().trim();
            userColor = $(data).css('border-color');
            taskId = $(data).attr('id');
            taskId = taskId.replace('task_', '');
            title = $(data).find('.title').text().trim();
            description = $(data).find('.description').text().trim();
            hours = $(data).find('.hours').text().trim();
            date = $(data).find('.dueDate').text().trim();

            $( "#task-dialog input#taskId" ).val(taskId);
            $( "#task-dialog textarea#title" ).val(title);
            $( "#task-dialog textarea#description" ).val(description);
            $( "#task-dialog input#hours" ).val(hours);
            $( "#task-dialog input#dueDate" ).val(date);
            $( "#task-dialog" ).find( "#dueDate" ).datepicker();
            TaskUpdate.setOptionByText("#task-dialog", user);                
            
            $( "#task-dialog" ).dialog({ title: "Edit Task" });
            $( "#task-dialog" ).dialog( "open" );
            $( "#task-dialog" ).dialog({buttons:{
                   "Update": function() {
                        
                        $('.ui-error').remove();
                        $(".input-error").removeClass(".input-error");
                        var bValid = true;
                        bValid = bValid && isNumber( $( '#hours' ).val() );
                        if(bValid) {
                            TaskUpdate.updateCard(data, $( "form#task-dialog" ).serializeObject());
                            $( "#task-dialog" ).dialog( "close" );
                        }
                        else{
                            $( "#hours" ).before("<label style='color:red;display:block;' class='ui-error'>Value must be a number!</label>");
                            $( "#hours" ).addClass("input-error");
                        }
                       
                        
                     },
                    Cancel: function() {
                      $( this ).dialog( "close" );
                    } 
            }});
            TaskUpdate.changeModalColor(userColor);
        },
        modalAdd: function(data){
            storyId = data.parent().parent().parent().parent().attr("data-story-id");
            $( "#task-dialog input#taskId" ).val("");
            $( "#task-dialog textarea#title" ).val("");
            $( "#task-dialog textarea#description" ).val("");
            $( "#task-dialog input#hours" ).val("");
            $( "#task-dialog input#dueDate" ).val("");
            TaskUpdate.setOptionByText("#task-dialog", "");
            TaskUpdate.changeModalColor("#E7E7E7");
            $( "#task-dialog" ).dialog({ title: "Add Task" });
            $( "#task-dialog" ).dialog( "open" );
            $( "#task-dialog" ).find( "#dueDate" ).datepicker();
            $( "#task-dialog" ).dialog({buttons:{
                   "Update": function() {
                        
                        var bValid = true;
                        bValid = isNumber( $('#hours').val() );
                        console.log(bValid);
                        
                        TaskUpdate.addCard(data, $( "form#task-dialog" ).serializeObject(), storyId);
                        $( "#task-dialog" ).dialog( "close" );
                     },
                    Cancel: function() {
                      $( this ).dialog( "close" );
                    } 
            }});        
        },
        changeModalColor: function(userColor){
            $( ".ui-dialog" ).css( "border", "7px solid "+userColor);
        },
        changeUser: function(selected){
            color = $(selected).find("option:selected").attr("data-color");
            TaskUpdate.changeModalColor(color);
        },
        setOptionByText: function(selectId, text){
            $(selectId+" option").filter(function() {
                return $(this).text() == text;
            }).prop('selected', true);
        },
        getTextByOption: function(selectId, val){
            var text =  $(selectId+" option[value='"+val+"']").text();
            return text;
        },
        updateCard: function(card, data){
            $(card).find( ".title" ).text(data.title);
            
            if(isNumber(data.hours) && data.hours > 0){
                $(card).find( ".hours" ).text(parseFloat(data.hours).toFixed(1));
                $(card).find( ".hours" ).show();
            }
            else{
                $(card).find( ".hours" ).hide();
            }
                        
            $(card).find( ".description" ).text(data.description);
            Test = data;
            $(card).find( ".dueDate" ).text(data.dueDate);
            $(card).find( ".owner" ).text($("#task-dialog option[value='"+data.assigned+"']").text());
            $(card).css("border-color", $("#task-dialog option[value='"+data.assigned+"']").attr("data-color"));            
            TaskUpdate.ajaxUpdateTask(data);
        },
        addCard: function(story, data, storyId){
            var row = $(story).parent().parent().parent().parent();
            var cell = row.find("td.column-2");// put new tasks in the new column
            cell.append($(".cloneable:last").clone());
            card = cell.find(".cloneable:last");
            
            $(card).find( ".title" ).text(data.title);
            
            if(isNumber(data.hours) && data.hours > 0){
                $(card).find( ".hours" ).text(parseFloat(data.hours).toFixed(1));
                $(card).find( ".hours" ).show();
            }
            else{
                $(card).find( ".hours" ).hide();
            }
            
            $(card).find( ".description" ).text(data.description);
            //$(card).find( ".dueDate" ).text(data.dueDate);
            $(card).find( ".owner" ).text($("#task-dialog option[value='"+data.assigned+"']").text());
            $(card).css("border-color", $("#task-dialog option[value='"+data.assigned+"']").attr("data-color"));
            $(card).removeClass("cloneable");
            $(card).attr("id", "new_task_"+TaskUpdate.newTaskId);
            
            data.storyId = storyId;
            data.newTaskId = TaskUpdate.newTaskId;
            TaskUpdate.ajaxAddTask(data, card);
            TaskUpdate.newTaskId++;
            
            card.show();
        },
	TaskUpdateReceive: function (task, sender, receiverSerialized){//if the task changes statuses/stories
		taskId = $(task).attr("id");
		taskId = taskId.replace("task_","");
		receiverStatus = $(task).parent().attr("data-status");
		receiverStory = $(task).parent().parent().attr("data-story-id");
		senderStatus = $(sender).attr("data-status");
		senderStory = $(sender).parent().attr("data-story-id");	
		
		if(typeof(senderStatus) !== "undefined"){
			senderSerialized = $(sender).sortable('serialize');
			
			data = {
				taskId: taskId,
				sender: {"story":senderStory, "status":senderStatus, "sortingOrder":senderSerialized},
				receiver: {"story":receiverStory, "status":receiverStatus, "sortingOrder":receiverSerialized}			
			}			
			
			TaskUpdate.ajaxSendTaskPosition(data);
		}
	},
	TaskUpdateSame: function(task, receiverSerialized){
		taskId = $(task).attr("id");
		taskId = taskId.replace("task_","");
		receiverStatus = $(task).parent().attr("data-status");
		receiverStory = $(task).parent().parent().attr("data-story-id");	
		
		data = {
			taskId: taskId,
			receiver: {"story":receiverStory, "status":receiverStatus, "sortingOrder":receiverSerialized}
		}

		TaskUpdate.ajaxSendTaskPosition(data);
			
	},
	ajaxSendTaskPosition:function(data){
            taskId = data.taskId;
            TaskUpdate.block(taskId);
            console.log("ajaxSendTaskPosition:")
            console.log(data);
            $.ajax({
              type: "POST",
              url: TaskUpdate.updateURL+"/"+taskId,
              data: data,
              success: function(data, textStatus, jqXHR){
                    TaskUpdate.unBlock(taskId);
              },
              error: function(jqXHR, textStatus, errorThrown){
                    TaskUpdate.unBlock(taskId);
                    TaskUpdate.showError(taskId);
              }		  
            });
	},
        ajaxUpdateTask: function(data){
            taskId = data.taskId;
            TaskUpdate.block(taskId);
            console.log("AjaxUpdateTask:");
            console.log(data);
            $.ajax({
              type: "POST",
              url: TaskUpdate.updateURL+"/"+taskId,
              data: data,
              success: function(data, textStatus, jqXHR){
                    TaskUpdate.unBlock(taskId);                    
              },
              error: function(jqXHR, textStatus, errorThrown){
                    TaskUpdate.unBlock(taskId);
                    TaskUpdate.showError(taskId);
              }		  
            });
        },
        ajaxAddTask: function(data, card){
            taskId = data.newTaskId;
            TaskUpdate.newBlock(taskId);
            console.log("AjaxAddTask (this will need to pass bask taskId with new task id) :");
            console.log(data);
            $.ajax({
              type: "POST",
              url: TaskUpdate.addURL,
              data: data,
              success: function(data, textStatus, jqXHR){
                    TaskUpdate.newUnBlock(taskId);
                    $( card ).find(".task-id").text(data.taskId);
                    $( card ).attr("id", "task_"+data.taskId);
                    $(card).dblclick(function(){TaskUpdate.modalEdit($(this));});//add binding for double click on new card only if the id is set
              },
              error: function(jqXHR, textStatus, errorThrown){
                    TaskUpdate.newUnBlock(taskId);
                    TaskUpdate.newShowError(taskId);
              }		  
            });
        },
	block:function(taskId){
		task = $('#task_'+taskId);
		task.append('<div class="spinner"></div>');
	},
	unBlock:function(taskId){
		task = $('#task_'+taskId);
		task.find('.spinner').remove();
	},
        newBlock:function(taskId){
		task = $('#new_task_'+taskId);
		task.append('<div class="spinner"></div>');
	},
	newUnBlock:function(taskId){
		task = $('#new_task_'+taskId);
		task.find('.spinner').remove();
	},
        showError:function(taskId){
            task = $('#task_'+taskId);
            task.css({"opacity":".8"});
            task.append('<div class="error" title="An error occured while saving the task!"></div>');
        },
        newShowError:function(taskId){
            task = $('#new_task_'+taskId);
            task.css({"opacity":".8"});
            task.append('<div class="error" title="An error occured while saving the task!"></div>');
        }
}

function isNumber(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}

function checkLength(o, n, min, max) {
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        updateTips("Length of " + n + " must be between " +
                min + " and " + max + ".");
        return false;
    } else {
        return true;
    }
}

function checkRegexp(o, regexp, n) {
    if (!(regexp.test(o.val()))) {
        o.addClass("ui-state-error");
        updateTips(n);
        return false;
    } else {
        return true;
    }
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