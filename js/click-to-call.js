document.addEventListener("DOMContentLoaded", function () {
    const widget = document.querySelector(".click-to-call-widget");

    if (!widget) {
        return;
    }

    const toggle = widget.querySelector(".click-to-call-toggle");
    const panel = widget.querySelector(".click-to-call-panel");
    const closeButton = widget.querySelector(".click-to-call-close");
    const firstName = widget.querySelector("#call-first-name");

    function setPanelOpen(isOpen, restoreFocus = true) {
        panel.hidden = !isOpen;
        panel.setAttribute("aria-hidden", String(!isOpen));
        toggle.setAttribute("aria-expanded", String(isOpen));
        toggle.setAttribute("aria-label", isOpen ? "Close Click2Message form" : "Open Click2Message form");

        if (isOpen) {
            firstName.focus();
        } else if (restoreFocus) {
            toggle.focus();
        }
    }

    toggle.addEventListener("click", function () {
        setPanelOpen(toggle.getAttribute("aria-expanded") !== "true");
    });

    closeButton.addEventListener("click", function () {
        setPanelOpen(false);
    });

    document.addEventListener("click", function (event) {
        if (!widget.contains(event.target) && toggle.getAttribute("aria-expanded") === "true") {
            setPanelOpen(false, false);
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && toggle.getAttribute("aria-expanded") === "true") {
            setPanelOpen(false);
        }
    });
});
