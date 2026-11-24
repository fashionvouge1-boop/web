/* =====================================================================
   StyleHub — front-end behaviour
   ===================================================================== */
(function() {
    'use strict';

    /* ---------- Mobile navigation + filter drawer ---------- */
    var nav = document.getElementById('mainNav');
    var burger = document.getElementById('navToggle');
    var overlay = document.createElement('div');
    overlay.className = 'overlay';
    document.body.appendChild(overlay);

    function closeDrawers() {
        if (nav) nav.classList.remove('is-open');
        document.querySelectorAll('.filters.is-open').forEach(function(f) {
            f.classList.remove('is-open');
        });
        overlay.classList.remove('is-open');
    }

    if (burger && nav) {
        burger.addEventListener('click', function() {
            nav.classList.toggle('is-open');
            overlay.classList.toggle('is-open', nav.classList.contains('is-open'));
        });
    }

    var filterToggle = document.querySelector('.filters-toggle');
    if (filterToggle) {
        filterToggle.addEventListener('click', function() {
            var f = document.querySelector('.filters');
            if (!f) return;
            f.classList.toggle('is-open');
            overlay.classList.toggle('is-open', f.classList.contains('is-open'));
        });
    }

    overlay.addEventListener('click', closeDrawers);

    /* ---------- Hero carousel ---------- */
    var slides = Array.prototype.slice.call(document.querySelectorAll('.hero__slide'));
    if (slides.length > 0) {
        var dots = Array.prototype.slice.call(document.querySelectorAll('.hero__dot'));
        var index = 0;
        var timer = null;

        function show(i) {
            index = (i + slides.length) % slides.length;
            slides.forEach(function(s, k) {
                s.classList.toggle('is-active', k === index);
            });
            dots.forEach(function(d, k) {
                d.classList.toggle('is-active', k === index);
            });
        }

        function next() {
            show(index + 1);
        }

        function start() {
            timer = setInterval(next, 5500);
        }

        function reset() {
            clearInterval(timer);
            start();
        }

        dots.forEach(function(d, k) {
            d.addEventListener('click', function() {
                show(k);
                reset();
            });
        });
        var prev = document.querySelector('.hero__arrow--prev');
        var nxt = document.querySelector('.hero__arrow--next');
        if (prev) prev.addEventListener('click', function() {
            show(index - 1);
            reset();
        });
        if (nxt) nxt.addEventListener('click', function() {
            show(index + 1);
            reset();
        });
        start();
    }

    /* ---------- Quantity steppers ---------- */
    document.querySelectorAll('.qty').forEach(function(box) {
        var input = box.querySelector('input');
        if (!input) return;
        var min = parseInt(input.getAttribute('min') || '1', 10);
        var max = parseInt(input.getAttribute('max') || '99', 10);
        box.querySelectorAll('button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var v = parseInt(input.value || '1', 10);
                v = btn.dataset.step === 'up' ? v + 1 : v - 1;
                input.value = Math.max(min, Math.min(max, v));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });

    /* ---------- Payment method highlight + pincode-driven COD block ---------- */
    var payOptions = document.querySelectorAll('.pay-option');
    var payError = document.getElementById('payError');
    var zipInput = document.getElementById('zip');
    var codOption = document.querySelector('.pay-option[data-cod="1"]');

    function syncPay() {
        payOptions.forEach(function(o) {
            var r = o.querySelector('input[type=radio]');
            o.classList.toggle('is-selected', !!(r && r.checked));
        });
    }

    function showPayError(msg) {
        if (!payError) return;
        if (msg) {
            payError.textContent = '\u26A0 ' + msg;
            payError.style.display = 'block';
        } else {
            payError.style.display = 'none';
        }
    }

    function codMessage() {
        return (codOption && codOption.getAttribute('data-block-message')) ||
            'Cash on Delivery is unavailable.';
    }

    /* First selectable online gateway (never Cash on Delivery). */
    function firstAvailable() {
        return document.querySelector('.pay-option input[type=radio][data-blocked="0"]:not([data-cod="1"])') ||
            document.querySelector('.pay-option input[type=radio][data-blocked="0"]');
    }

    function zipFilled() {
        return !!(zipInput && zipInput.value.trim() !== '');
    }

    /* Cash on Delivery is only serviceable while no pin code is entered.
       Entering a pin code shows the error; clearing it removes the error. */
    function updateCod() {
        if (!codOption) return;
        var radio = codOption.querySelector('input[type=radio]');
        var blocked = zipFilled();

        codOption.classList.toggle('is-blocked', blocked);
        if (radio) radio.setAttribute('data-blocked', blocked ? '1' : '0');
        var note = codOption.querySelector('.pay-note');
        if (note) note.style.display = blocked ? 'block' : 'none';

        if (blocked) {
            if (radio && radio.checked) {
                var ok = firstAvailable();
                if (ok) ok.checked = true;
            }
            showPayError(codMessage());
        } else {
            showPayError('');
        }
        syncPay();
    }

    if (zipInput) {
        zipInput.addEventListener('input', updateCod);
        zipInput.addEventListener('change', updateCod);
    }

    payOptions.forEach(function(o) {
        var r = o.querySelector('input[type=radio]');
        if (!r) return;
        var isCod = r.getAttribute('data-cod') === '1';

        function blockedMsg() {
            return isCod ? codMessage() :
                (o.getAttribute('data-block-message') || 'This payment method is unavailable.');
        }

        o.addEventListener('click', function(ev) {
            if (r.getAttribute('data-blocked') === '1') {
                ev.preventDefault();
                var ok = firstAvailable();
                if (ok) ok.checked = true;
                showPayError(blockedMsg());
                syncPay();
                return;
            }
            r.checked = true;
            showPayError('');
            syncPay();
        });

        r.addEventListener('change', function() {
            if (r.getAttribute('data-blocked') === '1') {
                var ok = firstAvailable();
                if (ok) ok.checked = true;
                showPayError(blockedMsg());
            } else if (!isCod) {
                showPayError('');
            }
            syncPay();
        });
    });

    syncPay();
    updateCod();

    /* Safety net: never submit with a blocked payment method selected. */
    var checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(ev) {
            var picked = checkoutForm.querySelector('input[name=payment_method]:checked');
            if (picked && picked.getAttribute('data-blocked') === '1') {
                ev.preventDefault();
                var opt = picked.closest('.pay-option');
                showPayError((opt && opt.getAttribute('data-block-message')) || 'This payment method is unavailable.');
                var ok = firstAvailable();
                if (ok) {
                    ok.checked = true;
                    syncPay();
                }
            }
        });
    }

    /* ---------- AJAX add-to-cart (progressive enhancement) ---------- */
    document.querySelectorAll('form[data-ajax-cart]').forEach(function(form) {
        form.addEventListener('submit', function(ev) {
            if (!window.fetch) return; /* fall back to normal POST */
            ev.preventDefault();
            var btn = form.querySelector('button[type=submit]');
            var original = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = 'Adding…';
            }

            fetch(form.getAttribute('action') || form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && typeof data.cart_count !== 'undefined') {
                        document.querySelectorAll('.cart-count').forEach(function(el) {
                            el.textContent = data.cart_count;
                        });
                        if (data.cart_count > 0 && !document.querySelector('.cart-count')) {
                            var c = document.querySelector('.cart-btn');
                            if (c) {
                                var s = document.createElement('span');
                                s.className = 'cart-count';
                                s.textContent = data.cart_count;
                                c.appendChild(s);
                            }
                        }
                    }
                    if (btn) { btn.innerHTML = '&#10003; Added'; }
                    setTimeout(function() {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = original;
                        }
                    }, 1400);
                })
                .catch(function() { form.submit(); });
        });
    });

    /* ---------- Cart: remove / clear (single form, JS-driven) ---------- */
    var cartForm = document.getElementById('cartForm');
    if (cartForm) {
        var actionInput = cartForm.querySelector('[name=action]');

        document.querySelectorAll('[data-cart-remove]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this item from your cart?')) return;
                actionInput.value = 'remove';
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'cart_key';
                hidden.value = btn.dataset.cartRemove;
                cartForm.appendChild(hidden);
                cartForm.submit();
            });
        });

        var clearBtn = document.querySelector('[data-cart-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (!confirm('Empty your entire cart?')) return;
                actionInput.value = 'clear';
                cartForm.submit();
            });
        }
    }

    /* ---------- Product gallery thumbnails ---------- */
    var galleryMain = document.getElementById('productMainImage');
    var thumbsWrap = document.getElementById('productThumbs');

    function bindThumbs() {
        if (!galleryMain) return;
        document.querySelectorAll('[data-thumb]').forEach(function(t) {
            if (t.dataset.bound === '1') return;
            t.dataset.bound = '1';
            t.addEventListener('click', function() {
                galleryMain.src = t.getAttribute('data-thumb');
                document.querySelectorAll('[data-thumb]').forEach(function(x) { x.classList.remove('is-active'); });
                t.classList.add('is-active');
            });
        });
    }

    /* Rebuild the thumbnail strip from a set of image URLs. */
    function renderThumbs(images) {
        if (!thumbsWrap || !images || !images.length) return;
        thumbsWrap.innerHTML = '';
        images.forEach(function(src, i) {
            var img = document.createElement('img');
            img.setAttribute('src', src);
            img.setAttribute('data-thumb', src);
            if (i === 0) img.classList.add('is-active');
            thumbsWrap.appendChild(img);
        });
        bindThumbs();
    }

    /* ---------- Product variant selectors (size / colour) ---------- */
    var productForm = document.querySelector('form[data-product-form]');
    if (productForm) {
        var sizeValue = productForm.querySelector('[data-size-value]');
        var colorValue = productForm.querySelector('[data-color-value]');

        productForm.querySelectorAll('input[name=size]').forEach(function(r) {
            r.addEventListener('change', function() {
                if (sizeValue) sizeValue.textContent = r.value;
            });
        });
        productForm.querySelectorAll('input[name=color]').forEach(function(r) {
            r.addEventListener('change', function() {
                if (colorValue) colorValue.textContent = r.value;
                var imgs = [];
                try { imgs = JSON.parse(r.getAttribute('data-images') || '[]'); } catch (e) { imgs = []; }
                if (imgs.length) {
                    if (galleryMain) galleryMain.src = imgs[0];
                    renderThumbs(imgs);
                } else {
                    var img = r.getAttribute('data-image');
                    if (galleryMain && img) galleryMain.src = img;
                }
            });
        });
    }

    bindThumbs();

    /* ---------- Wishlist toggle (AJAX) ---------- */
    document.querySelectorAll('form[data-wishlist]').forEach(function(form) {
        form.addEventListener('submit', function(ev) {
            if (!window.fetch) return; /* fall back to normal POST */
            ev.preventDefault();
            var btn = form.querySelector('button');

            fetch(form.getAttribute('action') || form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data) return;
                    if (data.error === 'login_required') { form.submit(); return; }
                    if (data.error) return;

                    var on = !!data.saved;
                    if (btn) {
                        btn.classList.toggle('is-on', on);
                        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                        var label = btn.querySelector('.wish-label');
                        if (label) label.textContent = on ? 'Saved to wishlist' : 'Save to wishlist';
                    }
                    document.querySelectorAll('.wish-count').forEach(function(el) {
                        el.textContent = data.count;
                        el.style.display = data.count > 0 ? '' : 'none';
                    });
                })
                .catch(function() { form.submit(); });
        });
    });
})();