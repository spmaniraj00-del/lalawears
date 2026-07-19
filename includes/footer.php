<?php declare(strict_types=1); ?>
  <div class="footer-wave" aria-hidden="true">
    <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
      <path d="M0,70 C260,120 520,20 760,45 C1000,70 1240,95 1440,40 L1440,120 L0,120 Z" fill="#f3faf1"></path>
      <path d="M0,70 C260,120 520,20 760,45 C1000,70 1240,95 1440,40" fill="none" stroke="#27a55b" stroke-width="2.5"></path>
    </svg>
  </div>
  <footer class="site-footer">
    <div class="footer-inner">
      <a class="footer-brand" href="<?= e(url('index.php')) ?>#home">
        <img src="<?= e(site_logo_url()) ?>" alt="LALA WEARS logo">
        <span class="footer-brand-name">lala<span>wears</span></span>
      </a>

      <div class="footer-center">
        <nav class="footer-links" aria-label="Footer">
          <a href="<?= e(url('page.php?p=about')) ?>">About</a>
          <a href="<?= e(url('contact.php')) ?>">Contact</a>
          <a href="<?= e(url('page.php?p=faq')) ?>">FAQ</a>
          <a href="<?= e(url('page.php?p=terms')) ?>">Terms of Service</a>
          <a href="<?= e(url('page.php?p=privacy')) ?>">Privacy Policy</a>
        </nav>
        <p class="footer-copy">&copy; <?= date('Y') ?> LALA WEARS. All rights reserved.</p>
      </div>

      <div class="footer-social">
        <a href="<?= e(site_instagram()) ?>" target="_blank" rel="noopener" aria-label="Instagram">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
        </a>
        <a href="<?= e(site_whatsapp()) ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
        </a>
        <?php if (site_facebook() !== ''): ?>
          <a href="<?= e(site_facebook()) ?>" target="_blank" rel="noopener" aria-label="Facebook">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
          </a>
        <?php endif; ?>
        <?php if (site_youtube() !== ''): ?>
          <a href="<?= e(site_youtube()) ?>" target="_blank" rel="noopener" aria-label="YouTube">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon></svg>
          </a>
        <?php endif; ?>
        <a href="mailto:<?= e(site_email()) ?>" aria-label="Email">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        </a>
        <a href="tel:<?= e(str_replace(' ', '', site_phone())) ?>" aria-label="Phone">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
        </a>
      </div>
    </div>
  </footer>
  <script src="<?= e(asset('js/main.js')) ?>?v=2.6"></script>
</body>
</html>
