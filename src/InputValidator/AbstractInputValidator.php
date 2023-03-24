<?php

namespace Alexanevsky\InputManagerBundle\InputValidator;

use Alexanevsky\InputManagerBundle\Exception\MissedRequiredPayloadException;
use Alexanevsky\InputManagerBundle\Input\InputInterface;
use Alexanevsky\InputManagerBundle\InputValidator\Attribute\SetFromPayload;
use function Symfony\Component\String\u;

abstract class AbstractInputValidator implements InputValidatorInterface
{
    private InputInterface $input;

    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function setPayload(array $payload): void
    {
        $reflProps = (new \ReflectionClass(static::class))->getProperties();
        $propsFromPayload = [];
        $requiredProps = [];

        foreach ($reflProps as $reflProp) {
            /** @var SetFromPayload|null $setPropFromPayload */
            if ($setPropFromPayload = (array_values($reflProp->getAttributes(SetFromPayload::class) ?? [])[0] ?? null)?->newInstance()) {
                $propsFromPayload[] = $reflProp->getName();

                if ($setPropFromPayload->required) {
                    $requiredProps[] = $reflProp->getName();
                }
            }
        }

        foreach ($propsFromPayload as $propName) {
            $propNameSnake = u($propName)->snake()->toString();

            if (!empty($payload[$propNameSnake])) {
                $this->$propName = $payload[$propNameSnake];
            } elseif (!empty($payload[$propName])) {
                $this->$propName = $payload[$propName];
            } elseif (in_array($propName, $requiredProps)) {
                throw new MissedRequiredPayloadException(sprintf('Required property "%s" is missed', $propName));
            }
        }
    }

    public function validate(): array
    {
        return [];
    }
}
