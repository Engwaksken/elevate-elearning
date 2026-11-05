document.addEventListener("DOMContentLoaded", function () {
    // Accessibility Widget Toggle
    const toggleWidget = document.getElementById("toggle-widget");
    const widgetPanel = document.getElementById("widget-panel");
    const increaseText = document.getElementById("increase-text");
    const decreaseText = document.getElementById("decrease-text");
    const toggleContrast = document.getElementById("toggle-contrast");
    const toggleDyslexia = document.getElementById("toggle-dyslexia");

    if (toggleWidget && widgetPanel) {
        toggleWidget.addEventListener("click", function (event) {
            event.stopPropagation();
            widgetPanel.classList.toggle("visible");
        });

        document.addEventListener("click", function (event) {
            if (!widgetPanel.contains(event.target) && event.target !== toggleWidget) {
                widgetPanel.classList.remove("visible");
            }
        });
    }

    if (increaseText) {
        increaseText.addEventListener("click", function () {
            document.body.classList.add("large-text");
        });
    }

    if (decreaseText) {
        decreaseText.addEventListener("click", function () {
            document.body.classList.remove("large-text");
        });
    }

    if (toggleContrast) {
        toggleContrast.addEventListener("click", function () {
            document.body.classList.toggle("high-contrast");
        });
    }

    if (toggleDyslexia) {
        toggleDyslexia.addEventListener("click", function () {
            document.body.classList.toggle("dyslexia-font");
        });
    }

    // Mobile Sidebar Toggle
    const sidebarToggleBtn = document.querySelector(".navbar-toggle");
    const sidebar = document.querySelector("#side-menu-collapse");

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("in"); // For Bootstrap 3.x
        });
    }
});
