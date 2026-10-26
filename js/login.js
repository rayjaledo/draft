
document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById("loginPassword");
    const toggleButton = document.getElementById("togglePassword");

    if (!passwordInput || !toggleButton) {
        return;
    }

    toggleButton.addEventListener("click", function () {
        const isPasswordHidden = passwordInput.type === "password";

        passwordInput.type = isPasswordHidden ? "text" : "password";

        toggleButton.textContent = isPasswordHidden ? "🙈" : "👁";

        toggleButton.setAttribute(
            "aria-label",
            isPasswordHidden ? "Hide password" : "Show password"
        );

        toggleButton.setAttribute(
            "aria-pressed",
            isPasswordHidden ? "true" : "false"
        );

        passwordInput.focus();
    });
});
