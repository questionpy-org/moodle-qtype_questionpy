<?php
namespace qtype_questionpy\form;

use qtype_questionpy\form\elements\form_elements;

class qpy_form implements renderable {
    public form_elements $general;
    public form_sections $sections;

    public function __construct(?form_elements $general = null, ?form_sections $sections = null) {
        $this->general = $general ?? new form_elements();
        $this->sections = $sections ?? new form_sections();
    }

    public static function from_array(array $array): self {
        return new self(
            form_elements::from_array($array["general"]),
            form_sections::from_array($array["sections"]),
        );
    }

    public function render_to($context): void {
        foreach ($this->general as $element) {
            $element->render_to($context);
        }

        foreach ($this->sections as $section) {
            $section->render_to($context);
        }
    }
}
