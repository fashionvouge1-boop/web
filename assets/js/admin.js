/* =====================================================================
   StyleHub — Admin panel behaviour
   ===================================================================== */
(function() {
    'use strict';

    /* ---------- Mobile sidebar ---------- */
    var side = document.getElementById('adminSide');
    var burger = document.getElementById('adminBurger');
    var overlay = document.getElementById('adminOverlay');

    function closeSide() {
        if (side) side.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-open');
    }
    if (burger && side) {
        burger.addEventListener('click', function() {
            side.classList.toggle('is-open');
            if (overlay) overlay.classList.toggle('is-open', side.classList.contains('is-open'));
        });
    }
    if (overlay) overlay.addEventListener('click', closeSide);

    /* ---------- Confirm destructive actions ---------- */
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('submit', function(ev) {
            if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
        });
    });

    /* ---------- Product form: per-colour image groups ---------- */
    var colorsBox = document.getElementById('colorCheckboxes');
    var imagesBox = document.getElementById('colorImageRows');
    var colorsOther = document.getElementById('colors_other');
    if (colorsBox && imagesBox) {
        function attrEsc(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;'); }
        function htmlEsc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

        function bindColorRemove(row) {
            var btn = row.querySelector('.img-row__remove');
            if (btn) btn.addEventListener('click', function() { row.remove(); });
        }

        function colorImgRow(color) {
            var row = document.createElement('div');
            row.className = 'img-row';
            row.innerHTML =
                '<input type="hidden" name="img_color[]" value="' + attrEsc(color) + '">' +
                '<input class="img-row__url" type="text" name="img_url[]" placeholder="Image URL or upload below" value="">' +
                '<input class="img-row__url" type="file" name="img_file[]" accept="image/*">' +
                '<button type="button" class="img-row__remove" title="Remove">&times;</button>';
            bindColorRemove(row);
            return row;
        }

        function groupFor(color) {
            var found = null;
            imagesBox.querySelectorAll('.color-img-group').forEach(function(g) {
                if (g.getAttribute('data-color-name').toLowerCase() === String(color).toLowerCase()) found = g;
            });
            return found;
        }

        function addColorGroup(color) {
            if (groupFor(color)) return;
            var hint = imagesBox.querySelector('p.muted');
            if (hint) hint.remove();
            var group = document.createElement('div');
            group.className = 'color-img-group';
            group.setAttribute('data-color-name', color);
            group.innerHTML =
                '<div class="color-img-group__head">' +
                    '<span class="img-row__color" style="font-weight:600;font-size:.84rem">' + htmlEsc(color) + '</span>' +
                    '<button type="button" class="btn btn--ghost btn--sm" data-add-color-img>+ Add image</button>' +
                '</div>' +
                '<div class="color-img-group__rows"></div>';
            var rows = group.querySelector('.color-img-group__rows');
            group.querySelector('[data-add-color-img]').addEventListener('click', function() {
                rows.appendChild(colorImgRow(color));
            });
            rows.appendChild(colorImgRow(color));
            imagesBox.appendChild(group);
        }

        /* Bind server-rendered groups. */
        imagesBox.querySelectorAll('.color-img-group').forEach(function(group) {
            var color = group.getAttribute('data-color-name');
            group.querySelectorAll('.img-row').forEach(bindColorRemove);
            var rows = group.querySelector('.color-img-group__rows');
            var addBtn = group.querySelector('[data-add-color-img]');
            if (addBtn && rows) addBtn.addEventListener('click', function() { rows.appendChild(colorImgRow(color)); });
        });

        /* Tick/un-tick preset colours. */
        colorsBox.querySelectorAll('input[type=checkbox]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                if (cb.checked) {
                    addColorGroup(cb.value);
                } else {
                    var group = groupFor(cb.value);
                    if (group) group.remove();
                }
            });
        });

        /* Custom colours typed under "Other colours". */
        if (colorsOther) {
            function customNames() {
                return colorsOther.value.split(',')
                    .map(function(s) { return s.trim(); })
                    .filter(function(s) { return s !== ''; });
            }
            colorsOther.addEventListener('input', function() {
                customNames().forEach(addColorGroup);
            });
            colorsOther.addEventListener('change', function() {
                var keep = {};
                customNames().forEach(function(n) { keep[n.toLowerCase()] = true; });
                colorsBox.querySelectorAll('input[type=checkbox]:checked').forEach(function(cb) {
                    keep[cb.value.toLowerCase()] = true;
                });
                imagesBox.querySelectorAll('.color-img-group').forEach(function(g) {
                    if (!keep[g.getAttribute('data-color-name').toLowerCase()]) g.remove();
                });
            });
        }
    }

    /* ---------- Slug helper ---------- */
    var nameInput = document.getElementById('name');
    var slugInput = document.getElementById('slug');
    if (nameInput && slugInput) {
        var slugTouched = slugInput.value.trim() !== '';
        slugInput.addEventListener('input', function() { slugTouched = true; });
        nameInput.addEventListener('input', function() {
            if (slugTouched) return;
            slugInput.value = nameInput.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        });
    }

    /* ---------- Product form: gallery image rows ---------- */
    var galleryRows = document.getElementById('galleryRows');

    function galleryRowHtml(url) {
        return '<input class="img-row__url" type="text" name="gal_url[]" value="' + (url || '').replace(/"/g, '&quot;') + '" placeholder="Image URL">' +
               '<input class="img-row__url" type="file" name="gal_file[]" accept="image/*">' +
               '<button type="button" class="img-row__remove" title="Remove">&times;</button>';
    }
    function bindGalleryRemove(row) {
        var btn = row.querySelector('.img-row__remove');
        if (btn) btn.addEventListener('click', function() { row.remove(); });
    }
    function addGalleryRow(url) {
        if (!galleryRows) return null;
        var row = document.createElement('div');
        row.className = 'img-row';
        row.innerHTML = galleryRowHtml(url);
        galleryRows.appendChild(row);
        bindGalleryRemove(row);
        return row;
    }
    if (galleryRows) {
        galleryRows.querySelectorAll('.img-row').forEach(bindGalleryRemove);
        var addGalBtn = document.getElementById('addGalleryImg');
        if (addGalBtn) addGalBtn.addEventListener('click', function() { addGalleryRow(''); });
    }

    /* ---------- Product form: import from a product link ---------- */
    var importForm = document.querySelector('form[data-import-url]');
    var importBtn = document.getElementById('importBtn');
    var importUrl = document.getElementById('importUrl');
    var importStatus = document.getElementById('importStatus');

    function setStatus(msg, isError) {
        if (!importStatus) return;
        importStatus.textContent = msg;
        importStatus.style.color = isError ? '#b91c1c' : '';
    }
    function setField(id, value) {
        var el = document.getElementById(id);
        if (el && value !== undefined && value !== null && value !== '') {
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    /* Tick matching preset checkboxes, return the values that had no preset. */
    function markVariants(name, values) {
        var list = values || [];
        var lower = list.map(function(v) { return String(v).toLowerCase(); });
        var matched = {};
        document.querySelectorAll('input[name="' + name + '[]"]').forEach(function(cb) {
            var idx = lower.indexOf(cb.value.toLowerCase());
            if (idx !== -1) {
                cb.checked = true;
                matched[idx] = true;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        return list.filter(function(v, i) { return !matched[i]; });
    }

    function fillFromImport(d) {
        setField('name', d.name);
        setField('description', d.description);
        setField('price', d.price);
        setField('sale_price', d.sale_price);

        var otherSizes = markVariants('sizes', d.sizes);
        if (otherSizes.length) setField('sizes_other', otherSizes.join(', '));

        var otherColors = markVariants('colors', d.colors);
        if (otherColors.length) setField('colors_other', otherColors.join(', '));

        if (d.images && d.images.length) {
            d.images.forEach(function(u) { addGalleryRow(u); });
        }

        var bits = [];
        if (d.name) bits.push('name');
        if (d.price) bits.push('price');
        bits.push((d.images ? d.images.length : 0) + ' image(s)');
        if (d.colors && d.colors.length) bits.push(d.colors.length + ' colour(s)');
        if (d.sizes && d.sizes.length) bits.push(d.sizes.length + ' size(s)');
        setStatus('Filled from the link: ' + bits.join(', ') + '. Review the details and upload the photos.', false);
    }

    if (importBtn && importUrl && importForm) {
        importBtn.addEventListener('click', function() {
            var url = importUrl.value.trim();
            if (!url) { setStatus('Paste a product URL first.', true); return; }
            var csrf = importForm.querySelector('input[name=csrf]');
            importBtn.disabled = true;
            setStatus('Reading the product page…', false);

            var fd = new FormData();
            fd.append('csrf', csrf ? csrf.value : '');
            fd.append('url', url);

            fetch(importForm.getAttribute('data-import-url'), {
                    method: 'POST', body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    importBtn.disabled = false;
                    if (!d || !d.ok) { setStatus((d && d.error) || 'Could not read that page.', true); return; }
                    fillFromImport(d);
                })
                .catch(function() {
                    importBtn.disabled = false;
                    setStatus('Import failed — check the URL and try again.', true);
                });
        });
    }
})();