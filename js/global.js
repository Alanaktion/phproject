$(document).ready(function(){
	// Initialize tooltips and popovers
	$(".has-tooltip").tooltip();
	$(".has-popover").popover();

	// Handle checkboxes on issue listings
	$('.issue-list tbody tr input').click(function(e) {
		e.stopPropagation();
	});
	$('.issue-list tbody tr').click(function(e) {
		var $checkbox = $(this).find('input');
		if (e.ctrlKey) {
			$checkbox.prop('checked', !$checkbox.prop('checked'));
		} else {
			var checked = $checkbox.prop('checked');
			$('.issue-list tbody tr td input').prop('checked', false);
			//$checkbox.prop('checked', false);
			$checkbox.prop('checked', !checked);
		}
	});

	// Add double click on issue listing
	$('.issue-list tbody tr').dblclick(function(e) {
		self.location = '/issues/' + $(this).data('id');
	});

	// Auto-submit when select box is changed
	$('.issue-filters').on('change', 'select, input', function(e) {
		$(this).parents('form').submit();
	});

});
