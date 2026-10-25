/**
 * CodeCareBD — WooCommerce Cart & Checkout Blocks integration.
 *
 * Registers the manual gateways (bKash, Nagad, Rocket, Payoneer, Upay,
 * CellFin, Bank Transfer, Bangla QR) as block payment methods. No build step:
 * this uses the WordPress/WooCommerce script globals directly.
 *
 * Field format validation happens on the server (one source of truth); the
 * client only checks that required fields are filled in.
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
    var useState = window.wp.element.useState;
    var useSelect = window.wp.data && window.wp.data.useSelect;
    var decodeEntities = window.wp.htmlEntities.decodeEntities;
    var __ = window.wp.i18n.__;

    var config = window.ccdBlocks || {};
    var GATEWAYS = config.gateways || ['ccd_bkash', 'ccd_nagad', 'ccd_rocket', 'ccd_payoneer'];
    var PROOF = config.proof || null;

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
     * Keep the send money charge / discount in sync with the selected payment
     * method: tell the Store API cart which method is active so fees are
     * recalculated.
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
     * Upload a payment screenshot; resolves with { token, name }.
     *
     * @param {string} gateway Gateway id.
     * @param {File}   file    Selected file.
     * @return {Promise}
     */
    function uploadProof(gateway, file) {
        if (!PROOF) {
            return Promise.reject(new Error(__('Upload failed. Please try again.', 'ccd-payment-gateway-domain')));
        }
        if (file.size > PROOF.maxBytes) {
            return Promise.reject(new Error(PROOF.i18n.tooBig));
        }
        var body = new FormData();
        body.append('file', file);
        body.append('gateway', gateway);
        body.append('nonce', PROOF.nonce);
        return fetch(PROOF.uploadUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json().catch(function () { return null; }); })
            .then(function (json) {
                if (json && json.success) {
                    return json.data;
                }
                throw new Error((json && json.data && json.data.message) || PROOF.i18n.failed);
            });
    }

    /**
     * Copy button element.
     */
    function copyButton(value, i18n, key) {
        return el('button', {
            key: key,
            type: 'button',
            className: 'ccd-copy-btn',
            'data-ccd-copy': value,
            'data-ccd-copied': i18n.copied || __('Copied!', 'ccd-payment-gateway-domain'),
            onClick: function (e) {
                e.preventDefault();
                e.stopPropagation();
                copyText(e.currentTarget);
            }
        }, i18n.copy || __('Copy', 'ccd-payment-gateway-domain'));
    }

    /**
     * Searchable, paginated bank account picker (used when the shop has
     * several bank accounts). Stores the chosen index in values.ccd_bank_paid_to.
     */
    var BankPicker = function (props) {
        var accounts = props.accounts;
        var i18n = props.i18n;
        var PAGE = 5;
        var qState = useState('');
        var limitState = useState(PAGE);
        var selState = useState(props.values.ccd_bank_paid_to !== undefined && props.values.ccd_bank_paid_to !== '' ? String(props.values.ccd_bank_paid_to) : '');
        var query = qState[0], setQuery = qState[1];
        var limit = limitState[0], setLimit = limitState[1];
        var selected = selState[0], setSelected = selState[1];

        var words = query.toLowerCase().split(/\s+/).filter(Boolean);
        var matches = accounts.filter(function (a) {
            return words.every(function (w) { return a.search.indexOf(w) !== -1; });
        });
        var shown = matches.slice(0, limit);
        var current = accounts.filter(function (a) { return String(a.index) === selected; })[0];

        var choose = function (a) {
            setSelected(String(a.index));
            props.values.ccd_bank_paid_to = String(a.index);
        };

        if (current) {
            var rows = [
                ['bank', current.bank_name, false],
                ['accountName', current.account_name, false],
                ['number', current.account_number, true],
                ['branch', current.branch, false],
                ['routing', current.routing, true]
            ].filter(function (r) { return r[1]; });
            return el('div', { className: 'ccd-bank-picker' }, [
                el('div', { key: 'sel', className: 'ccd-bank-selected' }, rows.map(function (r) {
                    return el('p', { key: r[0], className: 'ccd-account-info' }, [
                        decodeEntities(i18n[r[0]] || '') + ' ',
                        el('strong', { key: 'v', className: 'ccd-account-value' }, decodeEntities(r[1])),
                        r[2] ? ' ' : null,
                        r[2] ? copyButton(r[1], i18n, 'c') : null
                    ]);
                })),
                el('button', {
                    key: 'change',
                    type: 'button',
                    className: 'ccd-bank-change',
                    onClick: function (e) {
                        e.preventDefault();
                        setSelected('');
                        props.values.ccd_bank_paid_to = '';
                    }
                }, i18n.change)
            ]);
        }

        return el('div', { className: 'ccd-bank-picker' }, [
            el('p', { key: 't', className: 'ccd-bank-picker-title' }, el('strong', null, i18n.choose + ' *')),
            el('input', {
                key: 'q',
                type: 'search',
                className: 'ccd-bank-search input-text',
                placeholder: i18n.search,
                'aria-label': i18n.search,
                autoComplete: 'off',
                value: query,
                onChange: function (e) { setQuery(e.target.value); setLimit(PAGE); }
            }),
            el('div', { key: 'l', className: 'ccd-bank-list', role: 'listbox', 'aria-label': i18n.choose }, shown.map(function (a) {
                return el('button', {
                    key: 'a' + a.index,
                    type: 'button',
                    role: 'option',
                    'aria-selected': 'false',
                    className: 'ccd-bank-option',
                    onClick: function (e) { e.preventDefault(); choose(a); }
                }, [
                    el('span', { key: 'n', className: 'ccd-bank-option-name' }, decodeEntities(a.bank_name || a.label)),
                    el('span', { key: 'm', className: 'ccd-bank-option-meta' }, decodeEntities([a.branch, 'A/C ' + a.account_number].filter(Boolean).join(' · ')))
                ]);
            })),
            matches.length === 0 ? el('p', { key: 'e', className: 'ccd-bank-empty' }, i18n.none) : null,
            matches.length > 0 ? el('p', { key: 'f', className: 'ccd-bank-footer' }, [
                el('small', { key: 'c', className: 'ccd-bank-count' }, (i18n.count || '%1$s / %2$s').replace('%1$s', shown.length).replace('%2$s', matches.length)),
                ' ',
                shown.length < matches.length ? el('button', {
                    key: 'm',
                    type: 'button',
                    className: 'ccd-bank-more',
                    onClick: function (e) { e.preventDefault(); setLimit(limit + PAGE); }
                }, i18n.more) : null
            ]) : null
        ]);
    };

    GATEWAYS.forEach(function (id) {
        var data = getSetting(id + '_data', null);
        if (!data || !data.fields || !data.fields.length) {
            return;
        }

        // Module-scoped store of the latest field values for this gateway.
        var values = {};
        var proof = { token: '', name: '' };

        var ProofField = function () {
            var state = useState(proof.name ? PROOF.i18n.uploaded + ' ' + proof.name : '');
            var status = state[0];
            var setStatus = state[1];
            var required = data.proofMode === 'required';

            return el('p', { className: 'ccd-proof-field form-row form-row-wide' }, [
                el('label', { key: 'l', htmlFor: 'ccd_block_proof_' + id },
                    required
                        ? __('Payment screenshot or PDF', 'ccd-payment-gateway-domain') + ' *'
                        : __('Payment screenshot or PDF (optional)', 'ccd-payment-gateway-domain')),
                el('input', {
                    key: 'i',
                    id: 'ccd_block_proof_' + id,
                    type: 'file',
                    className: 'ccd-proof-input',
                    accept: PROOF ? PROOF.accept : 'image/*,application/pdf',
                    onChange: function (e) {
                        var file = e.target.files && e.target.files[0];
                        proof.token = '';
                        proof.name = '';
                        if (!file) {
                            setStatus('');
                            return;
                        }
                        setStatus(PROOF ? PROOF.i18n.uploading : '');
                        uploadProof(id, file).then(function (res) {
                            proof.token = res.token;
                            proof.name = res.name;
                            setStatus(PROOF.i18n.uploaded + ' ' + res.name);
                        }, function (err) {
                            e.target.value = '';
                            setStatus(err.message);
                        });
                    }
                }),
                el('small', { key: 's', className: 'ccd-proof-status' },
                    status || (PROOF ? PROOF.i18n.hint : ''))
            ]);
        };

        /**
         * The content rendered under the payment method in block checkout.
         */
        var Content = function (props) {
            var eventRegistration = props.eventRegistration;
            var emitResponse = props.emitResponse;
            var onPaymentSetup = eventRegistration.onPaymentSetup;

            useEffect(function () {
                var unsubscribe = onPaymentSetup(function () {
                    var errors = [];
                    data.fields.forEach(function (f) {
                        if (!(values[f.name] || '').trim()) {
                            errors.push(__('Please fill in:', 'ccd-payment-gateway-domain') + ' ' + decodeEntities(f.label));
                        }
                    });
                    if (data.bankAccounts && data.bankAccounts.length > 1 && !values.ccd_bank_paid_to) {
                        errors.push(__('Please select the bank account you paid into.', 'ccd-payment-gateway-domain'));
                    }
                    if (data.proofMode === 'required' && !proof.token) {
                        errors.push(__('Please upload a screenshot or PDF of your payment.', 'ccd-payment-gateway-domain'));
                    }
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
                    if (proof.token) {
                        paymentMethodData.ccd_payment_proof = proof.token;
                    }
                    if (values.ccd_bank_paid_to) {
                        paymentMethodData.ccd_bank_paid_to = values.ccd_bank_paid_to;
                    }
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: { paymentMethodData: paymentMethodData }
                    };
                });
                return unsubscribe;
            }, [onPaymentSetup, emitResponse.responseTypes, emitResponse.noticeContexts]);

            var children = [];

            if (data.discountNote) {
                children.push(el('p', { key: 'discount', className: 'ccd_discount_note' }, decodeEntities(data.discountNote)));
            }

            if (data.chargeNote) {
                children.push(el('p', { key: 'charge', className: 'ccd_extra_charge_note' }, decodeEntities(data.chargeNote)));
            }

            // Live value from the Store API cart (updates with address / method changes).
            var live = useSelect ? useSelect(function (select) {
                var store = select('wc/store/cart');
                var cart = store && store.getCartData ? store.getCartData() : null;
                return cart && cart.extensions ? cart.extensions['ccd-payment-gateway'] : undefined;
            }, []) : undefined;
            var ap = (live && live.advance !== undefined) ? live.advance : data.advancePayment;
            if (ap) {
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

            if (data.description) {
                children.push(el('p', { key: 'desc' }, decodeEntities(data.description)));
            }

            (data.accountRows || []).forEach(function (row, i) {
                if (row.heading) {
                    children.push(el('p', { key: 'heading' + i, className: 'ccd-account-heading' }, el('strong', null, decodeEntities(row.heading))));
                    return;
                }
                var parts = [
                    decodeEntities(row.label) + ' ',
                    el('strong', { key: 'v', className: 'ccd-account-value' }, decodeEntities(row.value))
                ];
                if (row.copy) {
                    parts.push(' ');
                    parts.push(el('button', {
                        key: 'c',
                        type: 'button',
                        className: 'ccd-copy-btn',
                        'data-ccd-copy': row.value,
                        'data-ccd-copied': __('Copied!', 'ccd-payment-gateway-domain'),
                        onClick: function (e) {
                            // The classic checkout script also listens for .ccd-copy-btn clicks.
                            e.preventDefault();
                            e.stopPropagation();
                            copyText(e.currentTarget);
                        }
                    }, __('Copy', 'ccd-payment-gateway-domain')));
                }
                children.push(el('p', { key: 'account' + i, className: 'ccd-account-info ccd-blocks-account-info' }, parts));
            });

            if (data.qrCode) {
                children.push(el('p', { key: 'qr', className: 'ccd-qr-code' }, [
                    el('img', { key: 'i', src: data.qrCode, alt: __('Scan to pay', 'ccd-payment-gateway-domain'), loading: 'lazy' }),
                    el('br', { key: 'b' }),
                    el('small', { key: 's' }, __('Scan this QR code with your app to pay', 'ccd-payment-gateway-domain'))
                ]));
            }

            var rows = data.fields.map(function (f) {
                return el('p', { key: f.name, className: 'ccd-blocks-field form-row form-row-wide' }, [
                    el('label', { key: 'l', htmlFor: 'ccd_block_' + f.name }, decodeEntities(f.label) + ' *'),
                    el('input', {
                        key: 'i',
                        id: 'ccd_block_' + f.name,
                        type: f.type === 'tel' ? 'text' : (f.type || 'text'),
                        inputMode: f.type === 'tel' ? 'numeric' : undefined,
                        autoComplete: 'off',
                        className: 'input-text',
                        placeholder: f.placeholder || '',
                        // Keep what the customer typed when they switch methods and come back.
                        defaultValue: values[f.name] || '',
                        onChange: function (e) { values[f.name] = e.target.value; }
                    })
                ]);
            });

            if (data.bankAccounts && data.bankAccounts.length > 1) {
                children.push(el(BankPicker, { key: 'bank_picker', accounts: data.bankAccounts, i18n: data.bankI18n || {}, values: values }));
            }

            children.push(el('div', { key: 'fields', className: 'ccd-blocks-fields' }, rows));

            if (data.proofMode === 'optional' || data.proofMode === 'required') {
                children.push(el(ProofField, { key: 'proof' }));
            }

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
                    alt: '',
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
