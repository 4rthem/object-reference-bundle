<?php

namespace Arthem\Bundle\ObjectReferenceBundle\Mapping\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class ObjectReference extends Annotation
{
    /**
     * @var int
     */
    public $keyLength = 30;
}
