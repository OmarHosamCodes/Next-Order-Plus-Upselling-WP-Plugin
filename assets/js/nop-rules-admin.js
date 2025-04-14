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

        // Edit rule button
        $rulesTable.on("click", ".nop-edit-rule", function () {
            const ruleId = $(this).data("rule-id");
            const ruleData = JSON.parse(
                $(this).closest("tr").find(".nop-rule-data").val(),
            );
            openEditRuleModal(ruleData);
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

    // Save rule
    function saveRule(e) {
        e.preventDefault();

        const formData = new FormData($ruleForm[0]);
        formData.append("action", `${nop_rules_data.prefix}save_rule`);
        formData.append("nonce", nop_rules_data.nonce);

        $.ajax({
            url: nop_rules_data.ajax_url,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: () => {
                $ruleForm
                    .find('button[type="submit"]')
                    .prop("disabled", true)
                    .text(nop_rules_data.i18n.saving);
            },
            success: (response) => {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    showNotice(response.data.message, "error");
                    $ruleForm
                        .find('button[type="submit"]')
                        .prop("disabled", false)
                        .text(nop_rules_data.i18n.save_rule);
                }
            },
            error: () => {
                showNotice(nop_rules_data.i18n.error, "error");
                $ruleForm
                    .find('button[type="submit"]')
                    .prop("disabled", false)
                    .text(nop_rules_data.i18n.save_rule);
            },
        });
    }

    // Confirm and delete rule
    function confirmDeleteRule(ruleId) {
        if (confirm(nop_rules_data.i18n.confirm_delete)) {
            deleteRule(ruleId);
        }
    }

    // Delete rule
    function deleteRule(ruleId) {
        $.ajax({
            url: nop_rules_data.ajax_url,
            type: "POST",
            data: {
                action: `${nop_rules_data.prefix}delete_rule`,
                nonce: nop_rules_data.nonce,
                rule_id: ruleId,
            },
            beforeSend: () => {
                $(`tr[data-rule-id="${ruleId}"]`).addClass("nop-deleting");
            },
            success: (response) => {
                if (response.success) {
                    $(`tr[data-rule-id="${ruleId}"]`).fadeOut(400, function () {
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
                    $(`tr[data-rule-id="${ruleId}"]`).removeClass("nop-deleting");
                    showNotice(response.data.message, "error");
                }
            },
            error: () => {
                $(`tr[data-rule-id="${ruleId}"]`).removeClass("nop-deleting");
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
