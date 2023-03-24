<?php

namespace Alexanevsky\InputManagerBundle\Helper;

class TargetEntityExtractor
{
    private array $targetEntities;

    public function extractTargetEntity(string $class, string $property): ?string
    {
        if (isset($this->targetEntities[$class][$property])) {
            return $this->targetEntities[$class][$property] ?: null;
        }

        $reflProperty = new \ReflectionProperty($class, $property);
        $reflAttribute = array_values(
            array_filter(
                $reflProperty->getAttributes(),
                fn (\ReflectionAttribute $a) => in_array(
                    $a->getName(),
                    [ManyToMany::class, ManyToOne::class, OneToMany::class, OneToOne::class]
                )
            )
        )[0] ?? null;

        $this->targetEntities[$class][$property] = $reflAttribute->getArguments()['targetEntity'] ?? '';

        return $this->targetEntities[$class][$property] ?: null;
    }
}
