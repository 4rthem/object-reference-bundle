<?php

namespace Arthem\ObjectReferenceBundle\Mapping\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ObjectReference
{
    public function __construct(
        private int $keyLength = 30,
    ) {
    }

    public function getKeyLength(): int
    {
        return $this->keyLength;
    }
}
