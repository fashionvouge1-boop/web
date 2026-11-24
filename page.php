<?php
/**
 * Static information pages (About, Contact, Shipping & Returns, Privacy, Terms).
 * Reached via page.php?slug=<key> and linked from the footer.
 */
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));

$PAGES = [
    'about' => [
        'title'   => 'About Us',
        'lead'    => SITE_NAME . ' is a modern fashion destination bringing together curated clothing, footwear, beauty and accessories for him and her.',
        'sections' => [
            ['Who we are', '<p>Founded with a simple idea — great style should be effortless and affordable — ' . e(SITE_NAME) . ' curates every piece in-house. From everyday essentials to statement looks, our collections are chosen for quality fabric, honest pricing and timeless design.</p>'],
            ['Why shop with us', '<ul style="padding-left:1.2rem"><li>Free shipping on orders over ' . e(money(FREE_SHIPPING_OVER)) . '</li><li>Easy 30-day returns</li><li>Secure online payments (Razorpay, Stripe, UPI &amp; cards)</li><li>Dedicated customer support, Mon–Sat</li></ul>'],
            ['Our promise', '<p>We stand behind everything we sell. If a product doesn\'t meet your expectations, our team will make it right.</p>'],
        ],
    ],
    'contact' => [
        'title'   => 'Contact Us',
        'lead'    => 'Questions about an order, a product or a return? We are here to help.',
        'sections' => [
            ['Reach us', '<p><strong>Email:</strong> ' . e(SUPPORT_EMAIL) . '<br><strong>Phone:</strong> ' . e(SUPPORT_PHONE) . '<br><strong>Hours:</strong> Mon–Sat, 9am – 7pm IST</p>'],
        ],
    ],
    'shipping-returns' => [
        'title'   => 'Shipping &amp; Returns',
        'lead'    => 'Everything you need to know about getting your order and sending it back.',
        'sections' => [
            ['Shipping', '<ul style="padding-left:1.2rem"><li>Flat shipping of ' . e(money(SHIPPING_FLAT)) . ' per order.</li><li>Free shipping on orders over ' . e(money(FREE_SHIPPING_OVER)) . '.</li><li>Orders are dispatched within 1–2 business days and typically arrive in 3–6 business days.</li></ul>'],
            ['Returns & refunds', '<ul style="padding-left:1.2rem"><li>Not happy? Return most items within 30 days of delivery.</li><li>Products must be unworn, with tags attached, in original packaging.</li><li>Refunds are issued to the original payment method within 5–7 business days of us receiving the return.</li></ul>'],
            ['Cash on Delivery', '<p>Cash on Delivery is currently unavailable. All orders are prepaid via our secure online payment partners.</p>'],
        ],
    ],
    'privacy' => [
        'title'   => 'Privacy Policy',
        'lead'    => 'Your privacy matters. This policy explains what we collect and how we use it.',
        'sections' => [
            ['Information we collect', '<p>We collect the details you provide at checkout and when you create an account — such as your name, email, phone number and shipping address — so we can process and deliver your orders.</p>'],
            ['Payments', '<p>Card and UPI payments are processed by our payment partners. We never store your full card details on our servers.</p>'],
            ['Cookies', '<p>We use cookies to keep your session, remember your cart and improve your shopping experience.</p>'],
            ['Your rights', '<p>You may request access to, correction of, or deletion of your personal data at any time by emailing ' . e(SUPPORT_EMAIL) . '.</p>'],
        ],
    ],
    'terms' => [
        'title'   => 'Terms &amp; Conditions',
        'lead'    => 'By using this website you agree to the following terms.',
        'sections' => [
            ['Use of the site', '<p>You agree to use ' . e(SITE_NAME) . ' only for lawful purposes and to provide accurate information when placing orders.</p>'],
            ['Pricing & availability', '<p>All prices are shown in ' . e(CURRENCY) . '. We reserve the right to update prices and to cancel an order if a product becomes unavailable, with a full refund.</p>'],
            ['Orders', '<p>An order is confirmed once payment is successfully received. We may contact you to verify details before dispatch.</p>'],
            ['Liability', '<p>Our liability is limited to the value of the products purchased. This does not affect your statutory rights.</p>'],
        ],
    ],
];

if (!isset($PAGES[$slug])) {
    http_response_code(404);
    $pageTitle  = 'Page not found';
    $noindex    = true;
    require __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty"><h2>Page not found</h2>'
       . '<p>That page does not exist.</p>'
       . '<a class="btn btn--accent" href="' . e(url('index.php')) . '">Back to home</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$page = $PAGES[$slug];

/* Contact form submission. */
$contactErrors = [];
$contactOld    = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
if ($slug === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $contactErrors[] = 'Your session expired. Please try again.';
    }
    foreach ($contactOld as $f => $_) {
        $contactOld[$f] = trim((string)($_POST[$f] ?? ''));
    }
    if ($contactOld['name'] === '')  { $contactErrors[] = 'Please enter your name.'; }
    if (!filter_var($contactOld['email'], FILTER_VALIDATE_EMAIL)) { $contactErrors[] = 'Please enter a valid email address.'; }
    if (strlen($contactOld['message']) < 10) { $contactErrors[] = 'Please enter a message of at least 10 characters.'; }

    if (!$contactErrors) {
        $subject = 'Contact form: ' . ($contactOld['subject'] !== '' ? $contactOld['subject'] : 'New enquiry');
        $html = email_layout('New contact message', '<p><strong>From:</strong> ' . e($contactOld['name'])
              . ' &lt;' . e($contactOld['email']) . '&gt;</p><p>' . nl2br(e($contactOld['message'])) . '</p>');
        send_mail(SUPPORT_EMAIL, $subject, $html);
        set_flash('success', 'Thanks for reaching out! We will get back to you within one business day.');
        redirect('page.php?slug=contact');
    }
}

$pageTitle       = $page['title'];
$pageDescription = mb_strimwidth(strip_tags($page['lead']), 0, 160, '…');
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a> / <span><?= e($page['title']) ?></span>
    </div>

    <div class="panel" style="max-width:820px;margin:1.4rem auto 3rem">
        <h1><?= e($page['title']) ?></h1>
        <p class="muted" style="font-size:1.02rem"><?= e($page['lead']) ?></p>

        <?php foreach ($page['sections'] as [$heading, $body]): ?>
            <section style="margin-top:1.6rem">
                <h3 style="margin-bottom:.5rem"><?= $heading ?></h3>
                <div class="rich-text"><?= $body ?></div>
            </section>
        <?php endforeach; ?>

        <?php if ($slug === 'contact'): ?>
            <section style="margin-top:2rem">
                <h3>Send us a message</h3>
                <?php foreach ($contactErrors as $err): ?>
                    <div class="alert alert--error"><?= e($err) ?></div>
                <?php endforeach; ?>
                <form method="post" action="<?= e(url('page.php?slug=contact')) ?>">
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="field">
                            <label for="c_name">Your name *</label>
                            <input id="c_name" name="name" required value="<?= e($contactOld['name']) ?>">
                        </div>
                        <div class="field">
                            <label for="c_email">Email *</label>
                            <input id="c_email" type="email" name="email" required value="<?= e($contactOld['email']) ?>">
                        </div>
                        <div class="field field--full">
                            <label for="c_subject">Subject</label>
                            <input id="c_subject" name="subject" value="<?= e($contactOld['subject']) ?>">
                        </div>
                        <div class="field field--full">
                            <label for="c_message">Message *</label>
                            <textarea id="c_message" name="message" rows="5" required><?= e($contactOld['message']) ?></textarea>
                        </div>
                    </div>
                    <button class="btn btn--accent btn--lg" type="submit">Send message</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
