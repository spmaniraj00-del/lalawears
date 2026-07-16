<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pages = [
    'about' => [
        'title' => 'About Us',
        'body' => [
            'LALA WEARS is a premium clothing brand born in Bettiah, Bihar. We craft T-shirts and everyday wear that celebrate heritage, pride, and comfort — designed for people who want quality they can feel in every stitch.',
            'Every piece is made with premium 240 GSM cotton fabric and finished with durable embroidery inspired by Bihar\'s identity. From our workshop to your doorstep, we pack and ship each order with care.',
            'Founded by ' . FOUNDER_NAME . ', LALA WEARS stands for one simple promise: style that defines you, at a price that respects you.',
        ],
    ],
    'faq' => [
        'title' => 'Frequently Asked Questions',
        'body' => [
            'Q: How do I place an order? — Open any product, choose your quantity, and click Buy Now. Sign in, fill your delivery details, and your order goes straight to our team.',
            'Q: What sizes are available? — All our clothing is available in sizes S, M, L, XL, and XXL. Size is selected at checkout.',
            'Q: How long does delivery take? — Orders are usually delivered within 5–7 working days anywhere in India. You can track your order from My Account → Orders.',
            'Q: Can I cancel my order? — Yes, contact us on WhatsApp at ' . CONTACT_PHONE . ' before the order is shipped and we will cancel it for you.',
            'Q: How do I contact support? — Message us on WhatsApp, email ' . CONTACT_EMAIL . ', or use the Contact section on the home page. We reply quickly.',
        ],
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'body' => [
            'By using this website and placing an order, you agree to the following terms.',
            'Orders: All orders are confirmed by our team after they are placed. Prices shown are in Indian Rupees and include all product charges. Delivery is free.',
            'Payment: Orders are currently placed as cash on delivery / direct settlement with our team unless stated otherwise.',
            'Returns: If your product arrives damaged or incorrect, contact us within 48 hours of delivery with photos and we will replace it.',
            'Accounts: You are responsible for keeping your account credentials safe. We may suspend accounts that misuse the platform.',
            'These terms may be updated from time to time. Continued use of the site means you accept the latest version.',
        ],
    ],
    'privacy' => [
        'title' => 'Privacy Policy',
        'body' => [
            'We respect your privacy. This policy explains what data we collect and how we use it.',
            'What we collect: your name, phone number, email, and delivery address — only what is needed to process and deliver your order.',
            'How we use it: to fulfil orders, send order updates and notifications, and provide customer support. We never sell your personal data to anyone.',
            'Sign-in: if you use Google Sign-In, we only receive your basic profile (name, email, photo) to create your account.',
            'Security: your data is stored securely and access is limited to our team. You may request deletion of your account and data at any time by contacting ' . CONTACT_EMAIL . '.',
        ],
    ],
];

$key = strtolower(trim((string) ($_GET['p'] ?? 'about')));
if (!isset($pages[$key])) {
    $key = 'about';
}
$page = $pages[$key];

$pageTitle = $page['title'] . ' | ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<main class="static-page">
  <h1 class="deals-title reveal-up"><?= e($page['title']) ?></h1>
  <div class="static-page-body reveal-up">
    <?php foreach ($page['body'] as $para): ?>
      <p><?= e($para) ?></p>
    <?php endforeach; ?>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
