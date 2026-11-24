<?php
require_once __DIR__ . '/includes/functions.php';

$heroImg = function (string $prompt): string {
    return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=landscape_16_9&prompt=' . urlencode($prompt);
};
$tileImg = function (string $prompt): string {
    return 'https://coresg-normal.trae.ai/api/ide/v1/text_to_image?image_size=portrait_4_3&prompt=' . urlencode($prompt);
};

$featured = db()->query('SELECT p.*, c.name AS category_name FROM products p
                         JOIN categories c ON c.id = p.category_id
                         WHERE p.is_active = 1 AND p.featured = 1
                         ORDER BY p.created_at DESC LIMIT 8')->fetchAll();

$newArrivals = db()->query('SELECT p.*, c.name AS category_name FROM products p
                            JOIN categories c ON c.id = p.category_id
                            WHERE p.is_active = 1
                            ORDER BY p.created_at DESC LIMIT 8')->fetchAll();

$onSale = db()->query('SELECT p.*, c.name AS category_name FROM products p
                       JOIN categories c ON c.id = p.category_id
                       WHERE p.is_active = 1 AND p.sale_price IS NOT NULL
                       ORDER BY (p.price - p.sale_price) DESC LIMIT 4')->fetchAll();

$tiles = db()->query('SELECT * FROM categories ORDER BY gender DESC LIMIT 6')->fetchAll();

$pageTitle = 'Home';
$bodyClass = 'page-home';
require __DIR__ . '/includes/header.php';
?>

<!-- ============ HERO CAROUSEL ============ -->
<section class="container hero">
    <div class="hero__slides">
        <div class="hero__slide is-active" style="background-image:url('<?= e($heroImg('elegant woman wearing a flowing summer dress in a bright modern studio, editorial fashion banner')) ?>')">
            <div class="hero__content">
                <div class="hero__eyebrow">New Season 2026</div>
                <h1>Women's Collection</h1>
                <p>Fresh silhouettes, effortless style. Discover dresses, tops and accessories curated for you.</p>
                <div class="hero__cta">
                    <a class="btn btn--accent btn--lg" href="<?= e(url('shop.php?gender=women')) ?>">Shop Women</a>
                    <a class="btn btn--light btn--lg" href="<?= e(url('shop.php')) ?>">Browse All</a>
                </div>
            </div>
        </div>
        <div class="hero__slide" style="background-image:url('<?= e($heroImg('confident man in smart casual menswear, editorial fashion studio banner')) ?>')">
            <div class="hero__content">
                <div class="hero__eyebrow">Everyday Essentials</div>
                <h1>Men's Collection</h1>
                <p>Sharp shirts, denim, shoes and watches — built for the modern man.</p>
                <div class="hero__cta">
                    <a class="btn btn--accent btn--lg" href="<?= e(url('shop.php?gender=men')) ?>">Shop Men</a>
                    <a class="btn btn--light btn--lg" href="<?= e(url('shop.php?filter=featured')) ?>">Featured</a>
                </div>
            </div>
        </div>
        <div class="hero__slide" style="background-image:url('<?= e($heroImg('flat lay of fashion clothing shoes and accessories on clean background, sale shopping banner')) ?>')">
            <div class="hero__content">
                <div class="hero__eyebrow">Limited Time</div>
                <h1>Up to 40% Off</h1>
                <p>Grab your favourites at unbeatable prices before they are gone.</p>
                <div class="hero__cta">
                    <a class="btn btn--accent btn--lg" href="<?= e(url('shop.php?filter=sale')) ?>">Shop the Sale</a>
                </div>
            </div>
        </div>

        <button class="hero__arrow hero__arrow--prev" aria-label="Previous">&#8249;</button>
        <button class="hero__arrow hero__arrow--next" aria-label="Next">&#8250;</button>
        <div class="hero__dots">
            <button class="hero__dot is-active" aria-label="Slide 1"></button>
            <button class="hero__dot" aria-label="Slide 2"></button>
            <button class="hero__dot" aria-label="Slide 3"></button>
        </div>
    </div>
</section>

<!-- ============ CATEGORY TILES ============ -->
<section class="container section" style="padding-top:.6rem">
    <div class="section__head">
        <div>
            <h2>Shop by Category</h2>
            <p>Find exactly what you are looking for.</p>
        </div>
        <a class="section__link" href="<?= e(url('shop.php')) ?>">View all &rarr;</a>
    </div>
    <div class="cat-grid">
        <?php foreach ($tiles as $t): ?>
            <a class="cat-tile" href="<?= e(url('shop.php?category=' . $t['slug'])) ?>">
                <img loading="lazy" src="<?= e($tileImg($t['name'] . ' fashion collection product display, studio photography')) ?>" alt="<?= e($t['name']) ?>">
                <span class="cat-tile__label">
                    <span><?= e($t['name']) ?></span>
                    <small><?= e(ucfirst($t['gender'])) ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ FEATURED ============ -->
<section class="container section">
    <div class="section__head">
        <div>
            <h2>Featured Products</h2>
            <p>Hand-picked favourites our customers love.</p>
        </div>
        <a class="section__link" href="<?= e(url('shop.php?filter=featured')) ?>">See more &rarr;</a>
    </div>
    <div class="grid">
        <?php foreach ($featured as $p): echo render_product_card($p); endforeach; ?>
    </div>
</section>

<!-- ============ PROMO BANNER ============ -->
<section class="container section">
    <div class="promo-grid">
        <a class="promo-banner promo-banner--women" href="<?= e(url('shop.php?gender=women')) ?>">
            <div class="promo-banner__inner">
                <span class="promo-banner__tag">New Arrivals</span>
                <h3 class="promo-banner__title">Women's Edit</h3>
                <p class="promo-banner__text">Dresses, tops, handbags &amp; more — curated for her.</p>
                <span class="promo-banner__cta">Shop now &rarr;</span>
            </div>
        </a>
        <a class="promo-banner promo-banner--men" href="<?= e(url('shop.php?gender=men')) ?>">
            <div class="promo-banner__inner">
                <span class="promo-banner__tag">New Arrivals</span>
                <h3 class="promo-banner__title">Men's Edit</h3>
                <p class="promo-banner__text">Shirts, denim, shoes &amp; watches — built for him.</p>
                <span class="promo-banner__cta">Shop now &rarr;</span>
            </div>
        </a>
    </div>
</section>

<!-- ============ NEW ARRIVALS ============ -->
<section class="container section">
    <div class="section__head">
        <div>
            <h2>New Arrivals</h2>
            <p>The latest additions to our store.</p>
        </div>
        <a class="section__link" href="<?= e(url('shop.php?sort=newest')) ?>">Shop new &rarr;</a>
    </div>
    <div class="grid">
        <?php foreach ($newArrivals as $p): echo render_product_card($p); endforeach; ?>
    </div>
</section>

<!-- ============ ON SALE ============ -->
<?php if ($onSale): ?>
<section class="container section">
    <div class="section__head">
        <div>
            <h2>On Sale</h2>
            <p>Rated deals at reduced prices.</p>
        </div>
        <a class="section__link" href="<?= e(url('shop.php?filter=sale')) ?>">All deals &rarr;</a>
    </div>
    <div class="grid">
        <?php foreach ($onSale as $p): echo render_product_card($p); endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ TRUST STRIP ============ -->
<section class="container">
    <div class="trust-strip">
        <div><span class="ti">&#128666;</span><div><strong>Free Delivery</strong><small>On orders over <?= money(FREE_SHIPPING_OVER) ?></small></div></div>
        <div><span class="ti">&#8635;</span><div><strong>Easy Returns</strong><small>30-day return policy</small></div></div>
        <div><span class="ti">&#128274;</span><div><strong>Secure Payments</strong><small>UPI, Cards &amp; COD</small></div></div>
        <div><span class="ti">&#128222;</span><div><strong>Customer Support</strong><small>We're here to help</small></div></div>
    </div>
</section>

<!-- ============ NEWSLETTER ============ -->
<section class="container">
    <div class="newsletter">
        <h2>Join the StyleHub list</h2>
        <p>Be the first to know about new drops and exclusive offers.</p>
        <form onsubmit="event.preventDefault();this.reset();this.querySelector('input').placeholder='Thanks for subscribing!';">
            <input type="email" placeholder="Enter your email address" required>
            <button class="btn btn--accent" type="submit">Subscribe</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
