// script.js — Yahoo Sign-in Client JS

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
                if (emailErr) emailErr.textContent = 'Please enter your email or phone number.';
                emailInput.classList.add('err');
            }
        });
        emailInput.addEventListener('input', function () {
            if (emailErr) emailErr.textContent = '';
            emailInput.classList.remove('err');
        });
    }

    /* ══ PASSWORD SHOW/HIDE ══ */
    const pwInput = document.getElementById('pwInput');
    const eyeBtn  = document.getElementById('eyeBtn');
    const eyeOff  = document.getElementById('eyeOff');
    const eyeOn   = document.getElementById('eyeOn');
    const pwErr   = document.getElementById('pwErr');
    const pwForm  = document.getElementById('pwForm');

    function setPwVisible(show) {
        if (!pwInput) return;
        pwInput.type = show ? 'text' : 'password';
        if (eyeOff) eyeOff.style.display = show ? 'none'   : 'inline';
        if (eyeOn)  eyeOn.style.display  = show ? 'inline' : 'none';
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
                if (pwErr) pwErr.textContent = 'Please enter your password.';
                pwInput.classList.add('err');
            }
        });
        pwInput.addEventListener('input', function () {
            if (pwErr) pwErr.textContent = '';
            pwInput.classList.remove('err');
        });
    }
});
