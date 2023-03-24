<?php

namespace Alexanevsky\InputManagerBundle;

use Alexanevsky\InputManagerBundle\Input\InputInterface;
use Symfony\Component\Translation\TranslatableMessage;

class InputManager
{
    public function __construct(
        private Deserializer    $deserializer,
        private Mapper          $mapper,
        private Validator       $validator
    ) {
    }

    /**
     * @param InputInterface|class-string<InputInterface> $input
     */
    public function deserialize(string|array|object $source, InputInterface|string $input): InputInterface
    {
        return $this->deserializer->deserialize($source, $input);
    }

    /**
     * @param InputInterface|class-string<InputInterface> $input
     */
    public function mapInputToObject(InputInterface $input, object|string $object): object
    {
        return $this->mapper->mapInputToObject($input, $object);
    }

    /**
     * @param class-string<InputValidatorInterface>|null $extendedValidatorClass
     *
     * @return TranslatableMessage[]
     */
    public function validate(InputInterface $input, ?string $extendedValidatorClass = null, array $extendedValidatorPayload = []): array
    {
        return $this->validator->validate($input, $extendedValidatorClass, $extendedValidatorPayload);
    }
}
