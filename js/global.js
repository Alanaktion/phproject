/* globals $ BASE Mousetrap issue_types */
$(function() {

	// Tooltips and popovers
	$('.has-tooltip').tooltip();
	$('.has-popover').popover({delay: {show: 500, hide: 100}});

	// Correctly scroll to a given ID, accounting for fixed navigation
	if(window.location.hash) {
		var hashElement = $(window.location.hash);
		if(hashElement.length) {
			$(window).scrollTop(hashElement.offset().top - 100);
			hashElement.fadeTo(400, 0.5).fadeTo(400, 1).fadeTo(400, 0.5).fadeTo(400, 1);
		}
	}

	// Handle checkboxes on issue listings
	$('.issue-list thead tr input').click(function(e) {
		var checked = $(this).prop('checked');
		$('.issue-list tbody tr input').prop('checked', checked);
		if(checked) {
			$('.issue-list tbody tr').addClass('active');
		} else {
			$('.issue-list tbody tr').removeClass('active');
		}
		e.stopPropagation();
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
		if(e.which !== 1) {
			return;
		}
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
	$('.issue-list tbody tr input').dblclick(function(e) {
		e.stopPropagation();
	});
	$('.issue-list tbody tr').dblclick(function() {
		var id = $(this).data('id');
		if(id) {
			self.location = BASE + '/issues/' + id;
		}
	});

	// Auto-submit filters when select box is changed
	$('.issue-filters').on('change', 'select, input', function() {
		$(this).parents('form').submit();
	});

	// Submit issue sorting options
	$('.issue-sort').on('click', function(e) {
		var $form = $(this).parents('form');
		e.preventDefault();

		if($form.find('input[name=orderby]').val() == $(this).attr('id')) {
			if($form.find('input[name=ascdesc]').val() == 'desc') {
				$form.find('input[name=ascdesc]').val('asc');
			} else {
				$form.find('input[name=ascdesc]').val('desc');
			}
		} else {
			$form.find('input[name=orderby]').val($(this).attr('id'));
			$form.find('input[name=ascdesc]').val('desc');
		}
		$(this).parents('form').submit();
	});

	// Show Mac hotkeys on Macs
	if(navigator.platform.indexOf('Mac') >= 0) {
		var $modalBody = $('#modal-hotkeys .modal-body');
		$modalBody.html($modalBody.html().replace(/alt\+/g, '&#8997;').replace(/ctrl\+/g, '&#8984;').replace(/enter/g, '&#8617;').replace(/shift\+/g, '&#8679;'));
	}

	// Show correct accesskey modifier
	if(navigator.platform.indexOf('Mac') >= 0) {
		$('.accesskey-modifier').html('&#8984;&#8997;');
	} else if(navigator.userAgent.indexOf('Firefox') >= 0) {
		$('.accesskey-modifier').text('alt+shift');
	}

	// Submit from textarea if Ctrl+Enter or Cmd+Enter is pressed
	$('body').on('keypress', 'textarea', function(e) {
		if((e.keyCode == 13 || e.keyCode == 10) && (e.target.type != 'textarea' || (e.target.type == 'textarea' && (e.ctrlKey || e.metaKey)))) {
			$(this).closest('form').submit();
			e.preventDefault();
		}
	});

	// Bind default hotkeys
	Mousetrap.bind('?', function() {
		$('.modal.in').not('#modal-hotkeys').modal('hide');
		$('#modal-hotkeys').modal('toggle');
	});
	Mousetrap.bind('/', function() {
		$('.navbar-form input[type=search]').focus();
	});
	Mousetrap.bind('shift+b', function() {
		window.location = BASE + '/issues?status=open';
	});
	Mousetrap.bind('w', function() {
		$('#watch-btn').click();
	});
	Mousetrap.bind('e', function() {
		$('#btn-edit').click();
	});
	Mousetrap.bind('shift+c', function() {
		window.location = $('#btn-issue-close').attr('href');
	});
	Mousetrap.bind('c', function() {
		$('#comment_textarea').focus();
	});
	Mousetrap.bind('shift+r', function() {
		window.location = $('#btn-issue-reopen').attr('href');
	});
	Mousetrap.bind('r', function() {
		window.location.reload();
	});
	$.each(issue_types, function(key, type) {
		var typeId = type;
		Mousetrap.bind('n ' + typeId, function() {
			window.location = BASE + '/issues/new/' + typeId;
		});
	});

	// Allow "esc" to clear form focus
	$(document).on('keyup', function(e) {
		if(e.which == 27) {
			let $el = $(document.activeElement);
			if ($el.filter('input,select,textarea').length) {
				// Clear focus on a form element
				$el.blur();
			}
		}
	});

});

/**
 * Show an error message from a client-side action
 * @param {string} message
 */
function showAlert(message, type)
{
	if (!type) {
		type = 'danger';
	}
	var $alertContainer = $('<div />').addClass('container');
	var $alert = $('<div />').addClass('alert alert-' + type + ' alert-dismissable').text(message);
	$alert.prepend('<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>');
	$alertContainer.append($alert);
	$('.navbar').first().after($alertContainer);
}
