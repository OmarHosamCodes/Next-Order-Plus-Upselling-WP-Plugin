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

    // Initialize the admin UI
    function init() {
        // Initialize Select2 for product selection
        initSelect2();

        // Bind event handlers
        bindEvents();

        // Check for URL parameters
        checkUrlParams();
    }

    // Initialize Select2 for product dropdowns
    function initSelect2() {
        $(".nop-product-select").select2({
            width: "100%",
            placeholder: nop_rules_data.i18n.select_product,
            data: nop_rules_data.products,
        });
    }

    // Bind event handlers
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
                    showNotice("Error parsing rule data. Please refresh the page and try again.", "error");
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
        $rulesTable.on("click", ".nop-toggle-rule", function () {
            const ruleId = $(this).data("rule-id");
            const isActive = $(this).data("active") === 1;
            toggleRuleActive(ruleId, isActive);
        });

        // Close modal
        $(".nop-modal-close, .nop-cancel-rule").on("click", closeModal);

        // Submit rule form
        $ruleForm.on("submit", saveRule);

        // Condition type change
        $("#condition_type").on("change", updateConditionFields);

        // Action type change
        $("#action_type").on("change", updateActionFields);

        // Close modal when clicking outside of content
        $(window).on("click", (event) => {
            if ($(event.target).is($modal)) {
                closeModal();
            }
        });
    }

    // Check URL parameters for notifications
    function checkUrlParams() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has("updated")) {
            showNotice(nop_rules_data.i18n.save_success, "success");
        }
    }

    // Open modal for adding a new rule
    function openAddRuleModal() {
        resetForm();
        $modalTitle.text(nop_rules_data.i18n.add_rule);
        $modal.show();
    }

    // Open modal for editing an existing rule
    function openEditRuleModal(ruleData) {
        resetForm();
        $modalTitle.text(nop_rules_data.i18n.edit_rule);

        // Populate form with rule data
        $("#rule_id").val(ruleData.id);
        $("#rule_name").val(ruleData.name);
        $("#rule_description").val(ruleData.description);
        $("#rule_priority").val(ruleData.priority);
        $("#rule_active").prop("checked", ruleData.active);

        // Set condition type and update fields
        $("#condition_type").val(ruleData.condition_type).trigger("change");

        // Set action type and update fields
        $("#action_type").val(ruleData.action_type).trigger("change");

        // Set exclusive flag
        $("#action_exclusive").prop("checked", ruleData.action_params.exclusive);

        // Set condition values after fields are generated
        setTimeout(() => {
            populateConditionValues(ruleData);
            populateActionValues(ruleData);
        }, 100);

        $modal.show();
    }

    // Reset form to default state
    function resetForm() {
        $ruleForm[0].reset();
        $("#rule_id").val("");
        $conditionFields.empty();
        $actionFields.empty();
    }

    // Close the modal
    function closeModal() {
        $modal.hide();
    }

    // Update condition fields based on selected condition type
    function updateConditionFields() {
        const conditionType = $("#condition_type").val();
        $conditionFields.empty();

        if (!conditionType) {
            return;
        }

        let html = "";

        switch (conditionType) {
            case "cart_total":
                html = `
                    <div class="nop-form-group">
                        <label for="condition_value">${nop_rules_data.i18n.min_amount}</label>
                        <div class="nop-input-group">
                            <span class="nop-input-addon">${nop_rules_data.currency_symbol}</span>
                            <input type="number" id="condition_value" name="condition_value" step="0.01" min="0" required>
                        </div>
                        <p class="description">${nop_rules_data.i18n.min_amount_desc}</p>
                    </div>
                `;
                break;

            case "item_count":
                html = `
                    <div class="nop-form-group">
                        <label for="condition_value">${nop_rules_data.i18n.min_items}</label>
                        <input type="number" id="condition_value" name="condition_value" min="1" required>
                        <p class="description">${nop_rules_data.i18n.min_items_desc}</p>
                    </div>
                `;
                break;

            case "specific_product":
                html = `
                    <div class="nop-form-group">
                        <label for="product_id">${nop_rules_data.i18n.select_product}</label>
                        <select id="product_id" name="product_id" class="nop-product-select" required>
                            <option value="">${nop_rules_data.i18n.select_product}</option>
                        </select>
                    </div>
                    <div class="nop-form-group">
                        <label for="condition_value">${nop_rules_data.i18n.min_spend}</label>
                        <div class="nop-input-group">
                            <span class="nop-input-addon">${nop_rules_data.currency_symbol}</span>
                            <input type="number" id="condition_value" name="condition_value" step="0.01" min="0" required>
                        </div>
                        <p class="description">${nop_rules_data.i18n.product_total_desc}</p>
                    </div>
                `;
                break;
        }

        $conditionFields.html(html);

        // Initialize Select2 for product dropdowns
        $(".nop-product-select").select2({
            width: "100%",
            placeholder: nop_rules_data.i18n.select_product,
            data: nop_rules_data.products,
        });
    }

    // Update action fields based on selected action type
    function updateActionFields() {
        const actionType = $("#action_type").val();
        $actionFields.empty();

        if (!actionType) {
            return;
        }

        let html = "";

        switch (actionType) {
            case "percentage_discount":
                html = `
                    <div class="nop-form-group">
                        <label for="action_value">${nop_rules_data.i18n.discount_percentage}</label>
                        <div class="nop-input-group">
                            <input type="number" id="action_value" name="action_value" min="0" max="100" step="0.01" required>
                            <span class="nop-input-addon">%</span>
                        </div>
                        <p class="description">${nop_rules_data.i18n.percentage_discount_desc}</p>
                    </div>
                `;
                break;

            case "fixed_discount":
                html = `
                    <div class="nop-form-group">
                        <label for="action_value">${nop_rules_data.i18n.discount_amount}</label>
                        <div class="nop-input-group">
                            <span class="nop-input-addon">${nop_rules_data.currency_symbol}</span>
                            <input type="number" id="action_value" name="action_value" min="0" step="0.01" required>
                        </div>
                        <p class="description">${nop_rules_data.i18n.fixed_discount_desc}</p>
                    </div>
                `;
                break;

            case "free_shipping":
                html = `
                    <div class="nop-form-group">
                        <p class="description">${nop_rules_data.i18n.free_shipping_desc}</p>
                    </div>
                `;
                break;

            case "cheapest_free":
                html = `
                    <div class="nop-form-group">
                        <p class="description">${nop_rules_data.i18n.cheapest_free_desc}</p>
                    </div>
                `;
                break;

            case "most_expensive_free":
                html = `
                    <div class="nop-form-group">
                        <p class="description">${nop_rules_data.i18n.most_expensive_free_desc}</p>
                    </div>
                `;
                break;

            case "nth_cheapest_free":
                html = `
                    <div class="nop-form-group">
                        <label for="n">${nop_rules_data.i18n.position}</label>
                        <input type="number" id="n" name="n" min="1" value="1" required>
                        <p class="description">${nop_rules_data.i18n.nth_cheapest_free_desc}</p>
                    </div>
                `;
                break;

            case "nth_expensive_free":
                html = `
                    <div class="nop-form-group">
                        <label for="n">${nop_rules_data.i18n.position}</label>
                        <input type="number" id="n" name="n" min="1" value="1" required>
                        <p class="description">${nop_rules_data.i18n.nth_expensive_free_desc}</p>
                    </div>
                `;
                break;
        }

        $actionFields.html(html);
    }

    // Populate condition fields with values from rule data
    function populateConditionValues(ruleData) {
        const conditionType = ruleData.condition_type;

        switch (conditionType) {
            case "cart_total":
            case "item_count":
                $("#condition_value").val(ruleData.condition_value);
                break;

            case "specific_product": {
                const productOption = new Option(
                    getProductNameById(ruleData.condition_value),
                    ruleData.condition_value,
                    true,
                    true,
                );
                $("#product_id").append(productOption).trigger("change");
                break;
            }

            case "product_count":
            case "product_total":
                $("#condition_value").val(ruleData.condition_value);
                if (ruleData.condition_params?.product_id) {
                    const productOption = new Option(
                        getProductNameById(ruleData.condition_params.product_id),
                        ruleData.condition_params.product_id,
                        true,
                        true,
                    );
                    $("#product_id").append(productOption).trigger("change");
                }
                break;
        }
    }

    // Populate action fields with values from rule data
    function populateActionValues(ruleData) {
        const actionType = ruleData.action_type;

        switch (actionType) {
            case "percentage_discount":
            case "fixed_discount":
                $("#action_value").val(ruleData.action_value);
                break;

            case "nth_cheapest_free":
            case "nth_expensive_free":
                if (ruleData.action_params?.n) {
                    $("#n").val(ruleData.action_params.n);
                }
                break;
        }
    }

    // Get product name by ID
    function getProductNameById(productId) {
        for (const product of nop_rules_data.products) {
            if (product.id === productId) {
                return product.text;
            }
        }
        return `Product #${productId}`;
    }
    /**
     * Save rule with correct action prefix
     */
    function saveRule(e) {
        e.preventDefault();

        // Debug output to console
        console.log("Form submission started");

        // Check if form exists before accessing [0]
        if (!$ruleForm || $ruleForm.length === 0) {
            console.error("Form not found");
            showNotice(
                "Form not found. Please reload the page and try again.",
                "error",
            );
            return;
        }

        // Log what prefix we're using
        console.log("nop_rules_data object:", nop_rules_data);
        console.log("Prefix value:", nop_rules_data.prefix);

        // IMPORTANT: Hard-code the correct prefix if it's undefined
        const actionPrefix = nop_rules_data.prefix || "nop_";
        console.log("Using action prefix:", actionPrefix);

        // Create structured rule data object from form with debugging
        console.log("Building rule data from form elements");
        const $form = $ruleForm;

        // Log all form elements to identify what's available
        console.log("All form elements:", $form.serializeArray());

        // Build rule data object
        const ruleData = {
            id: $("#rule_id").val() || "",
            name: $("#rule_name").val() || "",
            description: $("#rule_description").val() || "",
            priority: $("#rule_priority").val() || "10",
            condition_type: $("#condition_type").val() || "",
            action_type: $("#action_type").val() || "",
            active: $("#rule_active").is(":checked"),
            condition_value: $("#condition_value").val() || "",
            action_value: $("#action_value").val() || "",
            condition_settings: {},
            action_settings: {},
        };

        console.log("Base rule data:", ruleData);

        // Add condition-specific fields
        console.log(
            "Condition fields found:",
            $("#condition_fields").find("input, select").length,
        );
        $("#condition_fields input, #condition_fields select").each(function () {
            const name = $(this).attr("name");
            const value = $(this).val();
            console.log(`Adding condition field: ${name} = ${value}`);
            ruleData.condition_settings[name] = value;
        });

        // Add action-specific fields
        console.log(
            "Action fields found:",
            $("#action_fields").find("input, select").length,
        );
        $("#action_fields input, #action_fields select").each(function () {
            const name = $(this).attr("name");
            const value = $(this).val();
            console.log(`Adding action field: ${name} = ${value}`);
            ruleData.action_settings[name] = value;
        });

        // Add exclusive setting
        ruleData.action_settings.exclusive = $("#action_exclusive").is(":checked");
        console.log("Exclusive setting:", ruleData.action_settings.exclusive);

        // Debug output complete rule data
        console.log("Final rule data object:", ruleData);

        // Create form data for AJAX request
        const formData = new FormData();

        // Use the correct action name - this is the key fix
        formData.append("action", `${actionPrefix}save_rule`);
        formData.append("nonce", nop_rules_data.nonce);

        // Convert rule data to JSON string
        const ruleDataJSON = JSON.stringify(ruleData);
        console.log("Rule data JSON:", ruleDataJSON);
        formData.append("rule_data", ruleDataJSON);

        // Create a direct object for debugging in the Network tab
        const directPostData = {
            action: `${actionPrefix}save_rule`,
            nonce: nop_rules_data.nonce,
            rule_data: ruleDataJSON,
        };

        console.log("AJAX request about to be sent with data:", directPostData);

        // Add a timestamp to avoid caching
        formData.append("_", new Date().getTime());

        $.ajax({
            url: nop_rules_data.ajax_url,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: (xhr) => {
                console.log("AJAX request starting");
                $ruleForm
                    .find('button[type="submit"]')
                    .prop("disabled", true)
                    .text("Saving...");
            },
            success: (response) => {
                console.log("AJAX response received:", response);
                if (response.success) {
                    console.log("Save successful, rule ID:", response.data.rule?.id);
                    // Show success message
                    showNotice(response.data.message, "success");

                    // Close modal
                    closeModal();

                    // Refresh the page to see new rule
                    window.location.reload();
                } else {
                    console.error("Save failed:", response.data.message);
                    showNotice(response.data.message || "Save failed", "error");
                    $ruleForm
                        .find('button[type="submit"]')
                        .prop("disabled", false)
                        .text("Save Rule");
                }
            },
            error: (xhr, status, error) => {
                console.error("AJAX error:", { xhr, status, error });
                console.error("Response text:", xhr.responseText);
                showNotice(`Error saving rule: ${error}`, "error");
                $ruleForm
                    .find('button[type="submit"]')
                    .prop("disabled", false)
                    .text("Save Rule");
            },
            complete: () => {
                console.log("AJAX request completed");
            },
        });
    }
    /**
     * Update a rule in the table without reloading the page
     */
    function updateRuleInTable(rule) {
        const ruleId = rule.id;
        const $row = $(`tr[data-rule-id="${ruleId}"]`);

        if ($row.length) {
            // Update existing row
            $row.find("td:nth-child(1)").text(rule.priority);
            $row.find("td:nth-child(2)").text(rule.name);
            $row.find("td:nth-child(3)").text(rule.description);
            $row.find("td:nth-child(4)").text(getConditionLabel(rule.condition_type));
            $row.find("td:nth-child(5)").text(getActionLabel(rule.action_type));

            // Update status toggle
            $row.find(".nop-rule-status").prop("checked", rule.active);
        } else {
            // Add new row
            const $tbody = $rulesTable.find("tbody");
            const $emptyRow = $tbody.find("tr td[colspan]").parent();

            if ($emptyRow.length) {
                // Remove "no rules" message
                $emptyRow.remove();
            }

            // Create new row HTML
            const newRow = `
            <tr data-rule-id="${rule.id}">
                <td>${rule.priority}</td>
                <td>${rule.name}</td>
                <td>${rule.description}</td>
                <td>${getConditionLabel(rule.condition_type)}</td>
                <td>${getActionLabel(rule.action_type)}</td>
                <td>
                    <div class="nop-status-toggle">
                        <label class="nop-switch">
                            <input type="checkbox" class="nop-rule-status" ${rule.active ? "checked" : ""}>
                            <span class="nop-slider"></span>
                        </label>
                    </div>
                </td>
                <td>
                    <button type="button" class="button nop-edit-rule">${nop_rules_data.i18n.edit}</button>
                    <button type="button" class="button nop-delete-rule">${nop_rules_data.i18n.delete}</button>
                </td>
            </tr>
        `;

            $tbody.append(newRow);
        }
    }

    /**
     * Get condition label from condition type
     */
    function getConditionLabel(type) {
        return nop_rules_data.condition_types[type] || type;
    }

    /**
     * Get action label from action type
     */
    function getActionLabel(type) {
        return nop_rules_data.action_types[type] || type;
    }

    // Confirm and delete rule
    function confirmDeleteRule(ruleId) {
        if (confirm(nop_rules_data.i18n.confirm_delete)) {
            deleteRule(ruleId);
        }
    }

    // Delete rule
    function deleteRule(ruleId) {
        // Ensure ruleId is a valid number
        let internalRuleId = ruleId;
        if (!internalRuleId || Number.isNaN(Number.parseInt(internalRuleId))) {
            console.error("Invalid rule ID:", internalRuleId);
            showNotice(
                "Invalid rule ID. Please refresh the page and try again.",
                "error",
            );
            return;
        }


        // Convert to integer to ensure it's handled properly
        internalRuleId = Number.parseInt(internalRuleId);
        console.log("Deleting rule ID:", internalRuleId);

        $.ajax({
            url: nop_rules_data.ajax_url,
            type: "POST",
            data: {
                action: `${nop_rules_data.prefix}delete_rule`,
                nonce: nop_rules_data.nonce,
                rule_id: internalRuleId,
            },
            beforeSend: () => {
                $(`tr[data-rule-id="${internalRuleId}"]`).addClass("nop-deleting");
            },
            success: (response) => {
                console.log("Delete response:", response);
                if (response.success) {
                    $(`tr[data-rule-id="${internalRuleId}"]`).fadeOut(400, function () {
                        $(this).remove();
                        showNotice(response.data.message, "success");
                        if ($rulesTable.find("tbody tr").length === 0) {
                            $rulesTable.find("tbody").html(`
                            <tr>
                                <td colspan="7">${nop_rules_data.i18n.no_rules}</td>
                            </tr>
                        `);
                        }
                    });
                } else {
                    $(`tr[data-rule-id="${internalRuleId}"]`).removeClass("nop-deleting");
                    showNotice(response.data.message, "error");
                }
            },
            error: (xhr, status, error) => {
                console.error("AJAX error:", { xhr, status, error });
                console.error("Response text:", xhr.responseText);
                $(`tr[data-rule-id="${internalRuleId}"]`).removeClass("nop-deleting");
                showNotice(`Error deleting rule: ${error}`, "error");
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
            },
            beforeSend: () => {
                $(`tr[data-rule-id="${ruleId}"] .nop-toggle-rule`).prop(
                    "disabled",
                    true,
                );
            },
            success: (response) => {
                if (response.success) {
                    const $row = $(`tr[data-rule-id="${ruleId}"]`);
                    const $statusCell = $row.find(".nop-rule-status");
                    const $toggleBtn = $row.find(".nop-toggle-rule");

                    // Update status cell
                    $statusCell
                        .removeClass("active inactive")
                        .addClass(response.data.active ? "active" : "inactive");
                    $statusCell.text(
                        response.data.active
                            ? nop_rules_data.i18n.active
                            : nop_rules_data.i18n.inactive,
                    );

                    // Update button
                    $toggleBtn.text(
                        response.data.active
                            ? nop_rules_data.i18n.deactivate
                            : nop_rules_data.i18n.activate,
                    );
                    $toggleBtn.data("active", response.data.active ? 1 : 0);

                    showNotice(response.data.message, "success");
                } else {
                    showNotice(response.data.message, "error");
                }
                $(`tr[data-rule-id="${ruleId}"] .nop-toggle-rule`).prop(
                    "disabled",
                    false,
                );
            },
            error: () => {
                showNotice(nop_rules_data.i18n.error, "error");
                $(`tr[data-rule-id="${ruleId}"] .nop-toggle-rule`).prop(
                    "disabled",
                    false,
                );
            },
        });
    }

    // Show notification
    function showNotice(message, type) {
        $notice
            .removeClass("hidden success error")
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
