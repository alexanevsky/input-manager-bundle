<?php

namespace Alexanevsky\InputManagerBundle;

use Alexanevsky\GetterSetterAccessorBundle\GetterSetterAccessor;
use Alexanevsky\InputManagerBundle\Exception\IncorrectInputToObjectMappingException;
use Alexanevsky\InputManagerBundle\Helper\TargetEntityExtractor;
use Alexanevsky\InputManagerBundle\Input\InputCollectionInterface;
use Alexanevsky\InputManagerBundle\Input\InputInterface;

class Mapper
{
    public function __construct(
        private GetterSetterAccessor    $getterSetterAccessor,
        private TargetEntityExtractor   $targetEntityExtractor
    ) {
    }

    public function mapInputToObject(InputInterface $input, object|string $object): object
    {
        if (is_string($object)) {
            $object = new $object();
        }

        $objectAccessor = $this->getterSetterAccessor->createAccessor($object);
        $inputAccessor = $this->getterSetterAccessor->createAccessor($input);

        foreach ($inputAccessor->getGetters() as $inputGetter) {
            if (!$objectAccessor->hasSetter($inputGetter->getName())) {
                continue;
            }

            $objectSetter = $objectAccessor->getSetter($inputGetter->getName());
            $value = $inputGetter->getValue();

            if ($value instanceof InputInterface) {
                $class = $objectSetter->getTypes()[0] ?? null;

                if (!$class) {
                    throw new IncorrectInputToObjectMappingException(sprintf(
                        'Cannot map "%s" to "%s" because class of "%s" is not defined',
                        $input::class,
                        $object::class,
                        $inputGetter->getName()
                    ));
                }

                $objectSetter->setValue($this->mapInputToObject($value, $class));
            } elseif ($value instanceof InputCollectionInterface) {
                $targetClass = $this->targetEntityExtractor->extractTargetEntity($object::class, $objectSetter->getName());

                throw new IncorrectInputToObjectMappingException(sprintf(
                    'Cannot map "%s" to "%s" because class of "%s" is not defined',
                    $input::class,
                    $object::class,
                    $inputGetter->getName()
                ));

                $collection = [];

                foreach ($value->all() as $valueItem) {
                    $collection[] = $this->mapInputToObject($valueItem, $targetClass);
                }

                $objectSetter->setValue($collection);
            } else {
                $objectSetter->setValue($value);
            }
        }

        return $object;
    }
}
