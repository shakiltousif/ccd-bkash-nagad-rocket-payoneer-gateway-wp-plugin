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

    // Payment screenshot: upload as soon as a file is picked, keep the returned token.
    $(document.body).on('change', '.ccd-proof-field[data-gateway] .ccd-proof-input', function () {
        if (typeof ccdProof === 'undefined' || !this.files || !this.files[0]) {
            return;
        }
        var file = this.files[0];
        var $wrap = $(this).closest('.ccd-proof-field');
        var $status = $wrap.find('.ccd-proof-status');
        var $token = $wrap.find('.ccd-proof-token');

        if (file.size > ccdProof.maxBytes) {
            $status.text(ccdProof.i18n.tooBig);
            $token.val('');
            this.value = '';
            return;
        }

        var data = new FormData();
        data.append('file', file);
        data.append('gateway', $wrap.data('gateway'));
        data.append('nonce', ccdProof.nonce);

        $status.text(ccdProof.i18n.uploading);
        $token.val('');
        $('#place_order').prop('disabled', true);

        $.ajax({ url: ccdProof.uploadUrl, type: 'POST', data: data, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                if (res && res.success) {
                    $token.val(res.data.token);
                    $status.text(ccdProof.i18n.uploaded + ' ' + res.data.name);
                } else {
                    $status.text((res && res.data && res.data.message) || ccdProof.i18n.failed);
                }
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                $status.text(msg || ccdProof.i18n.failed);
            })
            .always(function () {
                $('#place_order').prop('disabled', false);
            });
    });

    // Account picker (several bank accounts or wallet numbers): search, "show more", select.
    // Choices are remembered per gateway because WooCommerce re-renders the payment boxes.
    var pickerChoice = {};
    var pickerKey = function ($picker) { return String($picker.find('.ccd-picker-paid-to').attr('name') || ''); };

    var renderPicker = function ($picker) {
        var size = parseInt($picker.data('page-size'), 10) || 5;
        var limit = parseInt($picker.data('limit'), 10) || size;
        var i18n = $picker.data('i18n') || {};
        var words = String($picker.find('.ccd-picker-search').val() || '').toLowerCase().split(/\s+/).filter(Boolean);
        var $options = $picker.find('.ccd-picker-option');
        var matches = $options.filter(function () {
            var hay = String($(this).data('search') || '');
            return words.every(function (w) { return hay.indexOf(w) !== -1; });
        });
        $options.attr('hidden', true);
        matches.slice(0, limit).removeAttr('hidden');
        var shown = Math.min(limit, matches.length);
        $picker.find('.ccd-picker-empty').attr('hidden', matches.length > 0);
        $picker.find('.ccd-picker-count').text(matches.length ? String(i18n.count || '%1$s / %2$s').replace('%1$s', shown).replace('%2$s', matches.length) : '');
        $picker.find('.ccd-picker-more').toggle(shown < matches.length);
        $picker.find('.ccd-picker-footer').toggle(matches.length > 0);
    };

    var showSelected = function ($picker, $option) {
        var details = $option.data('details') || [];
        var i18n = $picker.data('i18n') || {};
        var $sel = $picker.find('.ccd-picker-selected').empty();
        details.forEach(function (r) {
            if (!r.value) {
                return;
            }
            var $p = $('<p class="ccd-account-info"></p>').text((r.label || '') + ' ');
            $('<strong class="ccd-account-value"></strong>').text(r.value).appendTo($p);
            if (r.copy) {
                $p.append(' ');
                $('<button type="button" class="ccd-copy-btn"></button>').attr({ 'data-ccd-copy': r.value, 'data-ccd-copied': i18n.copied || 'Copied!' }).text(i18n.copy || 'Copy').appendTo($p);
            }
            $sel.append($p);
        });
        $('<button type="button" class="ccd-picker-change"></button>').text(i18n.change || 'Change account').appendTo($sel);
        $sel.removeAttr('hidden');
        $picker.find('.ccd-picker-search, .ccd-picker-list, .ccd-picker-footer, .ccd-picker-empty, .ccd-picker-title').attr('hidden', true).hide();
        $picker.find('.ccd-picker-paid-to').val(String($option.data('index')));
        pickerChoice[pickerKey($picker)] = String($option.data('index'));
    };

    var initPickers = function () {
        $('.ccd-picker[data-page-size]').each(function () {
            var $picker = $(this);
            if ($picker.data('ccd-ready')) {
                return;
            }
            $picker.data('ccd-ready', true);
            renderPicker($picker);
            // The payment box is re-rendered on every checkout update; keep the choice.
            var choice = pickerChoice[pickerKey($picker)];
            if (choice !== undefined && choice !== '') {
                var $opt = $picker.find('.ccd-picker-option[data-index="' + choice + '"]');
                if ($opt.length) {
                    showSelected($picker, $opt);
                }
            }
        });
    };

    $(document.body).on('input', '.ccd-picker[data-page-size] .ccd-picker-search', function () {
        var $picker = $(this).closest('.ccd-picker');
        $picker.data('limit', $picker.data('page-size'));
        renderPicker($picker);
    });
    $(document.body).on('keydown', '.ccd-picker[data-page-size] .ccd-picker-search', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // do not submit the checkout form
        }
    });
    $(document.body).on('click', '.ccd-picker[data-page-size] .ccd-picker-more', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.ccd-picker');
        $picker.data('limit', (parseInt($picker.data('limit'), 10) || 5) + (parseInt($picker.data('page-size'), 10) || 5));
        renderPicker($picker);
    });
    $(document.body).on('click', '.ccd-picker[data-page-size] .ccd-picker-option', function (e) {
        e.preventDefault();
        showSelected($(this).closest('.ccd-picker'), $(this));
    });
    $(document.body).on('click', '.ccd-picker[data-page-size] .ccd-picker-change', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.ccd-picker');
        pickerChoice[pickerKey($picker)] = '';
        $picker.find('.ccd-picker-paid-to').val('');
        $picker.find('.ccd-picker-selected').attr('hidden', true).empty();
        $picker.find('.ccd-picker-search, .ccd-picker-list, .ccd-picker-title').removeAttr('hidden').show();
        renderPicker($picker);
    });
    $(document.body).on('updated_checkout', initPickers);
    initPickers();

});