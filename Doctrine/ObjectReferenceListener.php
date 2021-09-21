<?php

namespace Arthem\Bundle\ObjectReferenceBundle\Doctrine;

use Arthem\Bundle\ObjectReferenceBundle\Mapper\ObjectMapper;
use Arthem\Bundle\ObjectReferenceBundle\Mapping\Annotation\ObjectReference;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ObjectManager;
use ReflectionClass;

class ObjectReferenceListener implements EventSubscriber
{
    private static $config;

    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var ObjectMapper
     */
    private $objectMapper;

    public function __construct(Reader $annotationReader, ObjectMapper $objectMapper)
    {
        $this->annotationReader = $annotationReader;
        $this->objectMapper = $objectMapper;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata,
            Events::prePersist,
            Events::postLoad,
        ];
    }

    private function loadConfiguration(ObjectManager $objectManager, string $class): array
    {
        $config = [];
        if (isset(self::$config[$class])) {
            $config = self::$config[$class];
        } else {
            $factory = $objectManager->getMetadataFactory();
            $cacheDriver = $factory->getCacheDriver();
            if ($cacheDriver) {
                $cacheId = self::getCacheId($class);
                if (($cached = $cacheDriver->fetch($cacheId)) !== false) {
                    self::$config[$class] = $cached;
                    $config = $cached;
                } else {
                    // re-generate metadata on cache miss
                    $this->loadMetadataForObjectClass($objectManager, $factory->getMetadataFor($class));
                    if (isset(self::$config[$class])) {
                        $config = self::$config[$class];
                    }
                }
            }
        }

        return $config;
    }

    public function postLoad(LifecycleEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();
        $class = get_class($object);
        $em = $eventArgs->getEntityManager();
        $this->loadConfiguration($em, $class);

        if (!isset(self::$config[$class])) {
            return;
        }

        foreach (self::$config[$class] as $fieldName => $field) {
            $id = null;
            $type = null;

            $objectMapper = $this->objectMapper;
            (function () use ($objectMapper, &$id, &$type, $field, $em, $fieldName) {
                $fieldId = $field['id'];
                $id = $this->$fieldId;
                $fieldType = $field['type'];
                $type = $this->$fieldType;
                if ($id && $type) {
                    $this->$fieldName = function () use ($objectMapper, $em, $id, $type) {
                        return $em->find($objectMapper->getClassName($type), $id);
                    };
                }
            })->call($object);
        }
    }

    public function prePersist(LifecycleEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();
        $class = get_class($object);
        $this->loadConfiguration($eventArgs->getObjectManager(), $class);

        if (!isset(self::$config[$class])) {
            return;
        }

        $reflClass = new ReflectionClass($object);
        foreach (self::$config[$class] as $fieldName => $field) {
            $reflProp = $reflClass->getProperty($fieldName);
            $reflProp->setAccessible(true);
            $value = $reflProp->getValue($object);
            if (is_object($value)) {
                $reflId = $reflClass->getProperty($field['id']);
                $reflId->setAccessible(true);
                $reflId->setValue($object, $value->getId());

                $reflType = $reflClass->getProperty($field['type']);
                $reflType->setAccessible(true);
                $reflType->setValue($object, $this->objectMapper->getObjectKey($value));
            }
        }
    }

    /**
     * Scans the objects for extended annotations
     * event subscribers must subscribe to loadClassMetadata event
     *
     * @param  ObjectManager $objectManager
     * @param  object        $metadata
     * @return void
     */
    public function loadMetadataForObjectClass(ObjectManager $objectManager, ClassMetadataInfo $metadata)
    {
        $config = [];

        if ($metadata->isMappedSuperclass) {
            return;
        }

        $class = $metadata->getReflectionClass();

        foreach ($metadata->fieldMappings as $fieldName => $fieldMapping) {
            if (!$class->hasProperty($fieldName)) {
                continue;
            }

            $annotations = $this->annotationReader->getPropertyAnnotations($class->getProperty($fieldName));
            foreach ($annotations as $annotation) {
                if ($annotation instanceof ObjectReference) {
                    $this->createFields($config, $metadata, $annotation, $fieldName);
                }
            }
        }

        $cmf = $objectManager->getMetadataFactory();
        // cache the metadata (even if it's empty)
        // caching empty metadata will prevent re-parsing non-existent annotations
        $cacheId = self::getCacheId($metadata->name);
        if ($cacheDriver = $cmf->getCacheDriver()) {
            $cacheDriver->save($cacheId, $config, null);
        }

        if ($config) {
            self::$config[$metadata->name] = $config;
        }
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $this->loadMetadataForObjectClass($eventArgs->getObjectManager(), $eventArgs->getClassMetadata());
    }

    private function createFields(array &$config, ClassMetadataInfo $metadata, ObjectReference $annotation, $fieldName)
    {
        $fieldMapping = $metadata->fieldMappings[$fieldName];

        // Copy field type as it represents the ID specification
        $idField = $fieldMapping;
        $idField['fieldName'] = $fieldMapping['fieldName'].'Id';
        unset($idField['columnName']);

        $typeField = [
            'fieldName' => $fieldMapping['fieldName'].'Type',
            'type' => 'string',
            'length' => $annotation->keyLength,
            'nullable' => $idField['nullable'],
        ];

        unset($metadata->fieldMappings[$fieldName]);
        unset($metadata->fieldNames[array_search($fieldName, $metadata->fieldNames, true)]);

        $metadata->mapField($typeField);
        $metadata->mapField($idField);

        $config[$fieldName] = [
            'type' => $typeField['fieldName'],
            'id' => $idField['fieldName'],
        ];
    }

    private static function getCacheId(string $className): string
    {
        return $className.'_CLASSMETADATA';
    }
}
