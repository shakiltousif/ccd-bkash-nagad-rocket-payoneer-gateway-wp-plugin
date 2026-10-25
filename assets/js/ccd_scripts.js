jQuery(document).ready(function ($) {

    $(document.body).on('change', 'input[name="payment_method"]', () => {
        $('body').trigger('update_checkout');
    });

    // Copy the receiving number/email to the clipboard.
    $(document.body).on('click', '.ccd-copy-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var text = String($btn.data('ccd-copy') || '');
        var label = $btn.text();
        var done = function () {
            $btn.text($btn.data('ccd-copied') || label);
            setTimeout(function () { $btn.text(label); }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, done);
            return;
        }
        var $ta = $('<textarea readonly style="position:fixed;opacity:0"></textarea>').val(text).appendTo(document.body);
        $ta[0].select();
        try { document.execCommand('copy'); } catch (err) { /* ignore */ }
        $ta.remove();
        done();
    });

});