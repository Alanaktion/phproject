$(function() {
	$("#bulk-submit").click(function() {
		$('#form1 :input[isacopy]').remove();
		$('.filter-form :checkbox:checked').not(':submit').clone().hide().attr('isacopy','y').appendTo('#bulk-form');
	});

	$('#due_date, #start_date').datepicker({
		format: 'yyyy-mm-dd',
		language: datepicker_language,
		orientation: 'top auto',
		clearBtn: true,
		autoclose: true
	});
});
