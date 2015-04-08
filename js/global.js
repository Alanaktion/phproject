$(document).ready(function() {

	// Initialize tooltips and popovers
	$(".has-tooltip").tooltip();
	$(".has-popover").popover({delay: {show: 500, hide: 100}});

	// Correctly scroll to a given ID, accounting for fixed navigation
	if(window.location.hash) {
		var hashElement = $(window.location.hash);
		if(hashElement.length) {
			$(window).scrollTop(hashElement.offset().top - 100);
			hashElement.fadeTo(400, 0.5).fadeTo(400, 1).fadeTo(400, 0.5).fadeTo(400, 1);
		}
	}

	// Handle custom nested input focusing
	$(".form-control.has-child").click(function(e) {
		$(this).find(".form-control-child input").focus();
		e.stopPropagation();
	});

	// Handle checkboxes on issue listings
	$('.issue-list thead tr input').click(function(e) {
		var checked = $(this).prop('checked');
		$('.issue-list tbody tr input').prop('checked', checked);
		if(checked) {
			$('.issue-list tbody tr').addClass('active');
		} else {
			$('.issue-list tbody tr').removeClass('active');
		}
	});
	$('.issue-list tbody tr input').click(function(e) {
		var checked = $(this).prop('checked');
		if(checked) {
			$(this).parents('tr').addClass('active');
		} else {
			$(this).parents('tr').removeClass('active');
		}
		e.stopPropagation();
	});
	$('.issue-list tbody tr td:first-child').click(function(e) {
		e.stopPropagation();
	});
	$('.issue-list tbody tr').click(function(e) {
		var $checkbox = $(this).find('input'),
			checked = $checkbox.prop('checked');
		if (e.ctrlKey || e.metaKey) {
			$checkbox.prop('checked', !checked);
			if(!checked) {
				$checkbox.parents('tr').addClass('active');
			} else {
				$checkbox.parents('tr').removeClass('active');
			}
		} else {
			$('.issue-list tbody tr td input').prop('checked', false);
			$('.issue-list tbody tr').removeClass('active');
			$checkbox.prop('checked', !checked);
			if(!checked) {
				$checkbox.parents('tr').addClass('active');
			}
		}
		if (document.selection) {
			document.selection.empty();
		} else if (window.getSelection) {
			window.getSelection().removeAllRanges();
		}
	});

	// Add double click on issue listing
	$('.issue-list tbody tr').dblclick(function(e) {
		var id = $(this).data('id');
		if(id) {
			self.location = site_url + 'issues/' + id;
		}
	});

	// Submit from textarea if Ctrl+Enter or Cmd+Enter is pressed
	$('body').on('keypress', 'textarea', function(e) {
		if((e.keyCode == 13 || e.keyCode == 10) && (e.target.type != 'textarea' || (e.target.type == 'textarea' && (e.ctrlKey || e.metaKey)))) {
			$(this).parents('form')[0].submit();
			e.preventDefault();
		}
	});

	// Auto-submit filters when select box is changed
	$('.issue-filters').on('change', 'select, input', function(e) {
		$(this).parents('form').submit();
	});

	// Submit issue sorting options
	$(".issue-sort").on("click",  function(e) {
		e.preventDefault();

		if($("#orderby").val() == $(this).attr('id')) {
			if($("#ascdesc").val() == "desc") {
				$("#ascdesc").val("asc");
			} else {
				$("#ascdesc").val("desc");
			}

		} else {
			$("#orderby").val($(this).attr('id'));
			$("#ascdesc").val("desc");
		}
		$(this).parents('form').submit();
	});

});
