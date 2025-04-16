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

    // Open modal to add a new rule
    function openAddRuleModal() {
        // Reset form
        $ruleForm[0].reset();
        $("#rule_id").val("");

        // Set modal title
        $modalTitle.text(nop_rules_data.i18n.add_rule || "Add Rule");

        // Reset condition and action fields
        $conditionFields.empty();
        $actionFields.empty();

        // Show the action form fields
        addActionTypeSelect();

        // Show modal
        $modal.show();
    }

    // Open modal to edit an existing rule
    function openEditRuleModal(ruleData) {
        // Set form fields
        $("#rule_id").val(ruleData.id);
        $("#rule_name").val(ruleData.name);
        $("#rule_description").val(ruleData.description);
        $("#rule_category").val(ruleData.category);
        $("#rule_priority").val(ruleData.priority);
        $("#rule_active").prop("checked", ruleData.active);

        // Set condition type (hidden field)
        $("#condition_type").val(ruleData.condition_type);

        // Set modal title
        $modalTitle.text(nop_rules_data.i18n.edit_rule || "Edit Rule");

        // Generate condition fields based on condition type
        updateConditionFields(
            ruleData.condition_type,
            ruleData.condition_value,
            ruleData.condition_params,
        );

        // Add action type select and populate with current action
        addActionTypeSelect(
            ruleData.action_type,
            ruleData.action_value,
            ruleData.action_params,
        );

        // Show modal
        $modal.show();
    }

    // Close the rule modal
    function closeModal() {
        $modal.hide();
    }

    // Add action type select to form
    function addActionTypeSelect(selectedType, selectedValue, params) {
        // Create action type selector
        const $actionTypeGroup = $("<div class='nop-form-group'></div>");
        $actionTypeGroup.append(
            `<label for="action_type">${nop_rules_data.i18n.action || "Action"}</label>`,
        );

        const $actionTypeSelect = $(
            "<select id='action_type' name='action_type' required></select>",
        );
        $actionTypeSelect.append(
            `<option value="">${nop_rules_data.i18n.select_action || "Select an action"}</option>`,
        );

        // Add available action types from data
        if (nop_rules_data.action_types) {
            Object.entries(nop_rules_data.action_types).forEach(([value, label]) => {
                const $option = $(`<option value="${value}">${label}</option>`);
                $actionTypeSelect.append($option);
            });
        }

        // Set selected value if provided
        if (selectedType) {
            $actionTypeSelect.val(selectedType);
        }

        $actionTypeGroup.append($actionTypeSelect);
        $actionFields.html($actionTypeGroup);

        // If a type is selected, create the specific fields for that type
        if (selectedType) {
            updateActionFields(selectedType, selectedValue, params);
        }
    }

    // Update action fields based on selected action type
    function updateActionFields(type, value, params) {
        const actionType = type || $("#action_type").val();

        if (!actionType) {
            return;
        }

        // Clear existing fields except the type selector
        $actionFields.find(".nop-form-group:not(:first)").remove();

        // Add appropriate fields based on action type
        switch (actionType) {
            case "percentage_discount":
                $actionFields.append(`
                    <div class="nop-form-group">
                        <label for="action_value">${nop_rules_data.i18n.discount_percentage || "Discount Percentage"}</label>
                        <div class="nop-input-group">
                            <input type="number" id="action_value" name="action_value" min="1" max="100" required value="${value || ""}">
                            <span class="nop-input-addon">%</span>
                        </div>
                        <p class="description">${nop_rules_data.i18n.percentage_discount_desc || "Percentage off cart total"}</p>
                    </div>
                `);
                break;

            case "fixed_discount":
                $actionFields.append(`
                    <div class="nop-form-group">
                        <label for="action_value">${nop_rules_data.i18n.discount_amount || "Discount Amount"}</label>
                        <div class="nop-input-group">
                            <span class="nop-input-addon">${nop_rules_data.currency_symbol || "$"}</span>
                            <input type="number" id="action_value" name="action_value" min="0.01" step="0.01" required value="${value || ""}">
                        </div>
                        <p class="description">${nop_rules_data.i18n.fixed_discount_desc || "Fixed amount off cart total"}</p>
                    </div>
                `);
                break;

            // Add more action types as needed
        }
    }

    // Update condition fields based on condition type
    function updateConditionFields(type, value, params) {
        const conditionType = type || "";

        // Clear existing fields
        $conditionFields.empty();

        if (!conditionType) {
            return;
        }

        // Add appropriate fields based on condition type
        switch (conditionType) {
            case "cart_total":
                $conditionFields.append(`
                    <div class="nop-form-group">
                        <label for="condition_value">${nop_rules_data.i18n.min_amount || "Minimum Cart Amount"}</label>
                        <div class="nop-input-group">
                            <span class="nop-input-addon">${nop_rules_data.currency_symbol || "$"}</span>
                            <input type="number" id="condition_value" name="condition_value" min="0.01" step="0.01" required value="${value || ""}">
                        </div>
                        <p class="description">${nop_rules_data.i18n.min_amount_desc || "Minimum cart subtotal required"}</p>
                    </div>
                `);
                break;

            case "item_count":
                $conditionFields.append(`
                    <div class="nop-form-group">
                        <label for="condition_value">${nop_rules_data.i18n.min_items || "Minimum Items"}</label>
                        <input type="number" id="condition_value" name="condition_value" min="1" step="1" required value="${value || ""}">
                        <p class="description">${nop_rules_data.i18n.min_items_desc || "Minimum number of items required"}</p>
                    </div>
                `);
                break;

            // Add more condition types as needed
        }
    }

    // Update progress bar highlighting
    function updateProgressBar(category) {
        // Implementation depends on if you have a progress bar UI
    }

    // Check URL parameters for any specific actions
    function checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get("action");
        const ruleId = urlParams.get("rule_id");

        if (action === "edit" && ruleId) {
            // Find the rule data
            $rulesTable
                .find(`tr[data-rule-id="${ruleId}"] .nop-edit-rule`)
                .trigger("click");
        } else if (action === "add") {
            openAddRuleModal();
        }
    }

    // Save rule
    function saveRule(e) {
        e.preventDefault();

        // Get form data
        const ruleId = $("#rule_id").val();
        const formData = {
            id: ruleId ? Number.parseInt(ruleId, 10) : 0,
            name: $("#rule_name").val(),
            description: $("#rule_description").val(),
            category: $("#rule_category").val(),
            priority: Number.parseInt($("#rule_priority").val(), 10),
            active: $("#rule_active").prop("checked"),
            condition_type: $("#condition_type").val() || $("#rule_category").val(),
            condition_value: $("#condition_value").val(),
            condition_settings: {}, // Add any additional settings here
            action_type: $("#action_type").val(),
            action_value: $("#action_value").val(),
            action_settings: {}, // Add any additional settings here
        };

        // Send AJAX request to save rule
        $.ajax({
            url: nop_rules_data.ajax_url,
            type: "POST",
            data: {
                action: `${nop_rules_data.prefix}save_rule`,
                nonce: nop_rules_data.nonce,
                rule_data: JSON.stringify(formData),
            },
            success: (response) => {
                if (response.success) {
                    showNotice(nop_rules_data.i18n.save_success, "success");
                    closeModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotice(
                        response.data.message || nop_rules_data.i18n.error,
                        "error",
                    );
                }
            },
            error: () => {
                showNotice(nop_rules_data.i18n.error, "error");
            },
        });
    }

    // Confirm and delete a rule
    function confirmDeleteRule(ruleId) {
        if (confirm(nop_rules_data.i18n.confirm_delete)) {
            deleteRule(ruleId);
        }
    }

    // Delete a rule via AJAX
    function deleteRule(ruleId) {
        $.ajax({
            url: nop_rules_data.ajax_url,
            type: "POST",
            data: {
                action: `${nop_rules_data.prefix}delete_rule`,
                nonce: nop_rules_data.nonce,
                rule_id: ruleId,
            },
            success: (response) => {
                if (response.success) {
                    showNotice(nop_rules_data.i18n.delete_success, "success");
                    $rulesTable.find(`tr[data-rule-id="${ruleId}"]`).fadeOut(function () {
                        $(this).remove();
                    });
                } else {
                    showNotice(
                        response.data.message || nop_rules_data.i18n.error,
                        "error",
                    );
                }
            },
            error: () => {
                showNotice(nop_rules_data.i18n.error, "error");
            },
        });
    }

    // Toggle rule active state
    function toggleRuleActive(ruleId, isActive) {
        $.ajax({
            url: nop_rules_data.ajax_url,
            type: "POST",
            data: {
                action: `${nop_rules_data.prefix}toggle_rule`,
                nonce: nop_rules_data.nonce,
                rule_id: ruleId,
                active: isActive ? 1 : 0,
            },
            success: (response) => {
                if (response.success) {
                    // Update UI
                    const category = response.data.category || "";

                    if (isActive && category) {
                        // If we activated a rule with a category, highlight that category
                        highlightActiveCategory(category);

                        // Reload page after a short delay to update all rule states
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    showNotice(
                        response.data.message || nop_rules_data.i18n.error,
                        "error",
                    );
                }
            },
            error: () => {
                showNotice(nop_rules_data.i18n.error, "error");
            },
        });
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
        }

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
