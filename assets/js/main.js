document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.flash').forEach((el) => {
    setTimeout(() => {
      // Only fixed (centered) flashes carry the translateX(-50%) transform.
      const isFixed = getComputedStyle(el).position === 'fixed';
      el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
      el.style.opacity = '0';
      el.style.transform = isFixed ? 'translateX(-50%) translateY(-12px)' : 'translateY(-8px)';
      setTimeout(() => el.remove(), 450);
    }, 4200);
  });

  const reveals = document.querySelectorAll('.reveal-up');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('is-visible'));
  }

  initJourneyLine();
  initGlowingEdgeCards();
  initHeaderMenus();
  initHeroDots();
  initDealsFilters();
  initQtyStepper();
  initReviewModal();
  initCheckoutSummary();
  initFounderCard();
  initHoverSlides();
  initProductGallery();
});

function initHoverSlides() {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-hover-slide]').forEach((el) => {
    const imgs = Array.from(el.querySelectorAll('img'));
    if (imgs.length < 2) {
      if (imgs[0]) imgs[0].classList.add('is-active');
      return;
    }

    let idx = 0;
    let timer = null;
    let dotsWrap = el.querySelector('.deal-media-dots');
    if (!dotsWrap) {
      dotsWrap = document.createElement('div');
      dotsWrap.className = 'deal-media-dots';
      dotsWrap.setAttribute('aria-hidden', 'true');
      imgs.forEach((_, i) => {
        const dot = document.createElement('span');
        if (i === 0) dot.classList.add('is-active');
        dotsWrap.appendChild(dot);
      });
      el.appendChild(dotsWrap);
    }
    const dots = Array.from(dotsWrap.querySelectorAll('span'));

    const show = (n) => {
      idx = ((n % imgs.length) + imgs.length) % imgs.length;
      imgs.forEach((img, i) => img.classList.toggle('is-active', i === idx));
      dots.forEach((dot, i) => dot.classList.toggle('is-active', i === idx));
    };

    const stop = () => {
      if (timer) clearInterval(timer);
      timer = null;
    };
    const start = () => {
      if (reduceMotion) return;
      stop();
      timer = setInterval(() => show(idx + 1), 3000);
    };

    show(0);

    // Auto-slide every 3s when card is on screen
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) start();
          else stop();
        });
      }, { threshold: 0.35 });
      io.observe(el);
    } else {
      start();
    }

    // Pause while hovering so user can click Buy
    el.addEventListener('mouseenter', stop);
    el.addEventListener('mouseleave', start);
  });
}

function initProductGallery() {
  const root = document.querySelector('[data-product-gallery]');
  if (!root) return;
  const frame = root.querySelector('.product-gallery-frame') || root;
  const mains = Array.from(root.querySelectorAll('.gallery-main'));
  if (mains.length < 2) return;

  const gotoBtns = Array.from(root.querySelectorAll('[data-gallery-goto]'));
  const prevBtn = root.querySelector('[data-gallery-prev]');
  const nextBtn = root.querySelector('[data-gallery-next]');
  const counter = root.querySelector('[data-gallery-current]');
  let idx = 0;
  let timer = null;

  const show = (n) => {
    idx = ((n % mains.length) + mains.length) % mains.length;
    mains.forEach((img, i) => img.classList.toggle('is-active', i === idx));
    gotoBtns.forEach((btn) => {
      const i = parseInt(btn.dataset.galleryGoto || '0', 10);
      btn.classList.toggle('is-active', i === idx);
    });
    if (counter) counter.textContent = String(idx + 1);
  };

  const stopAuto = () => {
    if (timer) clearInterval(timer);
    timer = null;
  };
  const startAuto = () => {
    stopAuto();
    timer = setInterval(() => show(idx + 1), 3200);
  };

  gotoBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      show(parseInt(btn.dataset.galleryGoto || '0', 10));
      startAuto();
    });
  });
  if (prevBtn) prevBtn.addEventListener('click', () => { show(idx - 1); startAuto(); });
  if (nextBtn) nextBtn.addEventListener('click', () => { show(idx + 1); startAuto(); });

  // Swipe / drag
  let startX = 0;
  let dragging = false;
  frame.addEventListener('pointerdown', (e) => {
    dragging = true;
    startX = e.clientX;
    frame.setPointerCapture?.(e.pointerId);
  });
  frame.addEventListener('pointerup', (e) => {
    if (!dragging) return;
    dragging = false;
    const dx = e.clientX - startX;
    if (Math.abs(dx) > 40) {
      show(dx < 0 ? idx + 1 : idx - 1);
      startAuto();
    }
  });
  frame.addEventListener('pointercancel', () => { dragging = false; });

  root.addEventListener('mouseenter', startAuto);
  root.addEventListener('mouseleave', stopAuto);
  document.addEventListener('keydown', (e) => {
    if (!root.closest('main')) return;
    if (e.key === 'ArrowLeft') { show(idx - 1); startAuto(); }
    if (e.key === 'ArrowRight') { show(idx + 1); startAuto(); }
  });

  show(0);
}

function initFounderCard() {
  const card = document.querySelector('[data-founder-card]');
  if (!card) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion || window.matchMedia('(hover: none)').matches) return;

  let raf = 0;
  const setFromEvent = (e) => {
    const rect = card.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    const fx = (x - 0.5) * 2;
    const fy = (y - 0.5) * 2;
    card.style.setProperty('--fx', fx.toFixed(3));
    card.style.setProperty('--fy', fy.toFixed(3));
  };

  card.addEventListener('pointermove', (e) => {
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => setFromEvent(e));
  });

  card.addEventListener('pointerleave', () => {
    if (raf) cancelAnimationFrame(raf);
    card.style.setProperty('--fx', '0');
    card.style.setProperty('--fy', '0');
  });
}

function initCheckoutSummary() {
  const form = document.querySelector('.checkout-grid[data-price]');
  if (!form) return;

  const price = parseFloat(form.dataset.price) || 0;
  const qtyInput = form.querySelector('#quantity');
  const format = (amount) => '\u20b9' + Math.round(amount).toLocaleString('en-IN');

  const update = () => {
    const qty = parseInt(qtyInput.value, 10) || 1;
    const total = price * qty;
    const setText = (selector, text) => {
      form.querySelectorAll(selector).forEach((el) => { el.textContent = text; });
    };
    setText('[data-qty-label]', String(qty));
    setText('[data-line-total]', format(total));
    setText('[data-subtotal]', format(total));
    setText('[data-total]', format(total));
    setText('[data-total-btn]', format(total));
  };

  form.querySelectorAll('.qty-btn[data-qty]').forEach((btn) => {
    btn.addEventListener('click', update);
  });
  update();
}

function initReviewModal() {
  const overlay = document.querySelector('[data-review-modal]');
  if (!overlay) return;

  const openBtn = document.querySelector('[data-open-review]');
  const closeBtns = overlay.querySelectorAll('[data-close-review]');
  const form = overlay.querySelector('#write-review');
  const fileInput = overlay.querySelector('#review-photos');
  const photoRow = overlay.querySelector('[data-photo-row]');
  const uploadBox = overlay.querySelector('.review-upload-box');
  const MAX_PHOTOS = 3;
  let files = [];

  const open = () => {
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
  };
  const close = () => {
    overlay.hidden = true;
    document.body.style.overflow = '';
  };

  if (openBtn) openBtn.addEventListener('click', open);
  closeBtns.forEach((btn) => btn.addEventListener('click', close));
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) close();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hidden) close();
  });

  if (!fileInput || !photoRow || !uploadBox || !form) return;

  const canUseDataTransfer = typeof DataTransfer !== 'undefined';

  const syncInput = () => {
    if (canUseDataTransfer) {
      const dt = new DataTransfer();
      files.forEach((f) => dt.items.add(f));
      fileInput.files = dt.files;
    }
    uploadBox.style.display = files.length >= MAX_PHOTOS ? 'none' : 'flex';
  };

  const renderPreviews = () => {
    photoRow.querySelectorAll('.review-photo-preview').forEach((el) => el.remove());
    files.forEach((file, idx) => {
      const wrap = document.createElement('div');
      wrap.className = 'review-photo-preview';
      const img = document.createElement('img');
      img.alt = '';
      img.src = URL.createObjectURL(file);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.textContent = '\u00d7';
      remove.setAttribute('aria-label', 'Remove photo');
      remove.addEventListener('click', () => {
        files.splice(idx, 1);
        syncInput();
        renderPreviews();
      });
      wrap.appendChild(img);
      wrap.appendChild(remove);
      photoRow.insertBefore(wrap, uploadBox);
    });
  };

  fileInput.addEventListener('change', () => {
    const incoming = Array.from(fileInput.files || []);
    incoming.forEach((f) => {
      if (files.length < MAX_PHOTOS && (f.type.startsWith('image/') || /\.(jpe?g|png|webp|gif)$/i.test(f.name))) {
        files.push(f);
      }
    });
    syncInput();
    renderPreviews();
    // Allow selecting the same file again after remove
    if (!canUseDataTransfer) {
      fileInput.value = '';
    }
  });

  form.addEventListener('submit', (e) => {
    if (!files.length || canUseDataTransfer) {
      // Native submit works when DataTransfer synced the input
      return;
    }
    // Fallback: rebuild FormData so photos always post
    e.preventDefault();
    const fd = new FormData(form);
    fd.delete('photos[]');
    files.forEach((f) => fd.append('photos[]', f, f.name));
    const actionBtn = form.querySelector('button[name="action"][value="add_review"]');
    if (actionBtn) fd.set('action', 'add_review');
    fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      redirect: 'follow',
    }).then((res) => {
      window.location.href = res.url || (window.location.pathname + window.location.search + '#reviews');
    }).catch(() => {
      form.submit();
    });
  });
}

function initQtyStepper() {
  const input = document.getElementById('quantity');
  if (!input) return;
  document.querySelectorAll('.qty-btn[data-qty]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const delta = parseInt(btn.dataset.qty, 10);
      const next = Math.min(10, Math.max(1, (parseInt(input.value, 10) || 1) + delta));
      input.value = String(next);
    });
  });
}

function initHeroDots() {
  const dots = document.querySelectorAll('.hero-dot');
  if (!dots.length) return;
  let current = 0;
  setInterval(() => {
    dots[current].classList.remove('is-active');
    current = (current + 1) % dots.length;
    dots[current].classList.add('is-active');
  }, 3500);
}

function initDealsFilters() {
  const pills = document.querySelectorAll('.filter-pill');
  const cards = document.querySelectorAll('.deal-card');
  if (!pills.length || !cards.length) return;

  pills.forEach((pill) => {
    pill.addEventListener('click', () => {
      pills.forEach((p) => p.classList.remove('is-active'));
      pill.classList.add('is-active');
      const filter = pill.dataset.filter;
      cards.forEach((card) => {
        const show = filter === 'all' || card.dataset.category === filter;
        card.classList.toggle('is-hidden', !show);
      });
    });
  });
}

function initHeaderMenus() {
  const dropdown = document.querySelector('.nav-account-dropdown');
  const trigger = document.querySelector('.dropdown-trigger');
  const menuToggle = document.querySelector('.menu-toggle');
  const header = document.querySelector('.main-header');
  const navCenterLinks = document.querySelectorAll('.nav-center a');

  if (trigger && dropdown) {
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isActive = dropdown.classList.toggle('active');
      trigger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('active');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  if (menuToggle && header) {
    const closeMobileNav = () => {
      header.classList.remove('mobile-nav-open');
      document.body.classList.remove('nav-locked');
      menuToggle.setAttribute('aria-expanded', 'false');
    };

    menuToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = header.classList.toggle('mobile-nav-open');
      document.body.classList.toggle('nav-locked', isOpen);
      menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    navCenterLinks.forEach(link => {
      link.addEventListener('click', closeMobileNav);
    });

    document.addEventListener('click', (e) => {
      if (!header.contains(e.target)) closeMobileNav();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeMobileNav();
        if (dropdown && trigger) {
          dropdown.classList.remove('active');
          trigger.setAttribute('aria-expanded', 'false');
        }
      }
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 980 && header.classList.contains('mobile-nav-open')) {
        closeMobileNav();
      }
    });
  }
}

function initJourneyLine() {
  const path = document.querySelector('.journey-draw');
  const journey = document.querySelector('.home-journey');
  const stops = document.querySelectorAll('.journey-stops span');
  if (!path || !journey) return;

  const length = path.getTotalLength();
  path.style.strokeDasharray = String(length);
  path.style.strokeDashoffset = String(length);

  const sections = ['home', 'craft', 'collection', 'contact']
    .map((id) => document.getElementById(id))
    .filter(Boolean);

  const update = () => {
    const rect = journey.getBoundingClientRect();
    const total = journey.offsetHeight - window.innerHeight;
    const scrolled = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
    const progress = total > 0 ? scrolled / total : 0;
    path.style.strokeDashoffset = String(length * (1 - progress));

    let active = 0;
    sections.forEach((section, i) => {
      const top = section.getBoundingClientRect().top;
      if (top < window.innerHeight * 0.45) active = i;
    });
    stops.forEach((stop, i) => {
      stop.classList.toggle('is-active', i <= active);
    });
  };

  // Throttle scroll work with requestAnimationFrame so scrolling stays smooth
  let ticking = false;
  const requestUpdate = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      update();
      ticking = false;
    });
  };

  update();
  window.addEventListener('scroll', requestUpdate, { passive: true });
  window.addEventListener('resize', requestUpdate);
}

function initGlowingEdgeCards() {
  const cards = document.querySelectorAll('[data-glowing-edge]');
  if (!cards.length) return;

  const round = (value, precision = 3) => parseFloat(value.toFixed(precision));
  const clamp = (value, min = 0, max = 100) => Math.min(Math.max(value, min), max);

  cards.forEach((card) => {
    const playIntro = () => {
      card.classList.add('animating');
      const angleStart = 110;
      const angleEnd = 465;
      const startTime = performance.now();
      card.style.setProperty('--pointer-deg', `${angleStart}deg`);

      const animate = (now) => {
        if (!card.classList.contains('animating')) return;
        const elapsed = now - startTime;

        if (elapsed > 500 && elapsed < 1000) {
          const t = (elapsed - 500) / 500;
          const ease = 1 - Math.pow(1 - t, 3);
          card.style.setProperty('--pointer-d', String(ease * 100));
        }
        if (elapsed > 500 && elapsed < 2000) {
          const t = (elapsed - 500) / 1500;
          const ease = t * t * t;
          const d = (angleEnd - angleStart) * (ease * 0.5) + angleStart;
          card.style.setProperty('--pointer-deg', `${d}deg`);
        }
        if (elapsed >= 2000 && elapsed < 4250) {
          const t = (elapsed - 2000) / 2250;
          const ease = 1 - Math.pow(1 - t, 3);
          const d = (angleEnd - angleStart) * (0.5 + ease * 0.5) + angleStart;
          card.style.setProperty('--pointer-deg', `${d}deg`);
        }
        if (elapsed > 3000 && elapsed < 4500) {
          const t = (elapsed - 3000) / 1500;
          const ease = t * t * t;
          card.style.setProperty('--pointer-d', String((1 - ease) * 100));
        }

        if (elapsed < 4500) {
          requestAnimationFrame(animate);
        } else {
          card.classList.remove('animating');
          card.style.setProperty('--pointer-d', '0');
        }
      };

      requestAnimationFrame(animate);
    };

    card.addEventListener('pointermove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const px = clamp((100 / rect.width) * x);
      const py = clamp((100 / rect.height) * y);
      const cx = rect.width / 2;
      const cy = rect.height / 2;
      const dx = x - cx;
      const dy = y - cy;

      let angle = 0;
      if (dx !== 0 || dy !== 0) {
        angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
        if (angle < 0) angle += 360;
      }

      let kx = Infinity;
      let ky = Infinity;
      if (dx !== 0) kx = cx / Math.abs(dx);
      if (dy !== 0) ky = cy / Math.abs(dy);
      const edge = clamp(1 / Math.min(kx, ky), 0, 1);

      card.style.setProperty('--pointer-x', `${round(px)}%`);
      card.style.setProperty('--pointer-y', `${round(py)}%`);
      card.style.setProperty('--pointer-deg', `${round(angle)}deg`);
      card.style.setProperty('--pointer-d', `${round(edge * 100)}`);

      if (card.classList.contains('animating')) {
        card.classList.remove('animating');
      }
    });

    card.addEventListener('pointerleave', () => {
      if (!card.classList.contains('animating')) {
        card.style.setProperty('--pointer-d', '0');
      }
    });

    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          setTimeout(playIntro, 400);
          io.disconnect();
        }
      });
    }, { threshold: 0.35 });
    io.observe(card);
  });
}
