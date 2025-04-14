<?php
/**
 * Rule Model
 *
 * Represents a single upsell rule with conditions and actions
 *
 * @package NextOrderPlus
 * @since 1.1.0
 */

namespace NOP\Models;

/**
 * Rule model class
 */
class NOP_Rule
{
    /**
     * Rule ID
     *
     * @var int
     */
    private $id;

    /**
     * Rule name
     *
     * @var string
     */
    private $name;

    /**
     * Rule description
     *
     * @var string
     */
    private $description;

    /**
     * Rule priority (lower number = higher priority)
     *
     * @var int
     */
    private $priority = 10;

    /**
     * Whether the rule is active
     *
     * @var bool
     */
    private $active = true;

    /**
     * Condition type
     * (cart_total, item_count, specific_product, product_count, product_total)
     *
     * @var string
     */
    private $condition_type;

    /**
     * Condition value
     *
     * @var mixed
     */
    private $condition_value;

    /**
     * Additional condition parameters
     *
     * @var array
     */
    private $condition_params = [];

    /**
     * Action type
     * (percentage_discount, fixed_discount, free_shipping, free_product)
     *
     * @var string
     */
    private $action_type;

    /**
     * Action value
     *
     * @var mixed
     */
    private $action_value;

    /**
     * Additional action parameters
     *
     * @var array
     */
    private $action_params = [];

    /**
     * Constructor
     *
     * @param array $data Rule data
     */
    public function __construct(array $data = [])
    {
        // Set properties from data
        if (!empty($data)) {
            $this->set_props($data);
        }
    }

    /**
     * Set rule properties from array
     *
     * @param array $data Rule data
     * @return void
     */
    public function set_props(array $data): void
    {
        if (isset($data['id'])) {
            $this->id = (int) $data['id'];
        }

        if (isset($data['name'])) {
            $this->name = sanitize_text_field($data['name']);
        }

        if (isset($data['description'])) {
            $this->description = sanitize_textarea_field($data['description']);
        }

        if (isset($data['priority'])) {
            $this->priority = (int) $data['priority'];
        }

        if (isset($data['active'])) {
            $this->active = (bool) $data['active'];
        }

        if (isset($data['condition_type'])) {
            $this->condition_type = sanitize_key($data['condition_type']);
        }

        if (isset($data['condition_value'])) {
            $this->condition_value = $data['condition_value'];
        }

        if (isset($data['condition_params']) && is_array($data['condition_params'])) {
            $this->condition_params = $data['condition_params'];
        }

        if (isset($data['action_type'])) {
            $this->action_type = sanitize_key($data['action_type']);
        }

        if (isset($data['action_value'])) {
            $this->action_value = $data['action_value'];
        }

        if (isset($data['action_params']) && is_array($data['action_params'])) {
            $this->action_params = $data['action_params'];
        }
    }

    /**
     * Get all rule data as array
     *
     * @return array Rule data
     */
    public function get_data(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'priority' => $this->priority,
            'active' => $this->active,
            'condition_type' => $this->condition_type,
            'condition_value' => $this->condition_value,
            'condition_params' => $this->condition_params,
            'action_type' => $this->action_type,
            'action_value' => $this->action_value,
            'action_params' => $this->action_params
        ];
    }

    /**
     * Get rule ID
     *
     * @return int Rule ID
     */
    public function get_id(): int
    {
        return $this->id;
    }

    /**
     * Set rule ID
     *
     * @param int $id Rule ID
     * @return void
     */
    public function set_id(int $id): void
    {
        $this->id = $id;
    }

    /**
     * Get rule name
     *
     * @return string Rule name
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Set rule name
     *
     * @param string $name Rule name
     * @return void
     */
    public function set_name(string $name): void
    {
        $this->name = sanitize_text_field($name);
    }

    /**
     * Get rule description
     *
     * @return string Rule description
     */
    public function get_description(): string
    {
        return $this->description;
    }

    /**
     * Set rule description
     *
     * @param string $description Rule description
     * @return void
     */
    public function set_description(string $description): void
    {
        $this->description = sanitize_textarea_field($description);
    }

    /**
     * Get rule priority
     *
     * @return int Rule priority
     */
    public function get_priority(): int
    {
        return $this->priority;
    }

    /**
     * Set rule priority
     *
     * @param int $priority Rule priority
     * @return void
     */
    public function set_priority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * Check if rule is active
     *
     * @return bool Whether rule is active
     */
    public function is_active(): bool
    {
        return $this->active;
    }

    /**
     * Set rule active state
     *
     * @param bool $active Whether rule is active
     * @return void
     */
    public function set_active(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * Get condition type
     *
     * @return string Condition type
     */
    public function get_condition_type(): string
    {
        return $this->condition_type;
    }

    /**
     * Set condition type
     *
     * @param string $type Condition type
     * @return void
     */
    public function set_condition_type(string $type): void
    {
        $this->condition_type = sanitize_key($type);
    }

    /**
     * Get condition value
     *
     * @return mixed Condition value
     */
    public function get_condition_value()
    {
        return $this->condition_value;
    }

    /**
     * Set condition value
     *
     * @param mixed $value Condition value
     * @return void
     */
    public function set_condition_value($value): void
    {
        $this->condition_value = $value;
    }

    /**
     * Get condition parameters
     *
     * @return array Condition parameters
     */
    public function get_condition_params(): array
    {
        return $this->condition_params;
    }

    /**
     * Set condition parameters
     *
     * @param array $params Condition parameters
     * @return void
     */
    public function set_condition_params(array $params): void
    {
        $this->condition_params = $params;
    }

    /**
     * Set condition data (alias for set_condition_params with proper name)
     *
     * @param array $data Condition data
     * @return void
     */
    public function set_condition_data(array $data): void
    {
        $this->condition_params = $data;
    }

    /**
     * Get condition data (alias for get_condition_params with proper name)
     *
     * @return array Condition data
     */
    public function get_condition_data(): array
    {
        return $this->condition_params;
    }

    /**
     * Set action data (alias for set_action_params with proper name)
     *
     * @param array $data Action data
     * @return void
     */
    public function set_action_data(array $data): void
    {
        $this->action_params = $data;
    }

    /**
     * Get action data (alias for get_action_params with proper name)
     *
     * @return array Action data
     */
    public function get_action_data(): array
    {
        return $this->action_params;
    }

    /**
     * Get all rule data as array (alias for get_data with simpler name)
     *
     * @return array Rule data
     */
    public function to_array(): array
    {
        return $this->get_data();
    }

    /**
     * Get action type
     *
     * @return string Action type
     */
    public function get_action_type(): string
    {
        return $this->action_type;
    }

    /**
     * Set action type
     *
     * @param string $type Action type
     * @return void
     */
    public function set_action_type(string $type): void
    {
        $this->action_type = sanitize_key($type);
    }

    /**
     * Get action value
     *
     * @return mixed Action value
     */
    public function get_action_value()
    {
        return $this->action_value;
    }

    /**
     * Set action value
     *
     * @param mixed $value Action value
     * @return void
     */
    public function set_action_value($value): void
    {
        $this->action_value = $value;
    }

    /**
     * Get action parameters
     *
     * @return array Action parameters
     */
    public function get_action_params(): array
    {
        return $this->action_params;
    }

    /**
     * Set action parameters
     *
     * @param array $params Action parameters
     * @return void
     */
    public function set_action_params(array $params): void
    {
        $this->action_params = $params;
    }

    /**
     * Save rule to database
     *
     * @return int Rule ID
     */
    public function save(): int
    {
        $rules = get_option('nop_upsell_rules', []);

        // Generate new ID if not set
        if (empty($this->id)) {
            $max_id = 0;
            foreach ($rules as $rule) {
                if ($rule['id'] > $max_id) {
                    $max_id = $rule['id'];
                }
            }
            $this->id = $max_id + 1;
        }

        // Update or add rule
        $data = $this->get_data();
        $rules[$this->id] = $data;

        update_option('nop_upsell_rules', $rules);

        return $this->id;
    }

    /**
     * Delete rule from database
     *
     * @return bool Success
     */
    public function delete(): bool
    {
        if (empty($this->id)) {
            return false;
        }

        $rules = get_option('nop_upsell_rules', []);

        if (isset($rules[$this->id])) {
            unset($rules[$this->id]);
            update_option('nop_upsell_rules', $rules);
            return true;
        }

        return false;
    }

    /**
     * Load rule from database by ID
     *
     * @param int $id Rule ID
     * @return bool Success
     */
    public function load(int $id): bool
    {
        $rules = get_option('nop_upsell_rules', []);

        if (isset($rules[$id])) {
            $this->set_props($rules[$id]);
            return true;
        }

        return false;
    }
}