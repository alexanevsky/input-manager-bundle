<?php

namespace Alexanevsky\InputManagerBundle\InputValidator\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class SetFromPayload
{
    public function __construct(
        public bool $required = false
    ) {
    }
}
