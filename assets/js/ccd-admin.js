/**
 * Gateway settings: "Select image" button for the QR code URL field.
 */
jQuery(function ($) {
    $('input.ccd-qr-code-url').each(function () {
        var $input = $(this);
        var $preview = $('<img style="display:block;max-width:120px;margin-top:8px;">');
        var $btn = $('<button type="button" class="button" style="margin-left:6px;"></button>').text(ccdAdmin.select);
        var frame;

        var refresh = function () {
            var url = $input.val();
            if (url) {
                $preview.attr('src', url).show();
            } else {
                $preview.hide();
            }
        };

        $input.after($btn);
        $btn.after($preview);
        $input.on('change input', refresh);
        refresh();

        $btn.on('click', function (e) {
            e.preventDefault();
            if (!frame) {
                frame = wp.media({ title: ccdAdmin.title, library: { type: 'image' }, multiple: false });
                frame.on('select', function () {
                    $input.val(frame.state().get('selection').first().toJSON().url).trigger('change');
                });
            }
            frame.open();
        });
    });
});
