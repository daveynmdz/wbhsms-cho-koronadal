// Registration Form Validation and Terms Modal

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    const errorMsg = document.getElementById('errorMsg');
    const termsCheckbox = document.getElementById('termsCheckbox');
    const showTerms = document.getElementById('showTerms');
    const termsModal = document.getElementById('termsModal');
    const agreeBtn = document.getElementById('agreeBtn');
    const disagreeBtn = document.getElementById('disagreeBtn');

    // Show Terms Modal
    showTerms.addEventListener('click', function(e) {
        e.preventDefault();
        termsModal.style.display = 'block';
    });

    // Agree to Terms
    agreeBtn.addEventListener('click', function() {
        termsCheckbox.checked = true;
        termsModal.style.display = 'none';
    });

    // Disagree to Terms
    disagreeBtn.addEventListener('click', function() {
        termsCheckbox.checked = false;
        termsModal.style.display = 'none';
    });

    // Hide modal when clicking outside
    window.onclick = function(event) {
        if (event.target == termsModal) {
            termsModal.style.display = 'none';
        }
    }

    // Password Validation
    function validatePassword(password) {
        const minLength = 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        return password.length >= minLength && hasUpper && hasNumber && hasSpecial;
    }

    // Form Validation
    form.addEventListener('submit', function(e) {
        errorMsg.style.display = 'none';
        errorMsg.textContent = '';

        // Required fields
        const requiredFields = ['last_name', 'first_name', 'barangay', 'dob', 'sex', 'contact_num', 'email', 'username', 'password', 'confirm_password'];
        for (let field of requiredFields) {
            const el = form.elements[field];
            if (!el || !el.value) {
                errorMsg.textContent = 'Please fill in all required fields.';
                errorMsg.style.display = 'block';
                e.preventDefault();
                return;
            }
        }

        // Barangay selection
        const barangay = form.elements['barangay'].value;
        const validBarangays = [
            'Brgy. Assumption','Brgy. Avanceña','Brgy. Cacub','Brgy. Caloocan','Brgy. Carpenter Hill','Brgy. Concepcion','Brgy. Esperanza','Brgy. General Paulino Santos','Brgy. Mabini','Brgy. Magsaysay','Brgy. Mambucal','Brgy. Morales','Brgy. Namnama','Brgy. New Pangasinan','Brgy. Paraiso','Brgy. Rotonda','Brgy. San Isidro','Brgy. San Roque','Brgy. San Jose','Brgy. Sta. Cruz','Brgy. Sto. Niño','Brgy. Saravia','Brgy. Topland','Brgy. Zone 1','Brgy. Zone 2','Brgy. Zone 3','Brgy. Zone 4'
        ];
        if (!validBarangays.includes(barangay)) {
            errorMsg.textContent = 'Please select a valid barangay.';
            errorMsg.style.display = 'block';
            e.preventDefault();
            return;
        }

        // Sex selection
        if (!form.elements['sex'].value) {
            errorMsg.textContent = 'Please select your sex.';
            errorMsg.style.display = 'block';
            e.preventDefault();
            return;
        }

        // Password rules
        const password = form.elements['password'].value;
        if (!validatePassword(password)) {
            errorMsg.textContent = 'Password must be at least 8 characters, include an uppercase letter, a number, and a special symbol.';
            errorMsg.style.display = 'block';
            e.preventDefault();
            return;
        }

        // Confirm password
        const confirmPassword = form.elements['confirm_password'].value;
        if (password !== confirmPassword) {
            errorMsg.textContent = 'Passwords do not match.';
            errorMsg.style.display = 'block';
            e.preventDefault();
            return;
        }

        // Terms and Conditions
        if (!termsCheckbox.checked) {
            errorMsg.textContent = 'You must agree to the Terms and Conditions.';
            errorMsg.style.display = 'block';
            e.preventDefault();
            return;
        }
    });
});
