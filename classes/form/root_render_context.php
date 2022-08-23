<?php

namespace qtype_questionpy\form;

class root_render_context extends render_context
{
    private int $next_unique_int = 1;

    function add_element(string $type, string $name, ...$args): object
    {
        return $this->moodle_quick_form->addElement($type, $name, ...$args);
    }

    function set_type(string $name, string $type): void
    {
        $this->moodle_quick_form->setType($name, $type);
    }

    function next_unique_int(): int
    {
        return $this->next_unique_int++;
    }

    function add_checkbox_controller(int $group_id): void
    {
        $this->moodleform->add_checkbox_controller($group_id);
    }
}
