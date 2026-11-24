</main>

<footer class="site-footer">
    <div class="container footer__grid">
        <div class="footer__col">
            <a class="logo logo--light" href="<?= e(url('index.php')) ?>">
                <span class="logo__mark">S</span>
                <span class="logo__text"><?= e(SITE_NAME) ?></span>
            </a>
            <p class="footer__about"><?= e(SITE_TAGLINE) ?>. Discover curated fashion, footwear, beauty and accessories for him and her — delivered to your door.</p>
            <div class="footer__social">
                <a href="#" aria-label="Facebook">FB</a>
                <a href="#" aria-label="Instagram">IG</a>
                <a href="#" aria-label="Twitter">TW</a>
                <a href="#" aria-label="YouTube">YT</a>
            </div>
        </div>

        <div class="footer__col">
            <h4>Shop</h4>
            <a href="<?= e(url('shop.php?gender=women')) ?>">Women</a>
            <a href="<?= e(url('shop.php?gender=men')) ?>">Men</a>
            <a href="<?= e(url('shop.php?filter=featured')) ?>">Featured</a>
            <a href="<?= e(url('shop.php?filter=sale')) ?>">Sale</a>
        </div>

        <div class="footer__col">
            <h4>Account</h4>
            <a href="<?= e(url('account/dashboard.php')) ?>">My Account</a>
            <a href="<?= e(url('account/orders.php')) ?>">Order History</a>
            <a href="<?= e(url('cart.php')) ?>">Shopping Cart</a>
            <a href="<?= e(url('account/login.php')) ?>">Sign In</a>
        </div>

        <div class="footer__col">
            <h4>Information</h4>
            <a href="<?= e(url('page.php?slug=about')) ?>">About Us</a>
            <a href="<?= e(url('page.php?slug=contact')) ?>">Contact</a>
            <a href="<?= e(url('page.php?slug=shipping-returns')) ?>">Shipping &amp; Returns</a>
            <a href="<?= e(url('page.php?slug=privacy')) ?>">Privacy Policy</a>
            <a href="<?= e(url('page.php?slug=terms')) ?>">Terms &amp; Conditions</a>
        </div>

        <div class="footer__col">
            <h4>Need help?</h4>
            <p>Email: <?= e(SUPPORT_EMAIL) ?></p>
            <p>Phone: <?= e(SUPPORT_PHONE) ?></p>
            <p>Mon–Sat, 9am – 7pm IST</p>
        </div>
    </div>

    <div class="footer__bar">
        <div class="container footer__bar-inner">
            <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</span>
            <span class="footer__pay">
                We accept:
                <em>VISA</em><em>Mastercard</em><em>UPI</em><em>Razorpay</em><em>Stripe</em><em>COD</em>
            </span>
        </div>
    </div>
</footer>

<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>
