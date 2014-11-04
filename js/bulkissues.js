$("#bulk-submit").click(function() {

	$('#form1 :input[isacopy]').remove();

	$('#filter :checkbox:checked').not(':submit').clone().hide().attr('isacopy','y').appendTo('#bulk-form');
	console.log($("#bulk-form").serializeArray());
	//$( "#bulk-update" ).submit();
});

$(function() {
	var nowTemp = new Date();
	var now = new Date(nowTemp.getFullYear(), nowTemp.getMonth(), nowTemp.getDate(), 0, 0, 0, 0);
	$('#due_date').datepicker({
		format: 'yyyy-mm-dd',
		/*onRender: function(date) {
			return date.valueOf() < now.valueOf() ? 'disabled' : '';
		}*/
	});
});
