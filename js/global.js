$(document).ready(function() {

	// Tooltips and popovers
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

	// Auto-submit filters when select box is changed
	$('.issue-filters').on('change', 'select, input', function(e) {
		$(this).parents('form').submit();
	});

	// Submit issue sorting options
	$(".issue-sort").on("click", function(e) {
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

	// Show Mac hotkeys on Macs
	if(navigator.platform.indexOf('Mac') >= 0) {
		var $modalBody = $("#modal-hotkeys .modal-body");
		$modalBody.html($modalBody.html().replace(/alt\+/g, '&#8997;').replace(/ctrl\+/g, '&#8984;').replace(/enter/g, '&#8617;').replace(/shift\+/g, '&#8679;'));
	}

	// Submit from textarea if Ctrl+Enter or Cmd+Enter is pressed
	$('body').on('keypress', 'textarea', function(e) {
		if((e.keyCode == 13 || e.keyCode == 10) && (e.target.type != 'textarea' || (e.target.type == 'textarea' && (e.ctrlKey || e.metaKey)))) {
			$(this).parents('form')[0].submit();
			e.preventDefault();
		}
	});

	// Bind keyup to hotkey handler
	$(document).on('keyup', function(e) {
		// Only handle hotkeys when not in a form context
		if(e.target.type != 'textarea' && e.target.tagName != 'INPUT' && e.target.tagName != 'SELECT' && (e.target.className != 'modal in' || e.which == 191)) {
			switch (e.which) {
				case 191: // Hotkey modal
					if(e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey) {
						$('.modal.in').not('#modal-hotkeys').modal('hide');
						$('#modal-hotkeys').modal('toggle');
					}
					break;
				case 66: // Browse
					if(e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey) {
						window.location = site_url + 'issues?status=open';
					}
					break;
				case 87: // Watch/unwatch issue
					if(!e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey) {
						$("#watch-btn").click();
					}
					break;
				case 69: // Edit issue
					if(!e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey) {
						$("#btn-edit").click();
					}
					break;
				case 67:
					if(e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey && $("#btn-issue-close").length) { // Close issue
						window.location = $("#btn-issue-close").attr("href");
					} else if(!e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey) { // Comment on issue
						$("#comment_textarea").focus();
					}
					break;
				case 82:
					if(e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey && $("#btn-issue-reopen").length) { // Reopen issue
						window.location = $("#btn-issue-reopen").attr("href");
					} else if(!e.shiftKey && !e.metaKey && !e.ctrlKey && !e.altKey) { // Reload
						window.location.reload();
					}
					break;
				default:
					if(!e.shiftKey && !e.ctrlKey && e.altKey && issue_types.indexOf(e.which - 48) >= 0) {
						window.location = site_url + 'issues/new/' + (e.which - 48);
					}
			}
		}
	});

	// Start session helper
	Session.init();

});


/**
 * Session helper
 * @type {Object}
 */
var Session = {

	pingInterval: 5,

	/**
	 * Initialize session pings
	 */
	init: function() {
		Intercom.getInstance().on('pingResponse', Session.pingResponse);
		setInterval(Session.pingWrapper, Session.pingInterval * 1000);
	},

	/**
	 * Recurring ping request wrapper
	 */
	pingWrapper: function() {
		if(Intercom.supported) {
			Intercom.getInstance().once('sessionPing', Session.ping, Session.pingInterval);
		} else {
			Session.ping();
		}
	},

	/**
	 * Send Ping request
	 */
	ping: function() {
		$.get(site_url + 'ping', function(data) {
			if(Intercom.supported) {
				Intercom.getInstance().emit('pingResponse', data);
			} else {
				Session.pingResponse(data);
			}
		});
	},

	/**
	 * Handle ping responses
	 */
	pingResponse: function(data) {
		if(data.is_logged_in && $('#modal-loggedout.in').length) {
			$('#modal-loggedout').modal('hide');
		}
		if(!data.is_logged_in && !$('#modal-loggedout.in').length) {
			$('.modal.in').modal('hide');
			$('#modal-loggedout').modal('show');
		}
	}

};
