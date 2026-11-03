/* =========================================================================
   TijaniDev — public site behaviour
   -------------------------------------------------------------------------
   Small, dependency-free progressive enhancements. Everything on the page
   works without this file; it only adds polish.
   ========================================================================= */

(function () {
    "use strict";

    var doc = document;
    var root = doc.documentElement;
    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    doc.documentElement.classList.add("has-js");

    function ready(fn) {
        if (doc.readyState !== "loading") {
            fn();
        } else {
            doc.addEventListener("DOMContentLoaded", fn, { once: true });
        }
    }

    /* ---------------------------------------------------------------
       Sticky header state + reading progress
       ------------------------------------------------------------ */
    function initHeader() {
        var header = doc.querySelector("[data-header]");
        var progress = doc.querySelector("[data-scroll-progress]");
        var toTop = doc.querySelector("[data-to-top]");
        var ticking = false;

        function update() {
            var y = window.scrollY || window.pageYOffset;
            var max = doc.documentElement.scrollHeight - window.innerHeight;

            if (header) {
                header.classList.toggle("is-scrolled", y > 12);
            }
            if (progress) {
                progress.style.width = (max > 0 ? Math.min(100, (y / max) * 100) : 0) + "%";
            }
            if (toTop) {
                toTop.classList.toggle("is-visible", y > 620);
            }
            ticking = false;
        }

        window.addEventListener(
            "scroll",
            function () {
                if (!ticking) {
                    ticking = true;
                    window.requestAnimationFrame(update);
                }
            },
            { passive: true }
        );

        update();
    }

    /* ---------------------------------------------------------------
       Mobile navigation
       ------------------------------------------------------------ */
    function initNav() {
        var toggle = doc.querySelector("[data-nav-toggle]");
        var nav = doc.getElementById("site-nav");
        if (!toggle || !nav) {
            return;
        }

        function setOpen(open) {
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
            doc.body.classList.toggle("nav-open", open);
            var label = toggle.querySelector(".nav-toggle__text");
            if (label) {
                label.textContent = open ? "Close" : "Menu";
            }
            if (open) {
                var first = nav.querySelector("a");
                if (first) {
                    window.setTimeout(function () {
                        first.focus({ preventScroll: true });
                    }, 60);
                }
            }
        }

        toggle.addEventListener("click", function () {
            setOpen(toggle.getAttribute("aria-expanded") !== "true");
        });

        nav.addEventListener("click", function (event) {
            if (event.target.closest("a")) {
                setOpen(false);
            }
        });

        doc.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && doc.body.classList.contains("nav-open")) {
                setOpen(false);
                toggle.focus();
            }
        });

        var wide = window.matchMedia("(min-width: 961px)");
        var onChange = function (event) {
            if (event.matches) {
                setOpen(false);
            }
        };
        if (typeof wide.addEventListener === "function") {
            wide.addEventListener("change", onChange);
        } else if (typeof wide.addListener === "function") {
            wide.addListener(onChange);
        }
    }

    /* ---------------------------------------------------------------
       Reveal on scroll
       ------------------------------------------------------------ */
    function initReveal() {
        var items = doc.querySelectorAll("[data-reveal]");
        if (!items.length) {
            return;
        }

        if (reduceMotion || !("IntersectionObserver" in window)) {
            Array.prototype.forEach.call(items, function (item) {
                item.classList.add("is-revealed");
            });
            return;
        }

        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-revealed");
                        observer.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: "0px 0px -8% 0px", threshold: 0.08 }
        );

        Array.prototype.forEach.call(items, function (item, index) {
            if (!item.style.getPropertyValue("--reveal-delay") && index % 3 !== 0) {
                item.style.setProperty("--reveal-delay", (index % 3) * 70 + "ms");
            }
            observer.observe(item);
        });
    }

    /* ---------------------------------------------------------------
       Gallery lightbox
       ------------------------------------------------------------ */
    function initLightbox() {
        var triggers = Array.prototype.slice.call(doc.querySelectorAll("[data-lightbox]"));
        if (!triggers.length || typeof doc.createElement("dialog").showModal !== "function") {
            return;
        }

        var dialog = doc.createElement("dialog");
        dialog.className = "lightbox";
        dialog.setAttribute("aria-label", "Project screenshot");
        dialog.innerHTML =
            '<div class="lightbox__inner">' +
            '<button type="button" class="lightbox__close" aria-label="Close image">&times;</button>' +
            '<img class="lightbox__image" alt="">' +
            '<div class="lightbox__bar"><span class="lightbox__caption"></span><span class="lightbox__hint">Esc to close</span></div>' +
            "</div>";
        doc.body.appendChild(dialog);

        var image = dialog.querySelector(".lightbox__image");
        var caption = dialog.querySelector(".lightbox__caption");
        var close = dialog.querySelector(".lightbox__close");

        triggers.forEach(function (trigger) {
            trigger.addEventListener("click", function (event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey) {
                    return;
                }
                event.preventDefault();
                image.src = trigger.getAttribute("href");
                image.alt = trigger.getAttribute("data-caption") || "";
                caption.textContent = trigger.getAttribute("data-caption") || "";
                if (typeof dialog.showModal === "function") {
                    dialog.showModal();
                }
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

        dialog.addEventListener("close", function () {
            image.removeAttribute("src");
        });
    }

    /* ---------------------------------------------------------------
       Alerts: dismiss + auto-hide confirmations
       ------------------------------------------------------------ */
    function initAlerts() {
        doc.addEventListener("click", function (event) {
            var button = event.target.closest("[data-dismiss-alert]");
            if (!button) {
                return;
            }
            var alert = button.closest(".alert");
            if (alert) {
                alert.remove();
            }
        });

        Array.prototype.forEach.call(doc.querySelectorAll(".alert--success"), function (alert) {
            window.setTimeout(function () {
                alert.style.transition = "opacity 260ms ease, transform 260ms ease";
                alert.style.opacity = "0";
                alert.style.transform = "translateY(-6px)";
                window.setTimeout(function () {
                    alert.remove();
                }, 300);
            }, 7000);
        });
    }

    /* ---------------------------------------------------------------
       Forms: prevent double submits, live character counters
       ------------------------------------------------------------ */
    function initForms() {
        Array.prototype.forEach.call(doc.querySelectorAll("form[data-enhance]"), function (form) {
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
                    var original = button.textContent;
                    button.dataset.label = original;
                    button.textContent = "Sending…";
                    window.setTimeout(function () {
                        if (form.dataset.submitting !== "true") {
                            button.disabled = false;
                            button.textContent = original;
                        }
                    }, 12000);
                }
            });
        });

        Array.prototype.forEach.call(doc.querySelectorAll("[data-counter]"), function (field) {
            var target = doc.querySelector(field.getAttribute("data-counter"));
            if (!target) {
                return;
            }
            var max = parseInt(field.getAttribute("maxlength") || "0", 10);
            var render = function () {
                target.textContent = field.value.length + (max > 0 ? " / " + max : "");
            };
            field.addEventListener("input", render);
            render();
        });
    }

    /* ---------------------------------------------------------------
       Copy email address
       ------------------------------------------------------------ */
    function initCopy() {
        Array.prototype.forEach.call(doc.querySelectorAll("[data-copy]"), function (button) {
            button.addEventListener("click", function () {
                var value = button.getAttribute("data-copy");
                if (!value || !navigator.clipboard) {
                    return;
                }
                navigator.clipboard.writeText(value).then(function () {
                    var label = button.querySelector("span");
                    if (!label) {
                        return;
                    }
                    var original = label.textContent;
                    label.textContent = "Copied";
                    window.setTimeout(function () {
                        label.textContent = original;
                    }, 1800);
                });
            });
        });
    }

    ready(function () {
        initHeader();
        initNav();
        initReveal();
        initLightbox();
        initAlerts();
        initForms();
        initCopy();
    });
})();
