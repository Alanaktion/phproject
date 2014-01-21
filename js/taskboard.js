$(function() {
	Taskboard.init();
});

Taskboard = {
	updateURL:'test.json',//AJAX Update Route (id is added dynamically)
        addURL:'test.json',//AJAX Add Route
	taskReceived:false,//used for checking tasks resorted within the same list or to another
        newTaskId:0,//keep track of new tasks in case there are a few error tasks
        init: function(){
                
                
		
                //initialize sorting / drag / drop
		
                /****** SORTABLE ********/
                /*$("td.droppable").sortable({
			connectWith: 'td.droppable',
			placeholder: "card task placeholder",
			receive: function(event, ui){
				Taskboard.TaskboardReceive($(ui.item), $(ui.sender), $(this).sortable('serialize'));
				Taskboard.taskReceived = true;//keep from repeating if changed lists
			},
			stop: function(event, ui){
                            if(Taskboard.taskReceived !== true){
                                Taskboard.TaskboardSame($(ui.item), $(this).sortable('serialize'));
                            }
                            else{
                                Taskboard.taskReceived = false;
                            }
			}
		}).disableSelection();*/
            
                Taskboard.makeDraggable($(".card.task"))

                $( ".droppable" ).droppable({
                    accept: ".card.task",
                    over: function(){
                            $(this).append('<div class="card task placeholder"></div>');
                    },
                    out: function () {
                            $(this).find('.placeholder').remove();
                    },
                    drop: function(event, ui){
                            $(this).find('.placeholder').remove();
                            $(this).append(
                                    $(ui.draggable)
                                    .css(
                                            {
                                                    "position":"",
                                                    "left":"", 
                                                    "top":""
                                            }
                                    ).
                                    draggable({
                                            revert: "invalid", 
                                            stack: ".card.task"
                                    })
                            );
                            Taskboard.TaskboardReceive($(ui.draggable));
                    }
		
                });
		
                //initialize modal double clicks
		$(".card.task").dblclick(function(){
			Taskboard.modalEdit($(this));
		});	
                
                //initialize modal window
		$( "#task-dialog" ).dialog({
		  autoOpen: false,
		  height: 430,
		  width: 550,
		  modal: true,
		  buttons: {
			Cancel: function() {
			  $( this ).dialog( "close" );
			},
                        "Update": function() {
				
			 }			
		  },
		  close: function() {
			
		  }
		});
                
                //temporary fix until moving to bootstrap modal
                $('.ui-dialog .ui-dialog-buttonset button:last').attr("class", "btn btn-danger btn-xs");
                $('.ui-dialog .ui-dialog-buttonset button:first').attr("class", "btn btn-default btn-sm");
                
                //initialize add buttons on stories
                $( ".add-task" ).click(function(){
                    Taskboard.modalAdd($(this));
                });
                
	},
        makeDraggable: function(card){
            $(card).draggable({
                //helper: "clone",
                cursoer: "move",
                containment: "#task-table",
                revert: "invalid",
                stack: ".card.task",
                start:function(){
                    $(this).css("opacity",".5");
                },
                stop:function(){
                    $(this).css("opacity","1");
                }
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
            priority = $(data).find('.priority').text().trim();
            console.log(priority);

            Taskboard.changeModalPriority(priority);
            $( "#task-dialog input#taskId" ).val(taskId);
            $( "#task-dialog textarea#title" ).val(title);
            $( "#task-dialog textarea#description" ).val(description);
            $( "#task-dialog input#hours" ).val(hours);
            $( "#task-dialog input#dueDate" ).val(date);
            $( "#task-dialog" ).find( "#dueDate" ).datepicker();
            Taskboard.setOptionByText("#task-dialog", user);   
            Taskboard.setOptionByText("#priority", priority);
            
            $( "#task-dialog" ).dialog({ title: "Edit Task" });
            $( "#task-dialog" ).dialog( "open" );
            $( "#task-dialog" ).dialog({buttons:{
                   Cancel: function() {
                      $( this ).dialog( "close" );
                    },
                    "Update": function() {
                        
                        $('.ui-error').remove();
                        $(".input-error").removeClass(".input-error");
                        var bValid = true;
                        bValid = bValid && isNumber( $( '#hours' ).val() );
                        if(bValid) {
                            console.log('here');
                            console.log($( "form#task-dialog" ).serializeObject());
                            Taskboard.updateCard(data, $( "form#task-dialog" ).serializeObject());
                            $( "#task-dialog" ).dialog( "close" );
                        }
                        else{
                            $( "#hours" ).before("<label style='color:red;display:block;' class='ui-error'>Value must be a number!</label>");
                            $( "#hours" ).addClass("input-error");
                        }
                       
                        
                     }
                    
            }});
            Taskboard.changeModalColor(userColor);
        },
        modalAdd: function(data){
            Taskboard.changeModalPriority("normal");
            storyId = data.parent().parent().parent().parent().attr("data-story-id");
            $( "#task-dialog input#taskId" ).val("");
            $( "#task-dialog textarea#title" ).val("");
            $( "#task-dialog textarea#description" ).val("");
            $( "#task-dialog input#hours" ).val("");
            $( "#task-dialog input#dueDate" ).val("");
            $("#task-dialog #priority").val($("#task-dialog #priority option:first").val());
            Taskboard.setOptionByText("#task-dialog", "");
            Taskboard.changeModalColor("#E7E7E7");
            $( "#task-dialog" ).dialog({ title: "Add Task" });
            $( "#task-dialog" ).dialog( "open" );
            $( "#task-dialog" ).find( "#dueDate" ).datepicker();
            $( "#task-dialog" ).dialog({buttons:{
                    Cancel: function() {
                      $( this ).dialog( "close" );
                    }, 
                    "Update": function() {
                        
                        var bValid = true;
                        bValid = isNumber( $('#hours').val() );
                        console.log(bValid);
                        
                        Taskboard.addCard(data, $( "form#task-dialog" ).serializeObject(), storyId);
                        $( "#task-dialog" ).dialog( "close" );
                     }                    
            }});        
        },
        changeModalColor: function(userColor){
            $( ".ui-dialog" ).css( "border", "7px solid "+userColor);
        },
        changeModalPriority: function(priority){
            
           /*switch(priority){
                case "normal":
                    $( '.ui-dialog-title' ).css("color", Taskboard.priorityColors.normal);
                    break;
                case "low":
                    $( '.ui-dialog-title' ).css("color", Taskboard.priorityColors.low);
                    break;                
                case "high":
                    $( '.ui-dialog-title' ).css("color", Taskboard.priorityColors.high);
                    break;
                default:
                    console.log("no valid priority was set");
                    break;
            }*/            
        },
        updateCardPriority: function(priority, card){
            console.log(priority);
            switch(priority){
                case "normal":
                    $( card).find( '.priority' ).attr("class", "priority normal");
                    $( card ).find('.priority').text("Normal Priority");
                    break;
                case "low":
                    $( card).find( '.priority' ).attr("class", "priority low");
                    $( card ).find('.priority').text("Low Priority");
                    break;                
                case "high":
                    $( card).find( '.priority' ).attr("class", "priority high");
                    $( card ).find('.priority').text("High Priority");
                    break;
                default:
                    console.log("no valid priority was set on Taskboard.updateCardPriority");
                    break;
            }  
        },
        changeUser: function(selected){
            color = $(selected).find("option:selected").attr("data-color");
            Taskboard.changeModalColor(color);
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
            $(card).find( ".dueDate" ).text(data.dueDate);
            $(card).find( ".owner" ).text($("#task-dialog option[value='"+data.assigned+"']").text());
            $(card).css("border-color", $("#task-dialog option[value='"+data.assigned+"']").attr("data-color"));
            Taskboard.updateCardPriority(data.priority, card);
            console.log(data);
            console.log("PRIORITY:"+data.priority);
            Taskboard.ajaxUpdateTask(data);
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
            $(card).attr("id", "new_task_"+Taskboard.newTaskId);
            Taskboard.updateCardPriority(data.priority, card);
            
            data.storyId = storyId;
            data.newTaskId = Taskboard.newTaskId;
            Taskboard.ajaxAddTask(data, card);
            Taskboard.newTaskId++;
            
            card.show();            
        },
	TaskboardReceive: function (task){//if the task changes statuses/stories
            taskId = $(task).attr("id");
            taskId = taskId.replace("task_","");
            receiverStatus = $(task).parent().attr("data-status");
            receiverStory = $(task).parent().parent().attr("data-story-id");

            data = {
                    taskId: taskId,
                    receiver: {"story":receiverStory, "status":receiverStatus}
            }			
                
            Taskboard.ajaxSendTaskPosition(data);
                
		
	},
        /* *** SORTABLE ****************
        TaskboardReceive: function (task, sender, receiverSerialized){//if the task changes statuses/stories
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
			
			Taskboard.ajaxSendTaskPosition(data);
		}
	},*/
	TaskboardSame: function(task, receiverSerialized){
		taskId = $(task).attr("id");
		taskId = taskId.replace("task_","");
		receiverStatus = $(task).parent().attr("data-status");
		receiverStory = $(task).parent().parent().attr("data-story-id");	
		
		data = {
			taskId: taskId,
			receiver: {"story":receiverStory, "status":receiverStatus, "sortingOrder":receiverSerialized}
		}

		Taskboard.ajaxSendTaskPosition(data);
			
	},
	ajaxSendTaskPosition:function(data){
            taskId = data.taskId;
            Taskboard.block(taskId);
            console.log("ajaxSendTaskPosition:")
            console.log(data);
            $.ajax({
              type: "POST",
              url: Taskboard.updateURL+"/"+taskId,
              data: data,
              success: function(data, textStatus, jqXHR){
                    Taskboard.unBlock(taskId);
              },
              error: function(jqXHR, textStatus, errorThrown){
                    Taskboard.unBlock(taskId);
                    Taskboard.showError(taskId);
                    $( "#task_"+taskId ).draggable( "option", "disabled", true );
              }		  
            });
	},
        ajaxUpdateTask: function(data){
            taskId = data.taskId;
            Taskboard.block(taskId);
            console.log("AjaxUpdateTask:");
            console.log(data);
            $.ajax({
              type: "POST",
              url: Taskboard.updateURL+"/"+taskId,
              data: data,
              success: function(data, textStatus, jqXHR){
                    Taskboard.unBlock(taskId);                    
              },
              error: function(jqXHR, textStatus, errorThrown){
                    Taskboard.unBlock(taskId);
                    Taskboard.showError(taskId);
                    $( "#task_"+taskId ).draggable( "option", "disabled", true );
              }		  
            });
        },
        ajaxAddTask: function(data, card){
            taskId = data.newTaskId;
            Taskboard.newBlock(taskId);
            console.log("AjaxAddTask (this will need to pass bask taskId with new task id) :");
            console.log(data);
            $.ajax({
              type: "POST",
              url: Taskboard.addURL,
              data: data,
              success: function(data, textStatus, jqXHR){
                    Taskboard.newUnBlock(taskId);
                    $( card ).find(".task-id").text(data.taskId);
                    $( card ).attr("id", "task_"+data.taskId);
                    $(card).dblclick(function(){Taskboard.modalEdit($(this));});//add binding for double click on new card only if the id is set
                    Taskboard.makeDraggable(card);
              },
              error: function(jqXHR, textStatus, errorThrown){
                    Taskboard.newUnBlock(taskId);
                    Taskboard.newShowError(taskId);
                    $( card ).draggable( "option", "disabled", true );
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