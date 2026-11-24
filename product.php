<?php
require_once __DIR__ . '/includes/functions.php';

$slug = (string)($_GET['slug'] ?? '');
$product = $slug !== '' ? get_product_by_slug($slug) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty"><h2>Product not found</h2>'
       . '<p>The product you are looking for is unavailable or has been removed.</p>'
       . '<a class="btn btn--accent" href="' . e(url('shop.php')) . '">Continue shopping</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$inStock = (int)$product['stock'] > 0;
$off     = discount_percent($product);
$sizes   = product_sizes($product);
$colors  = product_colors($product);
$colourImages = product_colour_images((int)$product['id']);
$gallery      = product_gallery_images((int)$product['id']);
$mainImage    = image_url($product['image']);
$hasColourImages = false;
foreach ($colourImages as $ciImgs) {
    if ($ciImgs) { $hasColourImages = true; break; }
}

$rating   = product_rating((int)$product['id']);
$reviews  = product_reviews((int)$product['id']);
$canReview = is_logged_in();

/* Star breakdown for the summary bars. */
$breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $rv) {
    $star = max(1, min(5, (int)$rv['rating']));
    $breakdown[$star]++;
}

/* Has the signed-in shopper already reviewed this product? */
$myReview = null;
if ($canReview) {
    $rvStmt = db()->prepare('SELECT rating, status, created_at FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
    $rvStmt->execute([(int)$product['id'], (int)current_user()['id']]);
    $myReview = $rvStmt->fetch() ?: null;
}

$relStmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p
                          JOIN categories c ON c.id = p.category_id
                          WHERE p.is_active = 1 AND p.category_id = ? AND p.id <> ?
                          ORDER BY RAND() LIMIT 4');
$relStmt->execute([$product['category_id'], $product['id']]);
$related = $relStmt->fetchAll();

/* ---- SEO: meta description, OpenGraph image and Product structured data ---- */
$__desc = trim(preg_replace('/\s+/', ' ', strip_tags((string)$product['description'])));
if ($__desc === '') {
    $__desc = $product['name'] . ' — ' . $product['category_name'] . ' available at ' . SITE_NAME . '.';
}
$pageDescription = mb_strimwidth($__desc, 0, 160, '…');

$__imgRaw  = (string)$product['image'];
$pageImage = preg_match('#^(https?:)?//#', $__imgRaw) ? $__imgRaw : absolute_url($__imgRaw);

$pageJsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => $pageDescription,
    'image'       => array_values(array_filter([$pageImage])),
    'sku'         => (string)$product['id'],
    'brand'       => ['@type' => 'Brand', 'name' => SITE_NAME],
    'category'    => $product['category_name'],
    'offers'      => [
        '@type'         => 'Offer',
        'url'           => absolute_url('product.php?slug=' . urlencode($product['slug'])),
        'priceCurrency' => 'INR',
        'price'         => number_format(product_price($product), 2, '.', ''),
        'availability'  => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
];
if ($rating['count'] > 0) {
    $pageJsonLd['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => $rating['avg'],
        'reviewCount' => $rating['count'],
    ];
}

$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a> /
        <a href="<?= e(url('shop.php')) ?>">Shop</a> /
        <a href="<?= e(url('shop.php?category=' . $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a> /
        <span><?= e($product['name']) ?></span>
    </div>

    <div class="product">
        <div>
            <div class="product__media">
                <img id="productMainImage" src="<?= e($mainImage) ?>" alt="<?= e($product['name']) ?>" data-default="<?= e($mainImage) ?>">
            </div>
            <?php if ($gallery || $hasColourImages): ?>
            <div class="product__thumbs" id="productThumbs">
                <img class="is-active" src="<?= e($mainImage) ?>" data-thumb="<?= e($mainImage) ?>" alt="">
                <?php foreach ($gallery as $g): $gUrl = image_url($g); if ($gUrl === '' || $gUrl === $mainImage) { continue; } ?>
                    <img src="<?= e($gUrl) ?>" data-thumb="<?= e($gUrl) ?>" alt="">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="product__info">
            <span class="card__cat"><?= e($product['category_name']) ?> &middot; <?= e(ucfirst($product['gender'])) ?></span>
            <h1 class="product__title"><?= e($product['name']) ?></h1>

            <div class="product__price">
                <span class="price-now"><?= money(product_price($product)) ?></span>
                <?php if ($off > 0): ?>
                    <span class="price-was"><?= money($product['price']) ?></span>
                    <span class="price-off">Save <?= $off ?>%</span>
                <?php endif; ?>
            </div>

            <div class="product__meta">
                <span><?= $inStock ? '&#10003; In stock (' . (int)$product['stock'] . ' left)' : '&#10007; Out of stock' ?></span>
                <a href="#reviews" class="product__meta-rating">
                    <?= star_html($rating['avg']) ?>
                    <span><?= $rating['count'] > 0
                        ? number_format($rating['avg'], 1) . ' (' . $rating['count'] . ' review' . ($rating['count'] === 1 ? '' : 's') . ')'
                        : 'No reviews yet' ?></span>
                </a>
                <span>SKU: SH-<?= str_pad((string)$product['id'], 5, '0', STR_PAD_LEFT) ?></span>
            </div>

            <p class="product__desc"><?= nl2br(e($product['description'])) ?></p>

            <form method="post" action="<?= e(url('cart.php')) ?>" data-product-form>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

                <?php if ($sizes): ?>
                <div class="variant">
                    <div class="variant__head">
                        <span class="variant__name">Select Size</span>
                        <span class="variant__value" data-size-value></span>
                    </div>
                    <div class="size-pills">
                        <?php foreach ($sizes as $s): ?>
                            <label class="size-pill">
                                <input type="radio" name="size" value="<?= e($s) ?>" required>
                                <span><?= e($s) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($colors): ?>
                <div class="variant">
                    <div class="variant__head">
                        <span class="variant__name">Select Colour</span>
                        <span class="variant__value" data-color-value></span>
                    </div>
                    <div class="swatches">
                        <?php foreach ($colors as $c):
                            $cImgs = array_values(array_filter(array_map('image_url', $colourImages[$c] ?? [])));
                            $cImg  = $cImgs[0] ?? $mainImage;
                        ?>
                            <label class="swatch" title="<?= e($c) ?>">
                                <input type="radio" name="color" value="<?= e($c) ?>" required
                                       data-image="<?= e($cImg) ?>"
                                       data-images="<?= e(json_encode($cImgs, JSON_UNESCAPED_SLASHES)) ?>">
                                <span class="swatch__dot" style="--swatch:<?= e(color_hex($c)) ?>"></span>
                                <span class="swatch__name"><?= e($c) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
                    <div class="qty">
                        <button type="button" data-step="down" aria-label="Decrease">&minus;</button>
                        <input type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$product['stock']) ?>">
                        <button type="button" data-step="up" aria-label="Increase">+</button>
                    </div>
                </div>

                <div class="product__buy">
                    <button class="btn btn--outline btn--lg" type="submit" name="action" value="add" <?= $inStock ? '' : 'disabled' ?>>Add to Cart</button>
                    <button class="btn btn--accent btn--lg" type="submit" name="action" value="buynow" <?= $inStock ? '' : 'disabled' ?>>Buy Now</button>
                </div>
            </form>

            <?php $wishSaved = wishlist_has((int)$product['id']); ?>
            <form method="post" action="<?= e(url('wishlist.php')) ?>" class="product__wish" data-wishlist>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                <button type="submit" class="wish-link<?= $wishSaved ? ' is-on' : '' ?>" aria-pressed="<?= $wishSaved ? 'true' : 'false' ?>">
                    <span class="wish-heart">&#9829;</span>
                    <span class="wish-label"><?= $wishSaved ? 'Saved to wishlist' : 'Save to wishlist' ?></span>
                </button>
            </form>

            <ul class="product__features">
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Free delivery on orders over <?= money(FREE_SHIPPING_OVER) ?></li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> 30-day hassle-free returns &amp; exchanges</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> 100% secure online checkout (UPI, Cards, Netbanking)</li>
            </ul>

            <div class="trust-row">
                <div class="trust"><strong>Authentic</strong>Genuine products only</div>
                <div class="trust"><strong>Fast Dispatch</strong>Shipped within 24h</div>
                <div class="trust"><strong>Support</strong>Mon–Sat, 9am–7pm</div>
            </div>
        </div>
    </div>

    <section class="section reviews" id="reviews">
        <div class="section__head">
            <div><h2>Customer Reviews</h2><p>What shoppers say about the <?= e($product['name']) ?>.</p></div>
        </div>

        <div class="reviews__layout">
            <aside class="panel review-summary">
                <div class="review-summary__score">
                    <span class="review-summary__avg"><?= $rating['count'] ? number_format($rating['avg'], 1) : '0.0' ?></span>
                    <?= star_html($rating['avg']) ?>
                    <span class="muted"><?= $rating['count'] ?> review<?= $rating['count'] === 1 ? '' : 's' ?></span>
                </div>
                <div class="review-summary__bars">
                    <?php for ($s = 5; $s >= 1; $s--):
                        $c   = $breakdown[$s];
                        $pct = $rating['count'] ? (int)round($c / $rating['count'] * 100) : 0; ?>
                        <div class="rating-bar">
                            <span class="rating-bar__label"><?= $s ?> &#9733;</span>
                            <span class="rating-bar__track"><span class="rating-bar__fill" style="width:<?= $pct ?>%"></span></span>
                            <span class="rating-bar__count"><?= $c ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
                <?php if ($canReview && !$myReview): ?>
                    <a class="btn btn--accent btn--block" href="#write-review" style="margin-top:1rem">Write a review</a>
                <?php elseif (!$canReview): ?>
                    <a class="btn btn--outline btn--block" href="<?= e(url('account/login.php')) ?>" style="margin-top:1rem">Sign in to review</a>
                <?php endif; ?>
            </aside>

            <div class="reviews__main">
                <?php if (!$reviews): ?>
                    <div class="panel empty" style="padding:2.5rem 1rem">
                        <p>No reviews yet. Be the first to share your thoughts!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $rv): ?>
                        <article class="review">
                            <div class="review__head">
                                <div class="review__who">
                                    <span class="review__avatar"><?= e(mb_strtoupper(mb_substr($rv['author_name'], 0, 1))) ?></span>
                                    <div>
                                        <strong><?= e($rv['author_name']) ?></strong>
                                        <?php if (!empty($rv['user_id']) && user_purchased_product((int)$rv['user_id'], (int)$product['id'])): ?>
                                            <span class="badge badge-success review__verified">Verified purchase</span>
                                        <?php endif; ?>
                                        <div class="muted" style="font-size:.78rem"><?= e(date('d M Y', strtotime($rv['created_at']))) ?></div>
                                    </div>
                                </div>
                                <?= star_html((float)$rv['rating']) ?>
                            </div>
                            <?php if (!empty($rv['title'])): ?><h4 class="review__title"><?= e($rv['title']) ?></h4><?php endif; ?>
                            <p class="review__body"><?= nl2br(e($rv['body'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canReview && !$myReview): ?>
        <div class="panel" id="write-review" style="margin-top:1.4rem">
            <h3>Write a review</h3>
            <form method="post" action="<?= e(url('review.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

                <div class="field">
                    <label>Your rating</label>
                    <div class="rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>" required>
                            <label for="rating-<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">&#9733;</label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="field">
                    <label for="rv-title">Title <span class="muted">(optional)</span></label>
                    <input id="rv-title" type="text" name="title" maxlength="190" placeholder="Summarise your experience">
                </div>
                <div class="field">
                    <label for="rv-body">Your review</label>
                    <textarea id="rv-body" name="body" rows="4" maxlength="2000" required placeholder="What did you like or dislike about this product?"></textarea>
                </div>
                <button class="btn btn--accent btn--lg" type="submit">Submit review</button>
            </form>
        </div>
        <?php elseif ($myReview): ?>
        <div class="alert alert--info" style="margin-top:1.4rem">
            <?= $myReview['status'] === 'approved'
                ? 'Thanks — your review is published.'
                : 'Thanks! Your review is awaiting approval.' ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($related): ?>
    <section class="section">
        <div class="section__head">
            <div><h2>You may also like</h2><p>More from <?= e($product['category_name']) ?>.</p></div>
        </div>
        <div class="grid">
            <?php foreach ($related as $p): echo render_product_card($p); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
