<?php

namespace Alexanevsky\InputManagerBundle\InputValidator;

use Alexanevsky\InputManagerBundle\Input\InputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Translation\TranslatableMessage;

#[AutoconfigureTag('alexanevsky.input_manager.input_validator')]
interface InputValidatorInterface
{
    public function setInput(InputInterface $input): void;

    public function getInput(): InputInterface;

    public function setPayload(array $payload): void;

    /**
     * @return TranslatableMessage[]
     */
    public function validate(): array;
}
