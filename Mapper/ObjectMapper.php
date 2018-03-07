<?php

namespace Arthem\Bundle\ObjectReferenceBundle\Mapper;

use Doctrine\Common\Util\ClassUtils;

class ObjectMapper
{
    /**
     * @var array
     */
    protected $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function getClassName(string $objectKey): string
    {
        if (!isset($this->mapping[$objectKey])) {
            throw new \InvalidArgumentException(sprintf('Undefined object "%s" in the object mapping', $objectKey));
        }

        return $this->mapping[$objectKey];
    }

    public function getObjectKey($object): string
    {
        $className = ClassUtils::getRealClass(is_string($object) ? $object : get_class($object));

        if (false === $key = array_search($className, $this->mapping, true)) {
            $reflection = new \ReflectionClass($className);
            while ($reflection->getParentClass()) {
                $reflection = $reflection->getParentClass();
                if (false !== $key = array_search($reflection->getName(), $this->mapping, true)) {
                    return $key;
                }
            }

            throw new \InvalidArgumentException(sprintf(
                    'Class "%s" is not defined in the object mapping',
                    $className)
            );
        }

        return $key;
    }
}
