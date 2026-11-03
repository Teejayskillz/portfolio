/* =========================================================================
   TijaniDev — admin dashboard behaviour
   ========================================================================= */

(function () {
    "use strict";

    var doc = document;
    doc.documentElement.classList.add("has-js");

    function ready(fn) {
        if (doc.readyState !== "loading") {
            fn();
        } else {
            doc.addEventListener("DOMContentLoaded", fn, { once: true });
        }
    }

    /* Sidebar (mobile) ------------------------------------------------- */
    function initSidebar() {
        var toggle = doc.querySelector("[data-sidebar-toggle]");
        if (!toggle) {
            return;
        }

        function setOpen(open) {
            doc.body.classList.toggle("sidebar-open", open);
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
        }

        toggle.addEventListener("click", function (event) {
            event.stopPropagation();
            setOpen(!doc.body.classList.contains("sidebar-open"));
        });

        doc.addEventListener("click", function (event) {
            if (!doc.body.classList.contains("sidebar-open")) {
                return;
            }
            if (event.target.closest(".admin-sidebar") || event.target.closest("[data-sidebar-toggle]")) {
                return;
            }
            setOpen(false);
        });

        doc.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                setOpen(false);
            }
        });

        window.matchMedia("(min-width: 901px)").addEventListener("change", function (event) {
            if (event.matches) {
                setOpen(false);
            }
        });
    }

    /* Destructive actions need confirmation ---------------------------- */
    function initConfirms() {
        doc.addEventListener("submit", function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            var message = form.getAttribute("data-confirm");
            if (!message) {
                return;
            }
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        doc.addEventListener("click", function (event) {
            var link = event.target.closest("[data-confirm-link]");
            if (link && !window.confirm(link.getAttribute("data-confirm-link"))) {
                event.preventDefault();
            }
        });
    }

    /* Automatic slug --------------------------------------------------- */
    function slugify(value) {
        return value
            .toString()
            .normalize("NFKD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "")
            .replace(/-{2,}/g, "-")
            .slice(0, 80);
    }

    function initSlug() {
        var title = doc.querySelector("[data-slug-source]");
        var slug = doc.querySelector("[data-slug-target]");
        if (!title || !slug) {
            return;
        }

        var manual = slug.value.trim() !== "";
        slug.addEventListener("input", function () {
            manual = slug.value.trim() !== "";
        });

        title.addEventListener("input", function () {
            if (!manual) {
                slug.value = slugify(title.value);
            }
        });

        var reset = doc.querySelector("[data-slug-reset]");
        if (reset) {
            reset.addEventListener("click", function (event) {
                event.preventDefault();
                manual = false;
                slug.value = slugify(title.value);
                slug.focus();
            });
        }
    }

    /* Local image previews -------------------------------------------- */
    function initPreviews() {
        Array.prototype.forEach.call(doc.querySelectorAll("[data-preview-input]"), function (input) {
            var targetSelector = input.getAttribute("data-preview-input");
            var target = doc.querySelector(targetSelector);
            if (!target) {
                return;
            }

            input.addEventListener("change", function () {
                var file = input.files && input.files[0];
                if (!file || !/^image\//.test(file.type)) {
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (event) {
                    target.innerHTML = "";
                    var image = doc.createElement("img");
                    image.src = String(event.target.result);
                    image.alt = "Selected image preview";
                    target.appendChild(image);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    /* Character counters ---------------------------------------------- */
    function initCounters() {
        Array.prototype.forEach.call(doc.querySelectorAll("[data-count-for]"), function (output) {
            var field = doc.getElementById(output.getAttribute("data-count-for"));
            if (!field) {
                return;
            }
            var max = parseInt(field.getAttribute("maxlength") || "0", 10);
            var render = function () {
                var length = field.value.length;
                output.textContent = max > 0 ? length + " / " + max : String(length);
                output.classList.toggle("is-over", max > 0 && length > max);
            };
            field.addEventListener("input", render);
            render();
        });
    }

    /* Submit protection ------------------------------------------------ */
    function initSubmitGuard() {
        Array.prototype.forEach.call(doc.querySelectorAll("form[data-guard]"), function (form) {
            form.addEventListener("submit", function (event) {
                if (typeof form.checkValidity === "function" && !form.checkValidity()) {
                    return;
                }
                if (form.dataset.submitting === "true") {
                    event.preventDefault();
                    return;
                }
                form.dataset.submitting = "true";
                var button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                }
            });
        });
    }

    /* Dismissible alerts ---------------------------------------------- */
    function initAlerts() {
        doc.addEventListener("click", function (event) {
            var button = event.target.closest("[data-dismiss-alert]");
            if (button) {
                var alert = button.closest(".alert");
                if (alert) {
                    alert.remove();
                }
            }
        });
    }

    /* Gallery lightbox (reuses the public lightbox styles) ------------- */
    function initLightbox() {
        var triggers = Array.prototype.slice.call(doc.querySelectorAll("[data-lightbox]"));
        if (!triggers.length || typeof doc.createElement("dialog").showModal !== "function") {
            return;
        }

        var dialog = doc.createElement("dialog");
        dialog.className = "lightbox";
        dialog.innerHTML =
            '<div class="lightbox__inner">' +
            '<button type="button" class="lightbox__close" aria-label="Close image">&times;</button>' +
            '<img class="lightbox__image" alt="">' +
            "</div>";
        doc.body.appendChild(dialog);

        var image = dialog.querySelector(".lightbox__image");
        var close = dialog.querySelector(".lightbox__close");

        triggers.forEach(function (trigger) {
            trigger.addEventListener("click", function (event) {
                event.preventDefault();
                image.src = trigger.getAttribute("href");
                image.alt = trigger.getAttribute("data-caption") || "";
                dialog.showModal();
            });
        });

        close.addEventListener("click", function () {
            dialog.close();
        });
        dialog.addEventListener("click", function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    }

    ready(function () {
        initSidebar();
        initConfirms();
        initSlug();
        initPreviews();
        initCounters();
        initSubmitGuard();
        initAlerts();
        initLightbox();
    });
})();
