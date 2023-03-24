<?php

namespace Alexanevsky\InputManagerBundle;

use Alexanevsky\GetterSetterAccessorBundle\GetterSetterAccessor;
use Alexanevsky\GetterSetterAccessorBundle\Model\ObjectSetter;
use Alexanevsky\InputManagerBundle\Exception\InvalidObjectToDeserializeException;
use Alexanevsky\InputManagerBundle\Input\Attribute\EntityFromId;
use Alexanevsky\InputManagerBundle\Input\InputCollectionInterface;
use Alexanevsky\InputManagerBundle\Input\InputInterface;
use Alexanevsky\InputManagerBundle\Input\InputModifiableInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\Serializer\SerializerInterface;
use function Symfony\Component\String\u;

class Deserializer
{
    public function __construct(
        private EntityManagerInterface  $em,
        private GetterSetterAccessor    $getterSetterAccessor,
        private SerializerInterface     $serializer
    ) {
    }

    /**
     * @param InputInterface|class-string<InputInterface> $input
     */
    public function deserialize(string|array|object $source, InputInterface|string $input): InputInterface
    {
        if (is_array($source)) {
            return $this->deserializeArray($source, $input);
        } elseif (is_string($source)) {
            return $this->deserializeJson($source, $input);
        } else {
            return $this->deserializeObject($source, $input);
        }
    }

    /**
     * @param InputInterface|class-string<InputInterface> $input
     */
    private function deserializeJson(string $json, InputInterface|string $input): InputInterface
    {
        return $this->deserializeArray(json_decode($json, true), $input);
    }

    /**
     * @param InputInterface|class-string<InputInterface> $input
     */
    private function deserializeObject(object $object, InputInterface|string $input): InputInterface
    {
        $this->validateSource($input);

        $objectAccessor = $this->getterSetterAccessor->createAccessor($object);
        $array = [];

        foreach ($objectAccessor->getGetters() as $getter) {
            $array[$getter->getName()] = $getter->getValue();
        }

        return $this->deserializeArray($array, $input);
    }

    /**
     * @param InputInterface|class-string<InputInterface> $input
     */
    private function deserializeArray(array $array, InputInterface|string $input): InputInterface
    {
        $this->validateSource($input);

        if (is_string($input)) {
            $input = new $input();
        }

        $inputAccessor = $this->getterSetterAccessor->createAccessor($input);

        foreach ($inputAccessor->getSetters() as $setter) {
            $possibleKeys = $this->resolveSetterPossibleKeys($setter);
            $key = array_values(array_filter(array_keys($array), fn ($k) => in_array($k, $possibleKeys, true)))[0] ?? null;

            if (!$key) {
                continue;
            }

            $keya = $key;
            $value = $array[$keya];

            if (is_string($value)) {
                $value = trim($value);
            }

            try {
                /** @var EntityFromId $entityFromIdAttr */
                if ($entityFromIdAttr = $setter->getAttribute(EntityFromId::class)) {
                    if (in_array('array', $setter->getTypes())) {
                        if (empty($value)) {
                            $setter->setValue([]);
                        } elseif ($value instanceof Collection) {
                            $setter->setValue($value->toArray());
                        } elseif (is_object(array_values($value)[0] ?? null)) {
                            $setter->setValue($value);
                        } else {
                            $setter->setValue(
                                $this->em
                                    ->getRepository($entityFromIdAttr->class)
                                    ->findBy([$entityFromIdAttr->property => $value])
                            );
                        }
                    } else {
                        if (empty($value)) {
                            $setter->setValue(null);
                        } elseif (is_object($value)) {
                            $setter->setValue($value);
                        } else {
                            $setter->setValue(
                                $this->em
                                    ->getRepository($entityFromIdAttr->class)
                                    ->findOneBy([$entityFromIdAttr->property => $value])
                            );
                        }
                    }
                } else {
                    $setter->setValue($value);
                }
            } catch (\TypeError $e) {
                if (class_exists($setter->getTypes()[0] ?? '')) {
                    $class = $setter->getTypes()[0];

                    if (is_object($value) && is_a($value, $class)) {
                        $setter->setValue($value);
                    } elseif (is_a($class, InputInterface::class, true)) {
                        $setter->setValue($this->deserialize($value, $class));
                    } elseif (is_a($class, InputCollectionInterface::class, true)) {
                        $collectionClass = $class;
                        /** @var InputCollectionInterface */
                        $collection = new $collectionClass();
                        array_walk(
                            $value,
                            fn ($collectionItem) => $collection->add($this->deserialize($collectionItem, $collection->getClass()))
                        );
                        $setter->setValue($collection);
                    } elseif (is_array($value) && method_exists($class, 'fromArray')) {
                        $setter->setValue($class::fromArray($value));
                    } elseif (is_array($value)) {
                        $setter->setValue($this->serializer->deserialize(json_encode($value, JSON_UNESCAPED_UNICODE), $class, 'json'));
                    } elseif (is_string($value) && method_exists($class, 'fromString')) {
                        $setter->setValue($class::fromString($value));
                    } else {
                        try {
                            // Just to catch MappingException if given class is not entity
                            $this->em->getClassMetadata($class);

                            $setter->setValue($this->em->find($class, $value));
                        } catch (MappingException) {
                            $setter->setValue(new $class($value));
                        }
                    }
                } elseif ($setter->isNullable() && !$value) {
                    $setter->setValue(null);
                } elseif (in_array('string', $setter->getTypes())) {
                    $setter->setValue((string) $value);
                } elseif (in_array('float', $setter->getTypes())) {
                    $setter->setValue((float) $value);
                } elseif (in_array('int', $setter->getTypes())) {
                    $setter->setValue((int) $value);
                } elseif (in_array('array', $setter->getTypes())) {
                    $setter->setValue((array) $value);
                } else {
                    throw $e;
                }
            }
        }

        if ($input instanceof InputModifiableInterface) {
            $input->modify();
        }

        return $input;
    }

    private function validateSource(object|string $input): void
    {
        if (!is_a($input, InputInterface::class, true)) {
            throw new InvalidObjectToDeserializeException(sprintf(
                'Can not deserialize to "%s" because it is not instance of "%s"',
                is_object($input) ? $input::class : $input,
                InputInterface::class
            ));
        }
    }

    private function resolveSetterPossibleKeys(ObjectSetter $setter): array
    {
        $possibleKeys = [
            $setter->getName(),
            u($setter->getName())->camel()->toString(),
            u($setter->getName())->snake()->toString(),
        ];

        /** @var EntityFromId $entityFromIdAttr */
        if ($entityFromIdAttr = $setter->getAttribute(EntityFromId::class)) {
            $keySuffix = $entityFromIdAttr->suffix;

            if (in_array('array', $setter->getTypes())) {
                $keySuffix ??= $entityFromIdAttr->property . 's';
            } else {
                $keySuffix ??= $entityFromIdAttr->property;
            }

            if ($keySuffix) {
                $suffixedKey = $setter->getName() . '_' . $keySuffix;
                $possibleKeys = array_merge($possibleKeys, [
                    u($suffixedKey)->camel()->toString(),
                    u($suffixedKey)->snake()->toString(),
                ]);
            }
        }

        return array_unique(array_filter($possibleKeys));
    }
}
