<?php

namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\render_context;

class checkbox_element extends form_element
{
    public string $name;
    public ?string $left_label = null;
    public ?string $right_label = null;
    public bool $required = false;
    public bool $selected = false;

    public function __construct(string $name, ?string $left_label = null, ?string $right_label = null, bool $required = false, bool $selected = false)
    {
        $this->name = $name;
        $this->left_label = $left_label;
        $this->right_label = $right_label;
        $this->required = $required;
        $this->selected = $selected;
    }

    public static function from_array(array $array): self
    {
        return new self(
            $array["name"],
            $array["left_label"] ?? null,
            $array["right_label"] ?? null,
            $array["required"] ?? false,
            $array["selected"] ?? false,
        );
    }

    protected static function kind(): string
    {
        return "checkbox";
    }

    public function render_to(render_context $context, ?int $group = null): void
    {
        $attributes = [
            "checked" => $this->selected,
            "required" => $this->required
        ];

        if ($group) {
            $attributes["group"] = $group;
        }

        $context->add_element(
            "advcheckbox", $this->name, $this->left_label, $this->right_label, $attributes
        );
    }
}
