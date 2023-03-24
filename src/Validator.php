<?php

namespace Alexanevsky\InputManagerBundle;

use Alexanevsky\GetterSetterAccessorBundle\GetterSetterAccessor;
use Alexanevsky\InputManagerBundle\Input\InputCollectionInterface;
use Alexanevsky\InputManagerBundle\Input\InputInterface;
use Alexanevsky\InputManagerBundle\InputValidator\InputValidatorInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceLocator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Validator
{
    public function __construct(
        #[TaggedLocator('alexanevsky.input_manager.input_validator')]
        private ServiceLocator $locator,

        private GetterSetterAccessor    $getterSetterAccessor,
        private ValidatorInterface      $validator
    ) {
    }

    /**
     * @param class-string<InputValidatorInterface>|null $extendedValidatorClass
     *
     * @return TranslatableMessage[]
     */
    public function validate(InputInterface $input, ?string $extendedValidatorClass = null, array $extendedValidatorPayload = []): array
    {
        $output = [];

        if ($extendedValidatorClass) {
            /** @var InputValidatorInterface */
            $extendedValidator = $this->locator->get($extendedValidatorClass);
            $extendedValidator->setInput($input);

            if ($extendedValidatorPayload) {
                $extendedValidator->setPayload($extendedValidatorPayload);
            }
        }

        /** @var ConstraintViolationInterface[] */
        $constraints = $this->validator->validate($input);

        foreach ($constraints as $constraint) {
            if (isset($output[$constraint->getPropertyPath()])) {
                continue;
            }

            $output[$constraint->getPropertyPath()] = new TranslatableMessage(
                $constraint->getMessageTemplate(),
                $constraint->getParameters()
            );
        }

        foreach (new \ArrayIterator($input) as $key => $item) {
            if (isset($output[$key])) {
                continue;
            } elseif ($item instanceof InputInterface) {
                if ($itemConstraints = $this->validate($item)) {
                    $output[$key] = array_values($itemConstraints)[0];
                }
            } elseif ($item instanceof InputCollectionInterface) {
                /** @var TranslatableMessage[] $collectionConstraints */
                $collectionConstraints = array_merge(
                    ...array_map(
                        fn (InputInterface $subitem): array => $this->validate($subitem),
                        $item->all()
                    )
                );

                if ($collectionConstraints) {
                    $output[$key] = array_values($collectionConstraints)[0];
                }
            }
        }

        if (isset($extendedValidator)) {
            $getters = $this->getterSetterAccessor->getGetters($input);

            foreach ($getters as $getter) {
                if (isset($output[$getter->getName()])) {
                    continue;
                }

                $method = 'validate' . $getter->getName();

                if (method_exists($extendedValidator, $method)) {
                    $error = $extendedValidator->$method();

                    if ($error) {
                        $output[$getter->getName()] = $error;
                    }
                }
            }
        }

        if (empty($output) && isset($extendedValidator)) {
            $output = $extendedValidator->validate();
        }

        return $output;
    }
}
