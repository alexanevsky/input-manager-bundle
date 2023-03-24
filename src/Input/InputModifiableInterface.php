<?php

namespace Alexanevsky\InputManagerBundle\Input;

interface InputModifiableInterface extends InputInterface
{
    public function modify(): void;
}
