/**
 * CodeCareBD — WooCommerce Cart & Checkout Blocks integration.
 *
 * Registers the four manual gateways (bKash, Nagad, Rocket, Payoneer) as block
 * payment methods. No build step: this uses the WordPress/WooCommerce script
 * globals (wc-blocks-registry, wp-element, etc.) directly.
 */
(function () {
    'use strict';

    if (!window.wc || !window.wc.wcBlocksRegistry || !window.wc.wcSettings) {
        return;
    }

    var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
    var getSetting = window.wc.wcSettings.getSetting;
    var el = window.wp.element.createElement;
    var useEffect = window.wp.element.useEffect;
    var decodeEntities = window.wp.htmlEntities.decodeEntities;
    var __ = window.wp.i18n.__;

    var GATEWAYS = ['ccd_bkash', 'ccd_nagad', 'ccd_rocket', 'ccd_payoneer'];

    /**
     * Copy the button's data-ccd-copy value and briefly show "Copied!".
     *
     * @param {HTMLElement} btn Copy button.
     */
    function copyText(btn) {
        var text = btn.getAttribute('data-ccd-copy') || '';
        var label = btn.textContent;
        var done = function () {
            btn.textContent = btn.getAttribute('data-ccd-copied') || label;
            setTimeout(function () { btn.textContent = label; }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
        } else {
            fallbackCopy(text);
            done();
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) { /* ignore */ }
        document.body.removeChild(ta);
    }

    /**
     * Keep the send money charge in sync with the selected payment method:
     * tell the Store API cart which method is active so fees are recalculated.
     */
    function watchPaymentMethod() {
        var data = window.wp && window.wp.data;
        var blocksCheckout = window.wc && window.wc.blocksCheckout;
        if (!data || !blocksCheckout || !blocksCheckout.extensionCartUpdate) {
            return;
        }
        var anyCharge = GATEWAYS.some(function (id) {
            var d = getSetting(id + '_data', null);
            return d && d.anyCharge;
        });
        if (!anyCharge) {
            return;
        }
        var last = null;
        data.subscribe(function () {
            var store = data.select('wc/store/payment');
            if (!store || !store.getActivePaymentMethod) {
                return;
            }
            var method = store.getActivePaymentMethod();
            if (!method || method === last) {
                return;
            }
            last = method;
            blocksCheckout.extensionCartUpdate({
                namespace: 'ccd-payment-gateway',
                data: { payment_method: method }
            });
        });
    }

    watchPaymentMethod();

    /**
     * Client-side validation mirroring the server (ccd_validate_payment_fields).
     *
     * @param {string} id     Gateway id.
     * @param {Array}  fields Field descriptors [number/email, transaction].
     * @param {Object} values Current field values.
     * @return {string[]} Error messages (empty when valid).
     */
    function validate(id, fields, values) {
        var errors = [];
        var primary = fields[0];
        var trx = fields[1];
        var primaryVal = (values[primary.name] || '').trim();
        var trxVal = (values[trx.name] || '').trim();

        if (id === 'ccd_payoneer') {
            if (primaryVal === '') {
                errors.push(__('Please enter your Payoneer sender email.', 'ccd-payment-gateway-domain'));
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(primaryVal)) {
                errors.push(__('Please enter a valid Payoneer sender email.', 'ccd-payment-gateway-domain'));
            }
            if (trxVal === '') {
                errors.push(__('Please enter your Payoneer transaction ID.', 'ccd-payment-gateway-domain'));
            } else if (trxVal.length < 5) {
                errors.push(__('Please enter a valid Payoneer transaction ID.', 'ccd-payment-gateway-domain'));
            }
        } else {
            if (primaryVal === '') {
                errors.push(__('Please enter your number.', 'ccd-payment-gateway-domain'));
            } else if (!/^[0-9]{11}$/.test(primaryVal)) {
                errors.push(__('Please enter a valid phone number.', 'ccd-payment-gateway-domain'));
            }
            if (trxVal === '') {
                errors.push(__('Please enter your transaction ID.', 'ccd-payment-gateway-domain'));
            } else if (trxVal.length < 5) {
                errors.push(__('Please enter a valid transaction ID.', 'ccd-payment-gateway-domain'));
            }
        }

        return errors;
    }

    GATEWAYS.forEach(function (id) {
        var data = getSetting(id + '_data', null);
        if (!data || !data.fields || !data.fields.length) {
            return;
        }

        // Module-scoped store of the latest field values for this gateway.
        var values = {};

        /**
         * The content rendered under the payment method in block checkout.
         */
        var Content = function (props) {
            var eventRegistration = props.eventRegistration;
            var emitResponse = props.emitResponse;
            var onPaymentSetup = eventRegistration.onPaymentSetup;

            useEffect(function () {
                var unsubscribe = onPaymentSetup(function () {
                    var errors = validate(id, data.fields, values);
                    if (errors.length) {
                        return {
                            type: emitResponse.responseTypes.ERROR,
                            message: errors.join(' '),
                            messageContext: emitResponse.noticeContexts.PAYMENTS
                        };
                    }
                    var paymentMethodData = {};
                    data.fields.forEach(function (f) {
                        paymentMethodData[f.name] = values[f.name] || '';
                    });
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: { paymentMethodData: paymentMethodData }
                    };
                });
                return unsubscribe;
            }, [onPaymentSetup, emitResponse.responseTypes, emitResponse.noticeContexts]);

            var children = [];

            if (data.description) {
                children.push(el('p', { key: 'desc' }, decodeEntities(data.description)));
            }

            if (data.accountValue) {
                children.push(el('p', { key: 'account', className: 'ccd-account-info ccd-blocks-account-info' }, [
                    decodeEntities(data.accountText) + ' ',
                    el('strong', { key: 'v', className: 'ccd-account-value' }, decodeEntities(data.accountValue)),
                    ' ',
                    el('button', {
                        key: 'c',
                        type: 'button',
                        className: 'ccd-copy-btn',
                        'data-ccd-copy': data.accountValue,
                        'data-ccd-copied': __('Copied!', 'ccd-payment-gateway-domain'),
                        onClick: function (e) {
                            // The classic checkout script also listens for .ccd-copy-btn clicks.
                            e.preventDefault();
                            e.stopPropagation();
                            copyText(e.currentTarget);
                        }
                    }, __('Copy', 'ccd-payment-gateway-domain'))
                ]));
            }

            if (data.qrCode) {
                children.push(el('p', { key: 'qr', className: 'ccd-qr-code' }, [
                    el('img', { key: 'i', src: data.qrCode, alt: __('Scan to pay', 'ccd-payment-gateway-domain'), loading: 'lazy' }),
                    el('br', { key: 'b' }),
                    el('small', { key: 's' }, __('Scan this QR code with your app to pay', 'ccd-payment-gateway-domain'))
                ]));
            }

            if (data.chargeNote) {
                children.push(el('p', { key: 'charge', className: 'ccd_extra_charge_note' },
                    decodeEntities(data.chargeNote)));
            }

            if (data.advancePayment) {
                var ap = data.advancePayment;
                children.push(el('div', {
                    key: 'advance',
                    className: 'ccd_advance_payment_notice',
                    style: { background: '#fff3cd', border: '1px solid #ffc107', padding: '15px', margin: '15px 0', borderRadius: '4px' }
                }, [
                    el('strong', { key: 't' }, decodeEntities(ap.title)),
                    el('br', { key: 'br1' }),
                    decodeEntities(ap.advanceText) + ' ',
                    el('strong', { key: 'a' }, decodeEntities(ap.advanceAmount)),
                    el('br', { key: 'br2' }),
                    decodeEntities(ap.remainingText) + ' ',
                    el('strong', { key: 'r' }, decodeEntities(ap.remainingAmount))
                ]));
            }

            var rows = data.fields.map(function (f) {
                return el('p', { key: f.name, className: 'ccd-blocks-field form-row form-row-wide' }, [
                    el('label', { key: 'l', htmlFor: 'ccd_block_' + f.name }, decodeEntities(f.label)),
                    el('input', {
                        key: 'i',
                        id: 'ccd_block_' + f.name,
                        type: f.type || 'text',
                        className: 'input-text',
                        placeholder: f.placeholder || '',
                        onChange: function (e) { values[f.name] = e.target.value; }
                    })
                ]);
            });

            children.push(el('div', { key: 'fields', className: 'ccd-blocks-fields' }, rows));

            return el('div', { className: 'ccd-blocks-content' }, children);
        };

        /**
         * Label: gateway title with its icon.
         */
        var Label = function () {
            var title = decodeEntities(data.title || id);
            var nodes = [el('span', { key: 'title' }, title)];
            if (data.icon) {
                nodes.unshift(el('img', {
                    key: 'icon',
                    src: data.icon,
                    alt: title,
                    style: { maxHeight: '24px', marginRight: '8px', verticalAlign: 'middle' }
                }));
            }
            return el('span', { className: 'ccd-blocks-label', style: { display: 'flex', alignItems: 'center' } }, nodes);
        };

        registerPaymentMethod({
            name: id,
            label: el(Label, null),
            ariaLabel: decodeEntities(data.title || id),
            canMakePayment: function () { return true; },
            content: el(Content, null),
            edit: el(Content, null),
            supports: {
                features: (data.supports && data.supports.length) ? data.supports : ['products']
            }
        });
    });
})();
