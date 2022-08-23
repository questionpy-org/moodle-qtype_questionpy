<?php

namespace qtype_questionpy\form;

/**
 * Abstracts away the differences in rendering elements in a group (where the element is created, and added as part of
 * the group element) and outside of a group (where the element is added directly) while still allowing for checkbox
 * controllers, which use an entirely different method.
 */
abstract class render_context
{
    public \moodleform $moodleform;
    public \MoodleQuickForm $moodle_quick_form;

    public function __construct(\moodleform $moodleform, \MoodleQuickForm $moodle_quick_form)
    {
        $this->moodleform = $moodleform;
        $this->moodle_quick_form = $moodle_quick_form;
    }

    abstract function add_element(string $type, string $name, ...$args): object;

    abstract function set_type(string $name, string $type): void;

    abstract function next_unique_int(): int;

    abstract function add_checkbox_controller(int $group_id): void;
}
