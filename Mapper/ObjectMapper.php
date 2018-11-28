<?php

namespace Arthem\Bundle\ObjectReferenceBundle\Mapper;

use Doctrine\Common\Persistence\Proxy;

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

    public function isObjectMapped($object): bool
    {
        $className = self::getRealClass(is_string($object) ? $object : get_class($object));

        if (false === array_search($className, $this->mapping, true)) {
            $reflection = new \ReflectionClass($className);
            while ($reflection->getParentClass()) {
                $reflection = $reflection->getParentClass();
                if (false !== array_search($reflection->getName(), $this->mapping, true)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private static function getRealClass($class): string
    {
        if (false === $pos = strrpos($class, '\\' . Proxy::MARKER . '\\')) {
            return $class;
        }

        return substr($class, $pos + Proxy::MARKER_LENGTH + 2);
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
        $className = self::getRealClass(is_string($object) ? $object : get_class($object));

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
