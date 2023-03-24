<?php

namespace Alexanevsky\InputManagerBundle\Input;

interface InputCollectionInterface
{
    /**
     * @return class-string<InputInterface>
     */
    public function getClass(): string;

    public function add(InputInterface $input): self;

    /**
     * @return InputInterface[]
     */
    public function all(): array;
}
