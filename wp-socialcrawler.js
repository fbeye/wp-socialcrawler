(function($) {
    $('form#wpsc_form').on('submit', function () {
        var self = this;
        if ($(self).find('.submit input').hasClass('button-disabled')) {
            return false;
        }

        $(self).find('.spinner').css('display', 'inline-block');
        $(self).find('.submit input').addClass('button-disabled');
        $.ajax({
            url: ajaxurl,
            data: $(self).serializeArray()
        })
        .then(function (result) {
            console.log(result);
            $(self).find('.spinner').css('display', 'none');
            $(self).find('.submit input').removeClass('button-disabled');
            location.reload();
        });
        return false;
    });

    if (wpsc_page && wpsc_page.tab === 'logs' && $('textarea').length > 0) {
        var textarea = $('textarea')[0];
        textarea.scrollTop = textarea.scrollHeight;
    }
})(jQuery);
