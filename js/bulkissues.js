$("#bulk-submit").click(function() {
	$('#form1 :input[isacopy]').remove();
	$('#filter :checkbox:checked').not(':submit').clone().hide().attr('isacopy','y').appendTo('#bulk-form');
});

$(function() {
	var nowTemp = new Date();
	var now = new Date(nowTemp.getFullYear(), nowTemp.getMonth(), nowTemp.getDate(), 0, 0, 0, 0);
	$('#due_date, #start_date').datepicker({
		format: 'yyyy-mm-dd',
	});
});
