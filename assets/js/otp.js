(function () {
  function initOtpInputs() {
    const group = document.querySelector('[data-otp-group]');
    if (!group) return;

    const inputs = Array.from(group.querySelectorAll('.otp-digit'));
    const hidden = document.querySelector('[data-otp-value]');
    const dots = Array.from(document.querySelectorAll('[data-otp-dot]'));
    const form = group.closest('form');

    const sync = () => {
      const code = inputs.map((i) => i.value.replace(/\D/g, '').slice(0, 1)).join('');
      if (hidden) hidden.value = code;
      inputs.forEach((input, i) => {
        input.classList.toggle('filled', input.value !== '');
        if (dots[i]) dots[i].classList.toggle('on', input.value !== '');
      });
    };

    inputs.forEach((input, index) => {
      input.addEventListener('input', (e) => {
        const v = e.target.value.replace(/\D/g, '');
        e.target.value = v.slice(0, 1);
        if (v && index < inputs.length - 1) inputs[index + 1].focus();
        sync();
        if (inputs.every((i) => i.value) && form) {
          // optional auto-submit when full — keep manual verify for control
        }
      });

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && index > 0) {
          inputs[index - 1].focus();
          inputs[index - 1].value = '';
          sync();
        }
        if (e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-' || e.key === '.') {
          e.preventDefault();
        }
      });

      input.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text') || '';
        const digits = text.replace(/\D/g, '').slice(0, inputs.length).split('');
        digits.forEach((d, i) => {
          if (inputs[i]) inputs[i].value = d;
        });
        const focusAt = Math.min(digits.length, inputs.length - 1);
        inputs[focusAt].focus();
        sync();
      });
    });

    sync();
    if (inputs[0]) inputs[0].focus();
  }

  function initResendCountdown() {
    const btn = document.querySelector('[data-resend-btn]');
    const label = document.querySelector('[data-resend-label]');
    if (!btn || !label) return;

    let remaining = parseInt(btn.getAttribute('data-seconds') || '30', 10);
    const tick = () => {
      if (remaining <= 0) {
        btn.disabled = false;
        label.textContent = 'Resend code';
        return;
      }
      btn.disabled = true;
      const m = Math.floor(remaining / 60);
      const s = String(remaining % 60).padStart(2, '0');
      label.textContent = 'Resend in ' + m + ':' + s;
      remaining -= 1;
      setTimeout(tick, 1000);
    };
    tick();
  }

  document.addEventListener('DOMContentLoaded', () => {
    initOtpInputs();
    initResendCountdown();
  });
})();
