<?php

namespace qtype_questionpy\form;

interface renderable
{
    public function render_to(render_context $context): void;
}
