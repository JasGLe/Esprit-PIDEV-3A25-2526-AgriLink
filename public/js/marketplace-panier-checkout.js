/**
 * Checkout panier : Informations → Livraison → Paiement (seuil livraison 99 DT).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-mpc-checkout]');
    if (!root) {
        return;
    }

    var subtotal = parseFloat(String(root.getAttribute('data-mpc-subtotal') || '0').replace(',', '.')) || 0;
    var FREE_THRESHOLD =
        parseFloat(String(root.getAttribute('data-mpc-livraison-seuil') || '99').replace(',', '.')) || 99;
    var DELIVERY_FEE =
        parseFloat(String(root.getAttribute('data-mpc-livraison-frais') || '7').replace(',', '.')) || 7;
    var promoValidateUrl = root.getAttribute('data-mpc-promo-validate-url') || '';
    var promoToken = root.getAttribute('data-mpc-promo-token') || '';
    var promoState = { code: '', discountAmount: 0 };

    var stepInfo = document.getElementById('mpc-step-info');
    var stepDelivery = document.getElementById('mpc-step-delivery');
    var stepPayment = document.getElementById('mpc-step-payment');
    var form = document.getElementById('mpc-checkout-form');
    var formCommande = document.getElementById('mpc-form-commande');
    var btnToShipping = document.getElementById('mpc-btn-to-shipping');
    var btnToPayment = document.getElementById('mpc-btn-to-payment');
    var btnBackInfo = document.getElementById('mpc-btn-back-info');
    var btnBackDelivery = document.getElementById('mpc-btn-back-delivery');

    var navItems = root.querySelectorAll('[data-mpc-step-nav]');

    var elSubtotal = document.getElementById('mpc-recap-subtotal-val');
    var elShipping = document.getElementById('mpc-recap-shipping-val');
    var elTotal = document.getElementById('mpc-recap-total-val');
    var elTotalLabel = document.getElementById('mpc-recap-total-label-main');
    var elTotalSublabel = document.getElementById('mpc-recap-total-sublabel');
    var elShippingRow = document.getElementById('mpc-recap-shipping-row');
    var elDiscountRow = document.getElementById('mpc-recap-discount-row');
    var elDiscountVal = document.getElementById('mpc-recap-discount-val');
    var elPromoInput = document.getElementById('mpc-promo-code-input');
    var elPromoApplyBtn = document.getElementById('mpc-promo-apply-btn');
    var elPromoFeedback = document.getElementById('mpc-promo-feedback');

    var elSummaryContact = document.getElementById('mpc-summary-contact');
    var elSummaryAddress = document.getElementById('mpc-summary-address');
    var elDeliveryMethodPrice = document.getElementById('mpc-delivery-method-price');

    var inputs = {
        nom: document.getElementById('checkout-nom'),
        tel: document.getElementById('checkout-tel'),
        email: document.getElementById('checkout-email'),
        adresse: document.getElementById('checkout-adresse'),
        complement: document.getElementById('checkout-complement'),
        cp: document.getElementById('checkout-cp'),
        ville: document.getElementById('checkout-ville'),
    };

    var orderHid = {
        nom: document.getElementById('mpc-order-nom'),
        telephone: document.getElementById('mpc-order-tel'),
        email: document.getElementById('mpc-order-email'),
        adresse: document.getElementById('mpc-order-adresse'),
        complement: document.getElementById('mpc-order-complement'),
        code_postal: document.getElementById('mpc-order-cp'),
        ville: document.getElementById('mpc-order-ville'),
        promo_code: document.getElementById('mpc-order-promo-code'),
    };

    function formatDt(n) {
        var v = Math.round(Number(n) * 1000) / 1000;
        var parts = v.toFixed(3).split('.');
        var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return intPart + ',' + parts[1];
    }

    function shippingFor(total) {
        if (total > FREE_THRESHOLD) {
            return { amount: 0, display: 'Gratuit' };
        }
        return { amount: DELIVERY_FEE, display: formatDt(DELIVERY_FEE) + ' DT' };
    }

    function isAdresseFilled() {
        return inputs.adresse && inputs.adresse.value.trim().length > 0;
    }

    function syncContinueButton() {
        if (!btnToShipping) {
            return;
        }
        var ok = isAdresseFilled();
        btnToShipping.disabled = !ok;
        btnToShipping.removeAttribute('title');
        if (!ok) {
            btnToShipping.setAttribute('title', 'Renseignez l’adresse de livraison pour continuer');
        }
    }

    function syncOrderHiddenFromCheckout() {
        if (!orderHid.nom || !inputs.nom) {
            return;
        }
        orderHid.nom.value = inputs.nom.value.trim();
        if (orderHid.telephone && inputs.tel) {
            orderHid.telephone.value = inputs.tel.value.trim();
        }
        if (orderHid.email && inputs.email) {
            orderHid.email.value = inputs.email.value.trim();
        }
        if (orderHid.adresse && inputs.adresse) {
            orderHid.adresse.value = inputs.adresse.value.trim();
        }
        if (orderHid.complement && inputs.complement) {
            orderHid.complement.value = inputs.complement.value.trim();
        }
        if (orderHid.code_postal && inputs.cp) {
            orderHid.code_postal.value = inputs.cp.value.trim();
        }
        if (orderHid.ville && inputs.ville) {
            orderHid.ville.value = inputs.ville.value.trim();
        }
        if (orderHid.promo_code) {
            orderHid.promo_code.value = promoState.code || '';
        }
    }

    function setStep(step) {
        if (stepInfo) {
            stepInfo.hidden = step !== 1;
        }
        if (stepDelivery) {
            stepDelivery.hidden = step !== 2;
        }
        if (stepPayment) {
            stepPayment.hidden = step !== 3;
        }

        navItems.forEach(function (li) {
            var s = parseInt(li.getAttribute('data-mpc-step-nav'), 10);
            li.classList.remove('mpc-steps__item--current', 'mpc-steps__item--muted', 'mpc-steps__item--done');
            if (s < step) {
                li.classList.add('mpc-steps__item--done');
            } else if (s === step) {
                li.classList.add('mpc-steps__item--current');
            } else {
                li.classList.add('mpc-steps__item--muted');
            }
            var icon = li.querySelector('.mpc-steps__num');
            if (icon) {
                if (s < step) {
                    icon.className = 'bi bi-check-circle-fill mpc-steps__num';
                } else if (s === step) {
                    icon.className = 'bi bi-' + s + '-circle-fill mpc-steps__num';
                } else {
                    icon.className = 'bi bi-' + s + '-circle mpc-steps__num';
                }
            }
        });

        if (step === 1) {
            updateRecapStep1();
        } else {
            updateRecapStep2();
        }
    }

    function updateRecapStep1() {
        var effectiveSubtotal = Math.max(0, subtotal - (promoState.discountAmount || 0));
        if (elSubtotal) {
            elSubtotal.textContent = formatDt(subtotal) + ' DT';
        }
        if (elDiscountRow && elDiscountVal) {
            elDiscountRow.classList.toggle('d-none', !promoState.discountAmount);
            elDiscountVal.textContent = '-' + formatDt(promoState.discountAmount || 0) + ' DT';
        }
        if (elShipping) {
            elShipping.textContent = '—';
        }
        if (elShippingRow) {
            elShippingRow.classList.add('mpc-recap__row--pending');
        }
        if (elTotalLabel) {
            elTotalLabel.textContent = 'Sous-total';
        }
        if (elTotalSublabel) {
            elTotalSublabel.hidden = true;
            elTotalSublabel.textContent = '';
        }
        if (elTotal) {
            elTotal.textContent = formatDt(effectiveSubtotal) + ' DT';
        }
    }

    function updateRecapStep2() {
        var effectiveSubtotal = Math.max(0, subtotal - (promoState.discountAmount || 0));
        var ship = shippingFor(effectiveSubtotal);
        if (elSubtotal) {
            elSubtotal.textContent = formatDt(subtotal) + ' DT';
        }
        if (elDiscountRow && elDiscountVal) {
            elDiscountRow.classList.toggle('d-none', !promoState.discountAmount);
            elDiscountVal.textContent = '-' + formatDt(promoState.discountAmount || 0) + ' DT';
        }
        if (elShipping) {
            elShipping.textContent = ship.display;
        }
        if (elShippingRow) {
            elShippingRow.classList.remove('mpc-recap__row--pending');
        }
        if (elDeliveryMethodPrice) {
            elDeliveryMethodPrice.textContent = ship.display;
        }
        if (elTotalLabel) {
            elTotalLabel.textContent = 'TOTAL TTC';
        }
        if (elTotalSublabel) {
            elTotalSublabel.hidden = false;
            elTotalSublabel.textContent = 'Produits + livraison';
        }
        var grand = effectiveSubtotal + ship.amount;
        if (elTotal) {
            elTotal.textContent = formatDt(grand) + ' DT';
        }
    }

    function setPromoFeedback(message, ok) {
        if (!elPromoFeedback) return;
        elPromoFeedback.textContent = message || '';
        elPromoFeedback.classList.remove('d-none', 'text-success', 'text-danger');
        elPromoFeedback.classList.add(ok ? 'text-success' : 'text-danger');
    }

    async function applyPromoCode() {
        if (!elPromoInput || !elPromoApplyBtn || !promoValidateUrl || !promoToken) {
            return;
        }
        var code = (elPromoInput.value || '').trim();
        if (!code) {
            promoState = { code: '', discountAmount: 0 };
            setPromoFeedback('Saisissez un code promo.', false);
            if (stepPayment && !stepPayment.hidden) {
                updateRecapStep2();
            } else {
                updateRecapStep1();
            }
            syncOrderHiddenFromCheckout();
            return;
        }

        elPromoApplyBtn.disabled = true;
        try {
            var params = new URLSearchParams();
            params.set('_token', promoToken);
            params.set('code', code);
            var res = await fetch(promoValidateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: params.toString(),
                credentials: 'same-origin'
            });
            var data = await res.json();
            if (data.ok) {
                promoState = {
                    code: data.promoCode || code.toUpperCase(),
                    discountAmount: Number(data.discountAmount || 0)
                };
                setPromoFeedback(data.message || 'Code promo appliqué.', true);
            } else {
                promoState = { code: '', discountAmount: 0 };
                setPromoFeedback(data.message || 'Code promo invalide.', false);
            }
        } catch (e) {
            promoState = { code: '', discountAmount: 0 };
            setPromoFeedback('Erreur réseau lors de la validation du code.', false);
        } finally {
            elPromoApplyBtn.disabled = false;
            if (stepPayment && !stepPayment.hidden) {
                updateRecapStep2();
            } else {
                updateRecapStep1();
            }
            syncOrderHiddenFromCheckout();
        }
    }

    function fillSummary() {
        var nom = inputs.nom ? inputs.nom.value.trim() : '';
        var tel = inputs.tel ? inputs.tel.value.trim() : '';
        if (elSummaryContact) {
            elSummaryContact.textContent = tel ? nom + ' — ' + tel : nom;
        }
        var parts = [];
        if (inputs.adresse && inputs.adresse.value.trim()) {
            parts.push(inputs.adresse.value.trim());
        }
        if (inputs.complement && inputs.complement.value.trim()) {
            parts.push(inputs.complement.value.trim());
        }
        var cityLine = [];
        if (inputs.cp && inputs.cp.value.trim()) {
            cityLine.push(inputs.cp.value.trim());
        }
        if (inputs.ville && inputs.ville.value.trim()) {
            cityLine.push(inputs.ville.value.trim());
        }
        if (cityLine.length) {
            parts.push(cityLine.join(' '));
        }
        parts.push('Tunisie');
        if (elSummaryAddress) {
            elSummaryAddress.textContent = parts.join(', ');
        }
    }

    Object.keys(inputs).forEach(function (key) {
        var el = inputs[key];
        if (el) {
            el.addEventListener('input', syncContinueButton);
            el.addEventListener('change', syncContinueButton);
        }
    });

    root.querySelectorAll('.mpc-pay-option__input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            root.querySelectorAll('.mpc-pay-option').forEach(function (lab) {
                lab.classList.remove('mpc-pay-option--selected');
            });
            var lab = radio.closest('.mpc-pay-option');
            if (lab) {
                lab.classList.add('mpc-pay-option--selected');
            }
        });
    });

    (function initPaySelection() {
        var checked = root.querySelector('.mpc-pay-option__input:checked');
        if (checked) {
            var lab = checked.closest('.mpc-pay-option');
            if (lab) {
                lab.classList.add('mpc-pay-option--selected');
            }
        }
    })();

    if (btnToShipping) {
        btnToShipping.addEventListener('click', function () {
            if (!isAdresseFilled()) {
                return;
            }
            if (!form || !form.reportValidity()) {
                return;
            }
            fillSummary();
            setStep(2);
        });
    }

    if (btnToPayment) {
        btnToPayment.addEventListener('click', function () {
            setStep(3);
            syncOrderHiddenFromCheckout();
        });
    }

    if (btnBackInfo) {
        btnBackInfo.addEventListener('click', function () {
            setStep(1);
        });
    }

    if (btnBackDelivery) {
        btnBackDelivery.addEventListener('click', function () {
            setStep(2);
        });
    }

    root.querySelectorAll('[data-mpc-goto-step]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var n = parseInt(btn.getAttribute('data-mpc-goto-step'), 10);
            if (n === 1) {
                setStep(1);
            }
        });
    });

    if (formCommande) {
        formCommande.addEventListener('submit', function () {
            syncOrderHiddenFromCheckout();
        });
    }

    if (elPromoApplyBtn) {
        elPromoApplyBtn.addEventListener('click', applyPromoCode);
    }
    if (elPromoInput) {
        elPromoInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyPromoCode();
            }
        });
    }

    syncContinueButton();
    updateRecapStep1();
})();
