document.addEventListener("DOMContentLoaded", function () {
    const backToTopButton = document.querySelector(".back-to-top");
    const socialFloat = document.querySelector(".social-float");

    if (backToTopButton) {
        function updateButtonVisibility() {
            const isVisible = window.scrollY > 300;

            backToTopButton.classList.toggle("is-visible", isVisible);
            backToTopButton.setAttribute("aria-hidden", String(!isVisible));
            backToTopButton.tabIndex = isVisible ? 0 : -1;
        }

        window.addEventListener("scroll", updateButtonVisibility, { passive: true });
        backToTopButton.addEventListener("click", function () {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });

        updateButtonVisibility();
    }

    if (socialFloat) {
        const socialToggle = socialFloat.querySelector(".social-toggle");
        const socialLinks = socialFloat.querySelector(".social-links");
        const links = socialLinks.querySelectorAll("a");

        function setSocialMenuOpen(isOpen) {
            socialFloat.classList.toggle("is-open", isOpen);
            socialToggle.setAttribute("aria-expanded", String(isOpen));
            socialToggle.setAttribute("aria-label", isOpen ? "Close social links" : "Open social links");
            socialLinks.setAttribute("aria-hidden", String(!isOpen));
            links.forEach(function (link) {
                link.tabIndex = isOpen ? 0 : -1;
            });
        }

        socialToggle.addEventListener("click", function () {
            setSocialMenuOpen(socialToggle.getAttribute("aria-expanded") !== "true");
        });

        document.addEventListener("click", function (event) {
            if (!socialFloat.contains(event.target)) {
                setSocialMenuOpen(false);
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && socialToggle.getAttribute("aria-expanded") === "true") {
                setSocialMenuOpen(false);
                socialToggle.focus();
            }
        });
    }
});
