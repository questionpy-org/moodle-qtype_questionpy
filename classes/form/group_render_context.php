<?php

namespace qtype_questionpy\form;

class group_render_context extends render_context
{
    public array $elements = [];

    private render_context $root;

    public function __construct(render_context $root)
    {
        parent::__construct($root->moodleform, $root->moodle_quick_form);
        $this->root = $root;
    }

    function add_element(string $type, string $name, ...$args): object
    {
        $element = $this->root->moodle_quick_form->createElement($type, $name, ...$args);
        $this->elements[] = $element;
        return $element;
    }

    function set_type(string $name, string $type): void
    {
        $this->root->set_type($name, $type);
    }

    function next_unique_int(): int
    {
        return $this->root->next_unique_int();
    }

    function add_checkbox_controller(int $group_id): void
    {
        $this->root->add_checkbox_controller($group_id);
    }
}
