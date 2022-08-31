<?php
namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\render_context;

class static_text_element extends form_element {
    public string $label;
    public string $text;

    public function __construct(string $label, string $text) {
        $this->label = $label;
        $this->text = $text;
    }

    protected static function kind(): string {
        return "static_text";
    }

    public static function from_array(array $array): self {
        return new self($array["label"], $array["text"]);
    }

    public function render_to(render_context $context): void {
        $context->add_element("static", "qpy_static_text_" . $context->next_unique_int(), $this->label, $this->text);
    }
}
