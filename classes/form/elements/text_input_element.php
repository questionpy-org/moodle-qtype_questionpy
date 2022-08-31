<?php
namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\render_context;

class text_input_element extends form_element {
    public string $name;
    public string $label;
    public bool $required = false;
    public ?string $default = null;
    public ?string $placeholder = null;

    public function __construct(
        string  $name,
        string  $label,
        bool    $required = false,
        ?string $default = null,
        ?string $placeholder = null
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->required = $required;
        $this->default = $default;
        $this->placeholder = $placeholder;
    }

    public static function from_array(array $array): self {
        return new self(
            $array["name"],
            $array["label"],
            $array["required"] ?? false,
            $array["default"] ?? null,
            $array["placeholder"] ?? null,
        );
    }

    protected static function kind(): string {
        return "text_input";
    }

    public function render_to(render_context $context): void {
        $attributes = [
            "required" => $this->required
        ];
        if ($this->default) {
            $attributes["value"] = $this->default;
        }
        if ($this->placeholder) {
            $attributes["placeholder"] = $this->placeholder;
        }

        $context->add_element("text", $this->name, $this->label, $attributes);
        $context->set_type($this->name, PARAM_TEXT);
    }
}
