/**
 * Next Order Plus - Rules Admin JavaScript
 *
 * Handles rule management UI interactions
 */
(($) => {
    // Cache DOM elements
    const $rulesTable = $(".nop-rules-table");
    const $modal = $("#nop-rule-modal");
    const $modalTitle = $("#nop-modal-title");
    const $ruleForm = $("#nop-rule-form");
    const $conditionFields = $("#condition_fields");
    const $actionFields = $("#action_fields");
    const $notice = $(".nop-rules-notice");
    // Progress bar elements
    const $progressSegments = $(".nop-progress-segment");
    const $activeCategory = $("#nop-active-category");
    const $categorySelect = $("#rule_category");
    // Category management elements
    let $categoryControls = $(".nop-category-controls");
    let $categoryFilter = $("#nop-category-filter");

    // Initialize the admin UI
    function init() {
        // Initialize Select2 for product selection
        initSelect2();

        // Group rules by categories
        groupRulesByCategory();

        // Bind event handlers
        bindEvents();

        // Initialize category selection
        initCategoryControls();

        // Check for URL parameters
        checkUrlParams();
    }

    // Initialize category controls outside the modal
    function initCategoryControls() {
        // Create category selection controls if they don't exist
        if ($categoryControls.length === 0) {
            const $controlsHTML = $(`
                <div class="nop-category-controls">
                    <h2>${nop_rules_data.i18n.active_category || "Active Category"}</h2>
                    <div class="nop-category-selector">
                        <select id="nop-category-filter">
                            <option value="">${nop_rules_data.i18n.all_categories || "All Categories"}</option>
                        </select>
                        <button type="button" class="button" id="nop-activate-category">${nop_rules_data.i18n.activate || "Activate"}</button>
                    </div>
                    <div class="nop-category-indicator"></div>
                </div>
            `);

            // Insert after the rules header
            $controlsHTML.insertAfter($(".nop-rules-header"));

            // Re-cache the elements
            $categoryControls = $(".nop-category-controls");
            $categoryFilter = $("#nop-category-filter");
        }

        // Populate the category dropdown from existing rules
        populateCategoryDropdown();
    }

    // Populate category dropdown from existing rules
    function populateCategoryDropdown() {
        const categories = new Set();
        let activeCategory = "";

        // Collect categories from rules data
        $rulesTable.find(".nop-rule-data").each(function () {
            try {
                const ruleData = JSON.parse($(this).val());
                if (ruleData.category) {
                    categories.add(ruleData.category);

                    // Check if this category is active
                    if (ruleData.active) {
                        activeCategory = ruleData.category;
                    }
                }
            } catch (e) {
                console.error("Failed to parse rule data:", e);
            }
        });

        // Clear existing options except the first one
        $categoryFilter.find("option:not(:first)").remove();

        // Add categories to dropdown
        for (const category of Array.from(categories).sort()) {
            const $option = $(`<option value="${category}">${category}</option>`);
            $categoryFilter.append($option);
        }

        // Set the active category in the dropdown
        if (activeCategory) {
            $categoryFilter.val(activeCategory);
            highlightActiveCategory(activeCategory);
        }

        // Bind event to activate category button
        $("#nop-activate-category")
            .off("click")
            .on("click", () => {
                const selectedCategory = $categoryFilter.val();
                if (selectedCategory) {
                    activateCategory(selectedCategory);
                } else {
                    showNotice("Please select a category to activate", "error");
                }
            });
    }

    // Activate a selected category
    function activateCategory(category) {
        if (!category) return;

        // Find all rules in this category
        const rulesToActivate = [];
        const rulesToDeactivate = [];

        $rulesTable.find("tr[data-rule-id]").each(function () {
            const $row = $(this);
            const $ruleDataInput = $row.find(".nop-rule-data");

            try {
                const ruleData = JSON.parse($ruleDataInput.val());
                const ruleId = $row.data("rule-id");

                if (ruleData.category === category) {
                    // This rule belongs to the category we're activating
                    rulesToActivate.push({
                        id: ruleId,
                        active: ruleData.active,
                    });
                } else if (ruleData.active) {
                    // This is an active rule in another category
                    rulesToDeactivate.push({
                        id: ruleId,
                        active: true,
                    });
                }
            } catch (e) {
                console.error("Error parsing rule data:", e);
            }
        });

        // Confirm with user if needed
        if (rulesToDeactivate.length > 0) {
            if (
                !confirm(
                    `Activating category "${category}" will deactivate ${rulesToDeactivate.length} rule(s) in other categories. Continue?`,
                )
            ) {
                return;
            }
        }

        // Deactivate rules in other categories
        let processedCount = 0;
        let errorCount = 0;

        // Process rules to deactivate
        for (const rule of rulesToDeactivate) {
            $.ajax({
                url: nop_rules_data.ajax_url,
                type: "POST",
                data: {
                    action: `${nop_rules_data.prefix}toggle_rule`,
                    nonce: nop_rules_data.nonce,
                    rule_id: rule.id,
                    active: 0, // Deactivate
                },
                success: (response) => {
                    processedCount++;
                    if (!response.success) {
                        errorCount++;
                    }
                    checkCompletion();
                },
                error: () => {
                    processedCount++;
                    errorCount++;
                    checkCompletion();
                },
            });
        }

        // Process rules to activate
        for (const rule of rulesToActivate) {
            // Only send request if the rule needs to be activated
            if (!rule.active) {
                $.ajax({
                    url: nop_rules_data.ajax_url,
                    type: "POST",
                    data: {
                        action: `${nop_rules_data.prefix}toggle_rule`,
                        nonce: nop_rules_data.nonce,
                        rule_id: rule.id,
                        active: 1, // Activate
                    },
                    success: (response) => {
                        processedCount++;
                        if (!response.success) {
                            errorCount++;
                        }
                        checkCompletion();
                    },
                    error: () => {
                        processedCount++;
                        errorCount++;
                        checkCompletion();
                    },
                });
            } else {
                // Rule is already active, count as processed
                processedCount++;
                checkCompletion();
            }
        };

        // Show loading indicator
        showNotice("Updating rules...", "info");

        // Check if all operations are complete
        function checkCompletion() {
            const totalOperations =
                rulesToDeactivate.length +
                rulesToActivate.filter((r) => !r.active).length;

            if (processedCount >= totalOperations) {
                // All done
                if (errorCount > 0) {
                    showNotice(
                        `Completed with ${errorCount} errors. Please refresh the page.`,
                        "error",
                    );
                } else {
                    showNotice(
                        `Successfully activated category "${category}"`,
                        "success",
                    );
                    // Update UI
                    highlightActiveCategory(category);
                    // Reload after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            }
        }
    }

    // Highlight the active category in the UI
    function highlightActiveCategory(category) {
        if (!category) return;

        // Update dropdown to match
        $categoryFilter.val(category);

        // Highlight rows in this category, dim others
        $rulesTable.find("tr[data-rule-id]").each(function () {
            const $row = $(this);
            const $ruleDataInput = $row.find(".nop-rule-data");

            try {
                const ruleData = JSON.parse($ruleDataInput.val());

                if (ruleData.category === category) {
                    $row.removeClass("nop-inactive-category");
                } else {
                    $row.addClass("nop-inactive-category");
                }
            } catch (e) {
                console.error("Error parsing rule data:", e);
            }
        });

        // Update category headers
        $rulesTable.find(".nop-category-header").each(function () {
            const $header = $(this);
            const headerCategory = $header
                .find(".nop-category-name")
                .text()
                .replace("Category: ", "");

            if (headerCategory === category) {
                $header.addClass("active").removeClass("inactive");
            } else {
                $header.addClass("inactive").removeClass("active");
            }
        });

        // Update the indicator in the admin
        $(".nop-category-indicator").html(`
            <div class="nop-active-badge">
                Active: <strong>${category}</strong>
            </div>
        `);
    }

    // Initialize Select2 for product dropdowns
    function initSelect2() {
        $(".nop-product-select").select2({
            width: "100%",
            placeholder: nop_rules_data.i18n.select_product,
            data: nop_rules_data.products,
        });
    }

    // Group rules by categories in the table
    function groupRulesByCategory() {
        // Get all table rows
        const $rows = $rulesTable.find("tbody tr");

        if ($rows.length <= 1) {
            return; // No need to group if 0 or 1 row
        }

        const categories = {};
        const activeCategories = new Set();

        // First pass: collect categories and their rules and identify active categories
        $rows.each(function () {
            const $row = $(this);
            const $ruleDataInput = $row.find(".nop-rule-data");

            if (!$ruleDataInput.length) {
                return;
            }

            try {
                const ruleData = JSON.parse($ruleDataInput.val());
                const category = ruleData.category || "uncategorized";

                // Add data attribute for category filtering
                $row.attr("data-category", category);

                // If rule is active, mark its category as active
                if (ruleData.active) {
                    activeCategories.add(category);
                }

                // Add to categories object
                if (!categories[category]) {
                    categories[category] = [];
                }

                categories[category].push($row);
            } catch (e) {
                console.error("Failed to parse rule data:", e);
            }
        });

        // Only proceed if we have categories
        if (Object.keys(categories).length === 0) {
            return;
        }

        // Remove all rows
        $rows.detach();

        // Add category headers and rows
        for (const [category, rows] of Object.entries(categories)) {
            const isCategoryActive = activeCategories.has(category);

            // Create category header
            const $categoryHeader = $(`
                <tr class="nop-category-header ${isCategoryActive ? "active" : "inactive"}">
                    <th colspan="7" class="nop-category-name">
                        ${category === "uncategorized" ? "Uncategorized Rules" : `Category: ${category}`}
                    </th>
                </tr>
            `);

            // Append the header
            $rulesTable.find("tbody").append($categoryHeader);

            // Append the rows with appropriate styling
            for (const $row of rows) {
                if (!isCategoryActive) {
                    $row.addClass("nop-inactive-category");
                }
                $rulesTable.find("tbody").append($row);
            }
        }
    }

    // Modify bindEvents function to add category filtering
    function bindEvents() {
        // Add rule button
        $(".nop-add-rule").on("click", openAddRuleModal);

        // Update the edit rule button click handler
        $rulesTable.on("click", ".nop-edit-rule", function () {
            try {
                const ruleId = $(this).data("rule-id");
                console.log("Edit button clicked for rule ID:", ruleId);

                // Find the hidden rule data input
                const $ruleDataInput = $(this).closest("tr").find(".nop-rule-data");

                if (!$ruleDataInput.length) {
                    console.error("Rule data input not found");
                    showNotice("Error: Rule data not found", "error");
                    return;
                }

                const ruleDataValue = $ruleDataInput.val();
                console.log("Raw rule data:", ruleDataValue);

                // Try to parse the JSON data
                let ruleData;
                try {
                    ruleData = JSON.parse(ruleDataValue);
                } catch (e) {
                    console.error("JSON parse error:", e);
                    console.error("Problematic JSON string:", ruleDataValue);
                    showNotice(
                        "Error parsing rule data. Please refresh the page and try again.",
                        "error",
                    );
                    return;
                }

                console.log("Parsed rule data:", ruleData);
                openEditRuleModal(ruleData);
            } catch (error) {
                console.error("Error in edit rule handler:", error);
                showNotice("An unexpected error occurred. Please try again.", "error");
            }
        });

        // Delete rule button
        $rulesTable.on("click", ".nop-delete-rule", function () {
            const ruleId = $(this).data("rule-id");
            confirmDeleteRule(ruleId);
        });

        // Toggle rule active state
        $rulesTable.on("click", ".nop-rule-status", function () {
            const $toggle = $(this);
            const $row = $toggle.closest("tr");
            const ruleId = $row.data("rule-id");
            const isActive = $toggle.prop("checked");

            // If activating a rule, read the category to warn about deactivating other categories
            if (isActive) {
                try {
                    const $ruleDataInput = $row.find(".nop-rule-data");
                    if ($ruleDataInput.length) {
                        const ruleData = JSON.parse($ruleDataInput.val());
                        if (ruleData.category && ruleData.category !== "uncategorized") {
                            if (
                                !confirm(
                                    "Activating this rule will deactivate all rules in other categories. Continue?",
                                )
                            ) {
                                $toggle.prop("checked", !isActive); // Revert the toggle
                                return;
                            }
                        }
                    }
                } catch (e) {
                    console.error("Error parsing rule data:", e);
                }
            }

            toggleRuleActive(ruleId, isActive);
        });

        // Close modal
        $(".nop-modal-close, .nop-cancel-rule").on("click", closeModal);

        // Handle form submission
        $ruleForm.on("submit", saveRule);

        // Handle condition type change
        $("#condition_type").on("change", function () {
            updateConditionFields($(this).val());
        });

        // Handle rule category change - set condition type to match
        $("#rule_category").on("change", function () {
            const category = $(this).val();
            $("#condition_type").val(category).trigger("change");

            // Update progress bar highlighting
            updateProgressBar(category);
        });

        // Handle category progress bar segment clicks
        $(".nop-progress-segment").on("click", function () {
            const category = $(this).data("category");

            // Update select dropdown
            $("#rule_category").val(category).trigger("change");

            // Visual feedback
            $(this).addClass("active").siblings().removeClass("active");
        });

        // Update condition fields whenever the form is shown
        $modal.on("shown", () => {
            const category = $("#rule_category").val();
            updateConditionFields(category || $("#condition_type").val());
        });

        // Handle action type change
        $("#action_type").on("change", () => {
            updateActionFields();
        });

        // Populate the category suggestions based on existing rules
        $modal.on("show", () => {
            const categories = new Set();

            // Gather categories from existing rules
            $rulesTable.find(".nop-rule-data").each(function () {
                const value = $(this).val();
                if (value) {
                    try {
                        const ruleData = JSON.parse(value);
                        if (ruleData.category) {
                            categories.add(ruleData.category);
                        }
                    } catch (e) {
                        console.error("Error parsing rule data:", e);
                    }
                }
            });

            // Create datalist for suggestions if it doesn't exist
            if (!$("#category-suggestions").length) {
                const $datalist = $("<datalist id='category-suggestions'></datalist>");

                for (const category of categories) {
                    $datalist.append(`<option value="${category}">`);
                }

                $("body").append($datalist);
                $("#rule_category").attr("list", "category-suggestions");
            }
        });

        // Category filter dropdown
        $categoryFilter.on("change", function () {
            const selectedCategory = $(this).val();

            if (!selectedCategory) {
                // Show all rules
                $rulesTable.find("tr[data-rule-id]").show();
                $rulesTable.find(".nop-category-header").show();
            } else {
                // Hide all rules first
                $rulesTable.find("tr[data-rule-id]").hide();
                $rulesTable.find(".nop-category-header").hide();

                // Show only rules in the selected category
                $rulesTable.find(`tr[data-category="${selectedCategory}"]`).show();
                $rulesTable
                    .find(`.nop-category-header:contains("${selectedCategory}")`)
                    .show();
            }
        });
    }

    // Show notification
    function showNotice(message, type) {
        $notice
            .removeClass("hidden success error info")
            .addClass(type)
            .text(message)
            .fadeIn();

        setTimeout(() => {
            $notice.fadeOut();
        }, 5000);
    }

    // Initialize when the document is ready
    $(document).ready(init);
})(jQuery);
