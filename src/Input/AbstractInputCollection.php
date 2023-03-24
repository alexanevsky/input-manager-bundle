<?php

namespace Alexanevsky\InputManagerBundle\Input;

abstract class AbstractInputCollection implements InputCollectionInterface, \IteratorAggregate, \Countable
{
    /**
     * @var InputInterface[]
     */
    protected array $inputs = [];

    public function add(InputInterface $input): self
    {
        $this->inputs[] = $input;

        return $this;
    }

    /**
     * @var InputInterface[]
     */
    public function all(): array
    {
        return $this->inputs;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->inputs);
    }

    public function count(): int
    {
        return count($this->inputs);
    }
}