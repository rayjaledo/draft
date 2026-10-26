
document.addEventListener("DOMContentLoaded", function () {
    // School ID formatting: SCC-00-00000000
    const studentIdInput = document.getElementById("student_id_no");

if (studentIdInput) {
    studentIdInput.addEventListener("input", function () {
        this.value = this.value.toUpperCase();
    });
}

    // Show or hide password fields.
    document.querySelectorAll(".signup-password-toggle").forEach(function (button) {
        button.addEventListener("click", function () {
            const targetId = this.dataset.passwordTarget;
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            const showPassword = input.type === "password";

            input.type = showPassword ? "text" : "password";
            this.textContent = showPassword ? "🙈" : "👁";
            this.setAttribute(
                "aria-label",
                showPassword ? "Hide password" : "Show password"
            );
            this.setAttribute("aria-pressed", String(showPassword));
        });
    });

    // Check that both passwords match before submission.
    const form = document.querySelector(
        'form[action="signup.php"]'
    );

    if (form) {
        form.addEventListener("submit", function (event) {
            const password = document.getElementById("password");
            const confirmPassword = document.getElementById("confirm_password");

            if (
                password &&
                confirmPassword &&
                password.value !== confirmPassword.value
            ) {
                event.preventDefault();
                confirmPassword.setCustomValidity("Passwords do not match.");
                confirmPassword.reportValidity();
            }
        });
    }

    const confirmPassword = document.getElementById("confirm_password");

    if (confirmPassword) {
        confirmPassword.addEventListener("input", function () {
            this.setCustomValidity("");
        });
    }
});
