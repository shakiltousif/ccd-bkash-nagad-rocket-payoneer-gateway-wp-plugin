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

    // Bank account picker (several bank accounts): search, "show more", select.
    var bankChoice = '';

    var renderPicker = function ($picker) {
        var size = parseInt($picker.data('page-size'), 10) || 5;
        var limit = parseInt($picker.data('limit'), 10) || size;
        var i18n = $picker.data('i18n') || {};
        var words = String($picker.find('.ccd-bank-search').val() || '').toLowerCase().split(/\s+/).filter(Boolean);
        var $options = $picker.find('.ccd-bank-option');
        var matches = $options.filter(function () {
            var hay = String($(this).data('search') || '');
            return words.every(function (w) { return hay.indexOf(w) !== -1; });
        });
        $options.attr('hidden', true);
        matches.slice(0, limit).removeAttr('hidden');
        var shown = Math.min(limit, matches.length);
        $picker.find('.ccd-bank-empty').attr('hidden', matches.length > 0);
        $picker.find('.ccd-bank-count').text(matches.length ? String(i18n.count || '%1$s / %2$s').replace('%1$s', shown).replace('%2$s', matches.length) : '');
        $picker.find('.ccd-bank-more').toggle(shown < matches.length);
        $picker.find('.ccd-bank-footer').toggle(matches.length > 0);
    };

    var showSelected = function ($picker, $option) {
        var account = $option.data('account') || {};
        var i18n = $picker.data('i18n') || {};
        var $sel = $picker.find('.ccd-bank-selected').empty();
        [['bank', account.bank_name, false], ['accountName', account.account_name, false], ['number', account.account_number, true], ['branch', account.branch, false], ['routing', account.routing, true]].forEach(function (r) {
            if (!r[1]) {
                return;
            }
            var $p = $('<p class="ccd-account-info"></p>').text((i18n[r[0]] || '') + ' ');
            $('<strong class="ccd-account-value"></strong>').text(r[1]).appendTo($p);
            if (r[2]) {
                $p.append(' ');
                $('<button type="button" class="ccd-copy-btn"></button>').attr({ 'data-ccd-copy': r[1], 'data-ccd-copied': i18n.copied || 'Copied!' }).text(i18n.copy || 'Copy').appendTo($p);
            }
            $sel.append($p);
        });
        $('<button type="button" class="ccd-bank-change"></button>').text(i18n.change || 'Change account').appendTo($sel);
        $sel.removeAttr('hidden');
        $picker.find('.ccd-bank-search, .ccd-bank-list, .ccd-bank-footer, .ccd-bank-empty, .ccd-bank-picker-title').attr('hidden', true).hide();
        $picker.find('.ccd-bank-paid-to').val(String($option.data('index')));
        bankChoice = String($option.data('index'));
    };

    var initPickers = function () {
        $('.ccd-bank-picker[data-page-size]').each(function () {
            var $picker = $(this);
            if ($picker.data('ccd-ready')) {
                return;
            }
            $picker.data('ccd-ready', true);
            renderPicker($picker);
            // The payment box is re-rendered on every checkout update; keep the choice.
            if (bankChoice !== '') {
                var $opt = $picker.find('.ccd-bank-option[data-index="' + bankChoice + '"]');
                if ($opt.length) {
                    showSelected($picker, $opt);
                }
            }
        });
    };

    $(document.body).on('input', '.ccd-bank-picker[data-page-size] .ccd-bank-search', function () {
        var $picker = $(this).closest('.ccd-bank-picker');
        $picker.data('limit', $picker.data('page-size'));
        renderPicker($picker);
    });
    $(document.body).on('keydown', '.ccd-bank-picker[data-page-size] .ccd-bank-search', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // do not submit the checkout form
        }
    });
    $(document.body).on('click', '.ccd-bank-picker[data-page-size] .ccd-bank-more', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.ccd-bank-picker');
        $picker.data('limit', (parseInt($picker.data('limit'), 10) || 5) + (parseInt($picker.data('page-size'), 10) || 5));
        renderPicker($picker);
    });
    $(document.body).on('click', '.ccd-bank-picker[data-page-size] .ccd-bank-option', function (e) {
        e.preventDefault();
        showSelected($(this).closest('.ccd-bank-picker'), $(this));
    });
    $(document.body).on('click', '.ccd-bank-picker[data-page-size] .ccd-bank-change', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.ccd-bank-picker');
        bankChoice = '';
        $picker.find('.ccd-bank-paid-to').val('');
        $picker.find('.ccd-bank-selected').attr('hidden', true).empty();
        $picker.find('.ccd-bank-search, .ccd-bank-list, .ccd-bank-picker-title').removeAttr('hidden').show();
        renderPicker($picker);
    });
    $(document.body).on('updated_checkout', initPickers);
    initPickers();

});