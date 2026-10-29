document.addEventListener("DOMContentLoaded", function () {

    // Customer login validation
    var customerForm = document.getElementById("customerLoginForm");
    if (customerForm) {
        customerForm.addEventListener("submit", function (e) {

            var email = document.getElementById("email");
            var password = document.getElementById("password");

            var emailError = document.getElementById("emailError");
            var passwordError = document.getElementById("passwordError");

            emailError.textContent = "";
            passwordError.textContent = "";

            var valid = true;

            if (email.value.trim() === "") {
                emailError.textContent = "Email is required!";
                valid = false;
            }

            if (password.value.trim() === "") {
                passwordError.textContent = "Password is required!";
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
            }
        });
    }

    // Chef login validation
    var chefForm = document.getElementById("chefLoginForm");
    if (chefForm) {
        chefForm.addEventListener("submit", function (e) {

            var email = document.getElementById("chefEmail");
            var password = document.getElementById("chefPassword");

            var emailError = document.getElementById("chefEmailError");
            var passwordError = document.getElementById("chefPasswordError");

            emailError.textContent = "";
            passwordError.textContent = "";

            var valid = true;

            if (email.value.trim() === "") {
                emailError.textContent = "Email is required!";
                valid = false;
            }

            if (password.value.trim() === "") {
                passwordError.textContent = "Password is required!";
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
            }
        });
    }

});
