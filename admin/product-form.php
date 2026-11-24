<?php
/**
 * Admin — add or edit a product.
 * Supports main image (URL or upload), price/discount, size & colour
 * variants and an optional image per colour.
 */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

$presetSizes  = ['S', 'M', 'L', 'XL', 'XXL', '6', '7', '8', '9', '10', '11', '12'];
$presetColors = ['Black', 'White', 'Navy', 'Grey', 'Beige', 'Brown', 'Tan', 'Red',
                 'Maroon', 'Blue', 'Green', 'Emerald', 'Olive', 'Pink', 'Yellow',
                 'Purple', 'Orange', 'Cream'];

$categories = all_categories();

$errors = [];
$form   = [
    'name'        => '',
    'slug'        => '',
    'category_id' => (int)($categories[0]['id'] ?? 0),
    'gender'      => 'unisex',
    'description' => '',
    'price'       => '',
    'sale_price'  => '',
    'stock'       => '0',
    'featured'    => 0,
    'is_active'   => 1,
    'image'       => '',
    'sizes'       => '',
    'colors'      => '',
];
$existingImages = [];
$existingGallery = [];

/* ---------- Load existing product ---------- */
if ($isEdit) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $product = $st->fetch();
    if (!$product) {
        set_flash('error', 'Product not found.');
        redirect('admin/products.php');
    }
    $form = [
        'name'        => $product['name'],
        'slug'        => $product['slug'],
        'category_id' => (int)$product['category_id'],
        'gender'      => $product['gender'],
        'description' => (string)$product['description'],
        'price'       => $product['price'],
        'sale_price'  => $product['sale_price'] ?? '',
        'stock'       => (string)$product['stock'],
        'featured'    => (int)$product['featured'],
        'is_active'   => (int)$product['is_active'],
        'image'       => $product['image'],
        'sizes'       => (string)$product['sizes'],
        'colors'      => (string)$product['colors'],
    ];
    $existingImages = product_colour_images($id);
    $existingGallery = product_gallery_images($id);
}

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $form['name']        = trim((string)($_POST['name'] ?? ''));
    $form['slug']        = trim((string)($_POST['slug'] ?? ''));
    $form['category_id'] = (int)($_POST['category_id'] ?? 0);
    $form['gender']      = (string)($_POST['gender'] ?? 'unisex');
    $form['description'] = trim((string)($_POST['description'] ?? ''));
    $form['price']       = trim((string)($_POST['price'] ?? ''));
    $form['sale_price']  = trim((string)($_POST['sale_price'] ?? ''));
    $form['stock']       = trim((string)($_POST['stock'] ?? '0'));
    $form['featured']    = isset($_POST['featured']) ? 1 : 0;
    $form['is_active']   = isset($_POST['is_active']) ? 1 : 0;
    $form['image']       = trim((string)($_POST['image'] ?? ''));

    $sizeList        = array_map('trim', (array)($_POST['sizes'] ?? []));
    $sizeList[]      = (string)($_POST['sizes_other'] ?? '');
    $form['sizes']   = clean_csv_field(implode(',', $sizeList));

    $colorList       = array_map('trim', (array)($_POST['colors'] ?? []));
    $colorList[]     = (string)($_POST['colors_other'] ?? '');
    $form['colors']  = clean_csv_field(implode(',', $colorList));

    /* Rebuild colour-image map from the submitted rows (for re-render). */
    $existingImages = [];
    foreach ((array)($_POST['img_color'] ?? []) as $i => $c) {
        $c = trim((string)$c);
        $u = trim((string)($_POST['img_url'][$i] ?? ''));
        if ($c !== '' && $u !== '') {
            $existingImages[$c][] = $u;
        }
    }

    /* Rebuild gallery rows from the submitted URLs (for re-render). */
    $existingGallery = [];
    foreach ((array)($_POST['gal_url'] ?? []) as $u) {
        $u = trim((string)$u);
        if ($u !== '') {
            $existingGallery[] = $u;
        }
    }

    /* Validation */
    if ($form['name'] === '') {
        $errors[] = 'Product name is required.';
    }
    if ($form['price'] === '' || !is_numeric($form['price']) || (float)$form['price'] <= 0) {
        $errors[] = 'A valid price (greater than 0) is required.';
    }
    if ($form['sale_price'] !== '' && (!is_numeric($form['sale_price']) || (float)$form['sale_price'] < 0)) {
        $errors[] = 'Sale price must be a valid number.';
    }
    if ($form['category_id'] <= 0) {
        $errors[] = 'Please choose a category.';
    }
    if (!in_array($form['gender'], ['men', 'women', 'unisex'], true)) {
        $form['gender'] = 'unisex';
    }

    /* Main image — a file upload wins over the URL field. */
    $uploadedMain = save_upload(upload_entry($_FILES['image_file'] ?? [], 0));
    if ($uploadedMain !== null) {
        $form['image'] = $uploadedMain;
    }

    /* Extra gallery images (URL or upload), in order. */
    $galleryItems = [];
    foreach ((array)($_POST['gal_url'] ?? []) as $i => $u) {
        $path = save_upload(upload_entry($_FILES['gal_file'] ?? [], (int)$i));
        if ($path === null) {
            $path = trim((string)$u);
        }
        if ($path !== '') {
            $galleryItems[] = $path;
        }
    }
    /* Fall back to the first gallery image when no main image was given. */
    if ($form['image'] === '' && $galleryItems) {
        $form['image'] = $galleryItems[0];
    }
    if ($form['image'] === '') {
        $errors[] = 'A product image (URL or upload) is required.';
    }

    if (!$errors) {
        $slug = $form['slug'] !== '' ? slugify($form['slug']) : slugify($form['name']);
        if ($slug === '') {
            $slug = 'product';
        }
        $base = $slug;
        $n    = 1;
        do {
            $chk = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id <> ?');
            $chk->execute([$slug, $isEdit ? $id : 0]);
            $taken = (bool)$chk->fetch();
            if ($taken) {
                $slug = $base . '-' . (++$n);
            }
        } while ($taken);

        $sale   = ($form['sale_price'] === '') ? null : round((float)$form['sale_price'], 2);
        $params = [
            $form['category_id'], $form['name'], $slug,
            $form['description'] !== '' ? $form['description'] : null,
            round((float)$form['price'], 2), $sale, $form['gender'], $form['image'],
            $form['sizes'] !== '' ? $form['sizes'] : null,
            $form['colors'] !== '' ? $form['colors'] : null,
            max(0, (int)$form['stock']), $form['featured'], $form['is_active'],
        ];

        if ($isEdit) {
            $params[] = $id;
            $pdo->prepare('UPDATE products SET category_id=?, name=?, slug=?, description=?, price=?, sale_price=?,
                           gender=?, image=?, sizes=?, colors=?, stock=?, featured=?, is_active=? WHERE id=?')
                ->execute($params);
        } else {
            $pdo->prepare('INSERT INTO products (category_id, name, slug, description, price, sale_price, gender,
                           image, sizes, colors, stock, featured, is_active)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($params);
            $id     = (int)$pdo->lastInsertId();
            $isEdit = true;
        }

        /* Sync images */
        $imgColors = (array)($_POST['img_color'] ?? []);
        $imgUrls   = (array)($_POST['img_url'] ?? []);
        $fileBag   = $_FILES['img_file'] ?? [];

        $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$id]);
        $ins   = $pdo->prepare('INSERT INTO product_images (product_id, color, image, sort_order) VALUES (?,?,?,?)');
        $order = 0;

        /* General gallery images (no colour). */
        foreach ($galleryItems as $g) {
            $ins->execute([$id, null, $g, $order++]);
        }

        /* Per-colour images. */
        foreach ($imgColors as $i => $color) {
            $color = trim((string)$color);
            if ($color === '') {
                continue;
            }
            $path = save_upload(upload_entry($fileBag, (int)$i));
            if ($path === null) {
                $path = trim((string)($imgUrls[$i] ?? ''));
            }
            if ($path === '') {
                continue;
            }
            $ins->execute([$id, $color, $path, $order++]);
        }

        set_flash('success', $isEdit ? 'Product saved.' : 'Product added to the catalogue.');
        redirect('admin/products.php');
    }
}

/* ---------- Split current sizes/colours into preset vs custom ---------- */
$currentSizes  = array_values(array_filter(array_map('trim', explode(',', $form['sizes'])), fn($v) => $v !== ''));
$checkedSizes  = array_intersect($currentSizes, $presetSizes);
$otherSizes    = implode(', ', array_diff($currentSizes, $presetSizes));

$currentColors = array_values(array_filter(array_map('trim', explode(',', $form['colors'])), fn($v) => $v !== ''));
$checkedColors = array_intersect($currentColors, $presetColors);
$otherColors   = implode(', ', array_diff($currentColors, $presetColors));

$pageTitle = $isEdit ? 'Edit Product' : 'Add Product';
$adminNav  = $isEdit ? 'products' : 'newproduct';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/products.php')) ?>">&larr; All products</a>
    <h2 style="margin:0"><?= $isEdit ? 'Edit Product' : 'Add New Product' ?></h2>
    <?php if ($isEdit): ?>
        <a class="btn btn--outline btn--sm" href="<?= e(url('product.php?slug=' . urlencode($form['slug']))) ?>" target="_blank" rel="noopener">Preview &#8599;</a>
    <?php endif; ?>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert--error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" data-import-url="<?= e(url('admin/import-url.php')) ?>">
    <?= csrf_field() ?>

    <div class="panel" id="importPanel">
        <h3>Import from a product link</h3>
        <p class="muted" style="font-size:.84rem;margin-top:0">
            Paste a product URL from any store. We read the page and fill in the name, description,
            price, discount, colours, sizes and images below — then you can edit everything and upload the photos.
        </p>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
            <div class="field" style="flex:1;min-width:260px;margin:0">
                <label for="importUrl">Product URL</label>
                <input id="importUrl" type="text" placeholder="https://example.com/product/...">
            </div>
            <button class="btn btn--accent" type="button" id="importBtn">&#10024; Fetch &amp; fill</button>
        </div>
        <div id="importStatus" class="hint" style="margin-top:.6rem"></div>
    </div>

    <div class="detail-grid">
        <div>
            <div class="panel">
                <h3>Basic Information</h3>
                <div class="form-grid">
                    <div class="field field--full">
                        <label for="name">Product name *</label>
                        <input id="name" type="text" name="name" required value="<?= e($form['name']) ?>" placeholder="e.g. Floral Wrap Midi Dress">
                    </div>
                    <div class="field field--full">
                        <label for="slug">URL slug</label>
                        <input id="slug" type="text" name="slug" value="<?= e($form['slug']) ?>" placeholder="auto-generated from the name">
                        <div class="hint">Leave blank to auto-create from the product name.</div>
                    </div>
                    <div class="field">
                        <label for="category_id">Category *</label>
                        <select id="category_id" name="category_id" required>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $form['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?> (<?= e($c['gender']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="gender">Shop for</label>
                        <select id="gender" name="gender">
                            <?php foreach (['women' => "Women", 'men' => "Men", 'unisex' => "Unisex"] as $g => $lbl): ?>
                                <option value="<?= $g ?>" <?= $form['gender'] === $g ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field field--full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Describe the product, fabric, fit..."><?= e($form['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h3>Pricing &amp; Stock</h3>
                <div class="form-grid">
                    <div class="field">
                        <label for="price">Price (<?= e(CURRENCY) ?>) *</label>
                        <input id="price" type="number" step="0.01" min="0" name="price" required value="<?= e((string)$form['price']) ?>">
                    </div>
                    <div class="field">
                        <label for="sale_price">Sale / discounted price (<?= e(CURRENCY) ?>)</label>
                        <input id="sale_price" type="number" step="0.01" min="0" name="sale_price" value="<?= e((string)$form['sale_price']) ?>">
                        <div class="hint">Leave blank for no discount. The discount % is calculated automatically.</div>
                    </div>
                    <div class="field">
                        <label for="stock">Stock quantity</label>
                        <input id="stock" type="number" min="0" name="stock" value="<?= e((string)$form['stock']) ?>">
                    </div>
                </div>
                <div class="check-grid">
                    <label class="check"><input type="checkbox" name="featured" value="1" <?= $form['featured'] ? 'checked' : '' ?>> Featured on home page</label>
                    <label class="check"><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>> Visible in store</label>
                </div>
            </div>

            <div class="panel">
                <h3>Size Variants</h3>
                <p class="muted" style="font-size:.84rem">Pick the sizes this product comes in (clothing: S–XXL, shoes: 6–12). Leave all unchecked for a one-size product.</p>
                <div class="check-grid">
                    <?php foreach ($presetSizes as $s): ?>
                        <label class="check"><input type="checkbox" name="sizes[]" value="<?= e($s) ?>" <?= in_array($s, $checkedSizes, true) ? 'checked' : '' ?>> <?= e($s) ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="field" style="margin-top:1rem;margin-bottom:0">
                    <label for="sizes_other">Other sizes (comma separated)</label>
                    <input id="sizes_other" type="text" name="sizes_other" value="<?= e($otherSizes) ?>" placeholder="e.g. 3XL, 4XL">
                </div>
            </div>

            <div class="panel">
                <h3>Colour Variants</h3>
                <p class="muted" style="font-size:.84rem">Selling one design in several colours? Tick each colour below.</p>
                <div class="check-grid" id="colorCheckboxes">
                    <?php foreach ($presetColors as $c): ?>
                        <label class="check"><input type="checkbox" name="colors[]" value="<?= e($c) ?>" <?= in_array($c, $checkedColors, true) ? 'checked' : '' ?>> <?= e($c) ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="field" style="margin-top:1rem;margin-bottom:0">
                    <label for="colors_other">Other colours (comma separated)</label>
                    <input id="colors_other" type="text" name="colors_other" value="<?= e($otherColors) ?>" placeholder="e.g. Mint, Coral">
                </div>
            </div>
        </div>

        <aside>
            <div class="panel">
                <h3>Main Image *</h3>
                <div style="text-align:center;margin-bottom:.8rem">
                    <img id="mainPreview" src="<?= e($form['image'] ? image_url($form['image']) : '') ?>" alt=""
                         style="width:150px;height:190px;object-fit:cover;border-radius:12px;border:1px solid var(--line);<?= $form['image'] ? '' : 'display:none' ?>">
                </div>
                <div class="field">
                    <label for="image">Image URL</label>
                    <input id="image" type="text" name="image" value="<?= e($form['image']) ?>" placeholder="https://... or upload below"
                           oninput="var p=document.getElementById('mainPreview'); if(this.value){p.src=this.value;p.style.display='block';}">
                </div>
                <div class="field" style="margin-bottom:0">
                    <label for="image_file">…or upload a file</label>
                    <input id="image_file" type="file" name="image_file" accept="image/*">
                    <div class="hint">JPG, PNG, WEBP or GIF.</div>
                </div>
            </div>

            <div class="panel">
                <h3>Gallery Images</h3>
                <p class="muted" style="font-size:.82rem;margin-top:0">Add as many photos as you like — paste an image URL or upload a file. The first one is used when no main image is set.</p>
                <div id="galleryRows">
                    <?php foreach ($existingGallery as $img): ?>
                        <div class="img-row">
                            <input class="img-row__url" type="text" name="gal_url[]" value="<?= e($img) ?>" placeholder="Image URL">
                            <input class="img-row__url" type="file" name="gal_file[]" accept="image/*">
                            <button type="button" class="img-row__remove" title="Remove">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn--ghost btn--sm" type="button" id="addGalleryImg" style="margin-top:.6rem">+ Add another image</button>
            </div>

            <div class="panel">
                <h3>Images per Colour</h3>
                <p class="muted" style="font-size:.82rem;margin-top:0">Optional — add one or more photos for each colour. When a shopper picks a colour, its photos appear in the gallery. Tick a colour above (or type it under <em>Other colours</em>) and its image group appears here.</p>
                <div id="colorImageRows">
                    <?php foreach ($existingImages as $color => $imgs): ?>
                        <div class="color-img-group" data-color-name="<?= e($color) ?>">
                            <div class="color-img-group__head">
                                <span class="img-row__color" style="font-weight:600;font-size:.84rem"><?= e($color) ?></span>
                                <button type="button" class="btn btn--ghost btn--sm" data-add-color-img>+ Add image</button>
                            </div>
                            <div class="color-img-group__rows">
                                <?php foreach ($imgs as $img): ?>
                                    <div class="img-row">
                                        <input type="hidden" name="img_color[]" value="<?= e($color) ?>">
                                        <input class="img-row__url" type="text" name="img_url[]" value="<?= e($img) ?>" placeholder="Image URL">
                                        <input class="img-row__url" type="file" name="img_file[]" accept="image/*">
                                        <button type="button" class="img-row__remove" title="Remove">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$existingImages): ?>
                        <p class="muted" style="font-size:.82rem">Tick a colour above and its image group will appear here.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <button class="btn btn--accent btn--block btn--lg" type="submit"><?= $isEdit ? 'Save changes' : 'Add product' ?></button>
                <a class="btn btn--ghost btn--block" style="margin-top:.6rem" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
            </div>
        </aside>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
