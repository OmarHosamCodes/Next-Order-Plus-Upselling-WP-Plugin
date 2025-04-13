/**
 * Next Order Plus - Admin JavaScript
 *
 * Enhances the admin interface with interactive elements
 * for the modern UI design.
 *
 * @package NextOrderPlus
 * @since 1.0.0
 */
(($) => {
    /**
     * Initialize admin UI enhancements
     */
    function initAdminUI() {
        // Stats cards hover effect
        $(".nop-stats-card").hover(
            function () {
                $(this).css("transform", "translateY(-2px)");
                $(this).css("box-shadow", "0 4px 10px rgba(0, 0, 0, 0.1)");
            },
            function () {
                $(this).css("transform", "translateY(0)");
                $(this).css("box-shadow", "0 1px 3px rgba(0, 0, 0, 0.08)");
            },
        );

        // Smooth form field focus effects
        $(".nop-admin-field input, .nop-admin-field textarea")
            .on("focus", function () {
                $(this).css("border-color", "#E54600");
                $(this).css("box-shadow", "0 0 0 1px #E54600");
            })
            .on("blur", function () {
                $(this).css("border-color", "");
                $(this).css("box-shadow", "");
            });

        // Feature animations
        $(".nop-feature").hover(
            function () {
                $(this)
                    .find(".nop-feature-icon")
                    .css("background-color", "rgba(229, 70, 0, 0.15)");
                $(this).find(".nop-feature-title").css("color", "#E54600");
            },
            function () {
                $(this)
                    .find(".nop-feature-icon")
                    .css("background-color", "rgba(229, 70, 0, 0.08)");
                $(this).find(".nop-feature-title").css("color", "");
            },
        );

        // Button hover effects
        $(".button-primary").hover(
            function () {
                $(this).css("transform", "translateY(-1px)");
            },
            function () {
                $(this).css("transform", "translateY(0)");
            },
        );

        // Create tabs functionality if needed
        initTabs();
    }

    /**
     * Initialize tabbed content if present
     */
    function initTabs() {
        if ($(".nop-admin-tab").length) {
            $(".nop-admin-tab").on("click", function () {
                const tabId = $(this).data("tab");

                // Update active tab
                $(".nop-admin-tab").removeClass("active");
                $(this).addClass("active");

                // Show tab content
                $(".nop-tab-content").hide();
                $(`#${tabId}`).show();
            });

            // Activate first tab by default
            $(".nop-admin-tab:first").click();
        }
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(() => {
        initAdminUI();
    });
})(jQuery);
