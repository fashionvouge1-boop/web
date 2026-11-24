<?php
require_once __DIR__ . '/includes/functions.php';

/* ---------------- Collect & sanitise filters ---------------- */
$q        = trim((string)($_GET['q'] ?? ''));
$gender   = in_array($_GET['gender'] ?? '', ['men', 'women'], true) ? $_GET['gender'] : '';
$catSlugs = array_filter(array_map('strval', (array)($_GET['category'] ?? [])));
$minPrice = ($_GET['min'] ?? '') !== '' ? max(0, (float)$_GET['min']) : null;
$maxPrice = ($_GET['max'] ?? '') !== '' ? max(0, (float)$_GET['max']) : null;
$filter   = in_array($_GET['filter'] ?? '', ['featured', 'sale'], true) ? $_GET['filter'] : '';
$sort     = $_GET['sort'] ?? 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;

$where  = ['p.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($gender !== '') {
    $where[] = '(p.gender = ? OR p.gender = "unisex")';
    $params[] = $gender;
}
if ($catSlugs) {
    $placeholders = implode(',', array_fill(0, count($catSlugs), '?'));
    $where[] = "c.slug IN ($placeholders)";
    foreach ($catSlugs as $s) { $params[] = $s; }
}
if ($minPrice !== null) { $where[] = 'COALESCE(p.sale_price, p.price) >= ?'; $params[] = $minPrice; }
if ($maxPrice !== null) { $where[] = 'COALESCE(p.sale_price, p.price) <= ?'; $params[] = $maxPrice; }
if ($filter === 'featured') { $where[] = 'p.featured = 1'; }
if ($filter === 'sale')     { $where[] = 'p.sale_price IS NOT NULL'; }

$orderBy = [
    'newest'    => 'p.created_at DESC',
    'price_asc' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_desc'=> 'COALESCE(p.sale_price, p.price) DESC',
    'name'      => 'p.name ASC',
][$sort] ?? 'p.created_at DESC';

$whereSql = implode(' AND ', $where);

/* ---------------- Pagination count ---------------- */
$countStmt = db()->prepare("SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* ---------------- Fetch products ---------------- */
$sql = "SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$allCats = all_categories();

/** Build a query string preserving current filters, with overrides. */
function shop_query(array $overrides = []): string
{
    $params = $_GET;
    unset($params['page']);
    foreach ($overrides as $k => $v) {
        if ($v === null) { unset($params[$k]); } else { $params[$k] = $v; }
    }
    return http_build_query($params);
}

$pageTitle = 'Shop';

/* ---- SEO: describe the current filtered view ---- */
$__bits = [];
if ($q !== '')         { $__bits[] = 'search results for "' . $q . '"'; }
elseif ($catSlugs)     { $__bits[] = implode(', ', array_map(fn($s) => ucwords(str_replace('-', ' ', $s)), $catSlugs)); }
elseif ($gender !== ''){ $__bits[] = $gender . "'s fashion"; }
else                   { $__bits[] = 'the full collection'; }
$pageDescription = 'Shop ' . implode(' ', $__bits) . ' at ' . SITE_NAME . ' — ' . number_format($totalRows)
                 . ' products with fast delivery and easy 30-day returns.';
if ($page > 1) {
    $noindex = true; // keep paginated pages out of the index
}
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a> / <span>Shop</span>
        <?php if ($gender): ?> / <span><?= e(ucfirst($gender)) ?></span><?php endif; ?>
    </div>

    <div class="shop">
        <!-- ============ FILTERS SIDEBAR ============ -->
        <aside class="filters" id="filters">
            <form method="get" action="<?= e(url('shop.php')) ?>">
                <h3>Filters</h3>

                <div class="filter-block">
                    <h4>Search</h4>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products..."
                           style="width:100%;padding:.6rem .8rem;border:1px solid var(--line);border-radius:9px">
                </div>

                <div class="filter-block">
                    <h4>Shopping for</h4>
                    <label class="check"><input type="radio" name="gender" value="" <?= $gender === '' ? 'checked' : '' ?>> All</label>
                    <label class="check"><input type="radio" name="gender" value="women" <?= $gender === 'women' ? 'checked' : '' ?>> Women</label>
                    <label class="check"><input type="radio" name="gender" value="men" <?= $gender === 'men' ? 'checked' : '' ?>> Men</label>
                </div>

                <div class="filter-block">
                    <h4>Category</h4>
                    <?php foreach ($allCats as $c): ?>
                        <label class="check">
                            <input type="checkbox" name="category[]" value="<?= e($c['slug']) ?>"
                                <?= in_array($c['slug'], $catSlugs, true) ? 'checked' : '' ?>>
                            <?= e($c['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-block">
                    <h4>Price</h4>
                    <div class="price-row">
                        <input type="number" name="min" min="0" placeholder="Min" value="<?= $minPrice !== null ? e((string)$minPrice) : '' ?>">
                        <span>&ndash;</span>
                        <input type="number" name="max" min="0" placeholder="Max" value="<?= $maxPrice !== null ? e((string)$maxPrice) : '' ?>">
                    </div>
                </div>

                <div class="filter-block">
                    <h4>Collection</h4>
                    <label class="check"><input type="radio" name="filter" value="" <?= $filter === '' ? 'checked' : '' ?>> All products</label>
                    <label class="check"><input type="radio" name="filter" value="featured" <?= $filter === 'featured' ? 'checked' : '' ?>> Featured</label>
                    <label class="check"><input type="radio" name="filter" value="sale" <?= $filter === 'sale' ? 'checked' : '' ?>> On sale</label>
                </div>

                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <button class="btn btn--accent btn--block" type="submit" style="margin-top:1rem">Apply filters</button>
                <a class="btn btn--ghost btn--block" href="<?= e(url('shop.php')) ?>" style="margin-top:.5rem">Clear all</a>
            </form>
        </aside>

        <!-- ============ PRODUCTS ============ -->
        <div>
            <div class="toolbar">
                <button class="btn btn--ghost btn--sm filters-toggle" type="button">&#9776; Filters</button>
                <span class="muted"><?= (int)$totalRows ?> product<?= $totalRows === 1 ? '' : 's' ?> found</span>
                <label class="muted" style="display:flex;align-items:center;gap:.5rem;font-size:.88rem">
                    Sort by
                    <select onchange="var u=new URL(location.href);u.searchParams.set('sort',this.value);u.searchParams.delete('page');location.href=u;">
                        <option value="newest"     <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="price_asc"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name"       <?= $sort === 'name' ? 'selected' : '' ?>>Name: A–Z</option>
                    </select>
                </label>
            </div>

            <?php if (!$products): ?>
                <div class="empty">
                    <h2>No products found</h2>
                    <p>Try adjusting your filters or search for something else.</p>
                    <a class="btn btn--accent" href="<?= e(url('shop.php')) ?>">Reset filters</a>
                </div>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($products as $p): echo render_product_card($p); endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= e(shop_query(['page' => $page - 1])) ?>">&laquo;</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="is-active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= e(shop_query(['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= e(shop_query(['page' => $page + 1])) ?>">&raquo;</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
