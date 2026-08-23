// Contact form (C1, SECURITY.md SEC-23).
// The form no longer sends email from the browser (the EmailJS SDK + public key
// are gone). It POSTs to /api/contact.php, which rate-limits per IP, checks the
// honeypot, verifies reCAPTCHA server-side, stores the enquiry, and relays it.
// User-visible behavior (validation alerts, success/failure alerts, form reset)
// is unchanged from the original.

function validateForm() {
    let firstName = document.getElementById("first_name").value;
    let lastName = document.getElementById("last_name").value;
    let email = document.getElementById("email").value;
    let mobile = document.getElementById("mobile").value;
    let message = document.getElementById("message").value;
    let recaptchaResponse = document.getElementById("g-recaptcha-response").value;

    if (firstName === "" || lastName === "" || email === "" || mobile === "") {
        alert("Please fill in all required fields.");
        return false;
    }

    let emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
    if (!emailPattern.test(email)) {
        alert("Invalid email format.");
        return false;
    }

    let mobilePattern = /^[0-9]{10}$/;
    if (!mobilePattern.test(mobile)) {
        alert("Invalid mobile number format.");
        return false;
    }

    if (recaptchaResponse === "") {
        alert("Please verify that you are not a robot.");
        return false;
    }

    return true;
}

function sendMail(event) {
    event.preventDefault();
    if (!validateForm()) {
        return;
    }

    fetch("/api/contact.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            first_name: document.getElementById("first_name").value,
            last_name: document.getElementById("last_name").value,
            email: document.getElementById("email").value,
            mobile: document.getElementById("mobile").value,
            message: document.getElementById("message").value,
            website: document.getElementById("website") ? document.getElementById("website").value : "",
            recaptcha: document.getElementById("g-recaptcha-response").value
        })
    }).then(function (r) {
        return r.json().catch(function () { return { ok: false, error: "Bad response" }; });
    }).then(function (j) {
        if (j.ok) {
            alert("Form Submitted !!");
            document.getElementById("contact_form").reset();
            grecaptcha.reset();
        } else {
            alert("Failed to send form. Please try again. " + (j.error || ""));
        }
    }).catch(function (error) {
        alert("Failed to send form. Please try again." + error.message);
    });
}

// Stage J: the section heading reveals via the shared one-time .sj-reveal
// system (site.js) — no per-file reveal code needed anymore.
