<?php

namespace Alexanevsky\InputManagerBundle\Input;

abstract class AbstractInput implements InputModifiableInterface
{
    public function modify(): void
    {
    }
}
