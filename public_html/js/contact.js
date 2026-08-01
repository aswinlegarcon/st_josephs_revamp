
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

    let parms = {
        first_name : document.getElementById("first_name").value,
        last_name : document.getElementById("last_name").value,
        email : document.getElementById("email").value,
        mobile : document.getElementById("mobile").value,
        message : document.getElementById("message").value,
    };

    emailjs.send("service_jh0ghjn","template_4j0k0ib",parms).then(function(response) {
        alert("Form Submitted !!");
        document.getElementById("contact_form").reset();
        grecaptcha.reset();
    }, function(error) {
        alert("Failed to send form. Please try again."+error.message);
    });
}

window.addEventListener('scroll', reveal);
function reveal()
{
    var reveals = document.querySelectorAll('.reveal-contact');
    for(var i=0; i< reveals.length;i++)
        {
            var windowheight = window.innerHeight;
            var revealtop = reveals[i].getBoundingClientRect().top;
            var revealpoint = 150;

            if(revealtop < windowheight-revealpoint)
                {
                    reveals[i].classList.add('active');
                }
                else
                {
                    reveals[i].classList.remove('active');
                }
        }
}

