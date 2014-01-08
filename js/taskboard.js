$(function() {
	taskUpdate.init();
});

taskUpdate = {
	updateURL:'test.json',//AJAX File
	taskReceived:false,//used for checking tasks resorted within the same list or to another
	init: function(){
		//initialize sorting / drag / drop
		$("td.droppable").sortable({
			connectWith: 'td.droppable',
			placeholder: "card task placeholder",
			receive: function(event, ui){
				taskUpdate.updateTaskReceive($(ui.item), $(ui.sender), $(this).sortable('serialize'));
				taskUpdate.taskReceived = true;//keep from repeating if changed lists
			},
			stop: function(event, ui){
				if(taskUpdate.taskReceived !== true){
					taskUpdate.updateTaskSame($(ui.item), $(this).sortable('serialize'));			
				}
				else{
					taskUpdate.taskReceived = false;
				}
			}
		}).disableSelection();
		
		//initialize modal double clicks
		$(".card.task").dblclick(function(){
			taskUpdate.modalUpdate($(this));
		});		
                //initialize modal window
		$( "#task-dialog" ).dialog({
		  autoOpen: false,
		  height: 550,
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
			//allFields.val( "" ).removeClass( "ui-state-error" );
		  }
		});
	},
	modalUpdate: function(data){
		
                $( ".date" ).datepicker();
                taskId = $(data).attr('id');
		taskId = taskId.replace('task_', '');
		$( "#task-dialog" ).dialog( "open" );                
	},
	updateTaskReceive: function (task, sender, receiverSerialized){//if the task changes statuses/stories
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
			
			taskUpdate.ajaxSendTaskPosition(data);
		}
	},
	updateTaskSame: function(task, receiverSerialized){
		taskId = $(task).attr("id");
		taskId = taskId.replace("task_","");
		receiverStatus = $(task).parent().attr("data-status");
		receiverStory = $(task).parent().parent().attr("data-story-id");	
		
		data = {
			taskId: taskId,
			receiver: {"story":receiverStory, "status":receiverStatus, "sortingOrder":receiverSerialized}
		}

		taskUpdate.ajaxSendTaskPosition(data);
			
	},
	ajaxSendTaskPosition:function(data){
		taskId = data.taskId;
		taskUpdate.block(taskId);
		$.ajax({
		  type: "POST",
		  url: taskUpdate.updateURL,
		  data: data,
		  success: function(data, textStatus, jqXHR){
			taskUpdate.unBlock(taskId);
		  },
		  error: function(jqXHR, textStatus, errorThrown){
			taskUpdate.unBlock(taskId);
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
	}
}