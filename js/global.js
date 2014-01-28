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
            $('.issue-list tbody tr td input').prop('checked', false);
            $checkbox.prop('checked', true);
        }
    });

    // Add double click on issue listing
    $('.issue-list tbody tr').dblclick(function(e) {
        self.location = '/issues/' + $(this).data('id');
    });

});
