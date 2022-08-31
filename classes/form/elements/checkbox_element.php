<?php
namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\render_context;

class checkbox_element extends form_element {
    public string $name;
    public ?string $leftlabel = null;
    public ?string $rightlabel = null;
    public bool $required = false;
    public bool $selected = false;

    public function __construct(string $name, ?string $leftlabel = null, ?string $rightlabel = null,
                                bool   $required = false, bool $selected = false) {
        $this->name = $name;
        $this->leftlabel = $leftlabel;
        $this->rightlabel = $rightlabel;
        $this->required = $required;
        $this->selected = $selected;
    }

    public static function from_array(array $array): self {
        return new self(
            $array["name"],
            $array["left_label"] ?? null,
            $array["right_label"] ?? null,
            $array["required"] ?? false,
            $array["selected"] ?? false,
        );
    }

    public function to_array(): array {
        return [
            "name" => $this->name,
            "left_label" => $this->leftlabel,
            "right_label" => $this->rightlabel,
            "required" => $this->required,
            "selected" => $this->selected,
        ];
    }

    protected static function kind(): string {
        return "checkbox";
    }

    public function render_to(render_context $context, ?int $group = null): void {
        $attributes = [
            "checked" => $this->selected,
            "required" => $this->required
        ];

        if ($group) {
            $attributes["group"] = $group;
        }

        $context->add_element(
            "advcheckbox", $this->name, $this->leftlabel, $this->rightlabel, $attributes
        );
    }
}
