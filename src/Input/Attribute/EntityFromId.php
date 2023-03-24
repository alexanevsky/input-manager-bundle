<?php

namespace Alexanevsky\InputManagerBundle\Input\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class EntityFromId
{
    /**
     * @param class-string      $class      The entity class.
     * @param string            $property   Property name of entity identifier.
     *                                      The denormalizer will try to find entity by this property during denormalizing.
     * @param string|false|null $suffix     Suffix that is appended to default property name.
     *                                      For example, property "user" will be denormalized from "userId".
     *                                      If given value is string, it will be appended.
     *                                      If given value is null, the identifier property name will be appended ("s" will append if value is array or collection).
     *                                      If given value is false, nothing will be appended.
     */
    public function __construct(
        public string $class,
        public string $property = 'id',
        public string|false|null $suffix = null
    ) {
    }
}
