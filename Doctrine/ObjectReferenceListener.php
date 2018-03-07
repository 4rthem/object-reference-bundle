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

class ObjectReferenceListener implements EventSubscriber
{
    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var array
     */
    private $config;

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

    public function prePersist(LifecycleEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();

        if (!isset($this->config[get_class($object)])) {
            return;
        }

        $reflClass = new \ReflectionClass($object);
        foreach ($this->config[get_class($object)] as $fieldName => $field) {
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

    public function postLoad(LifecycleEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();
        $em = $eventArgs->getEntityManager();

        if (!isset($this->config[get_class($object)])) {
            return;
        }

        foreach ($this->config[get_class($object)] as $fieldName => $field) {
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

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        /** @var ClassMetadataInfo $metadata */
        $metadata = $eventArgs->getClassMetadata();

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
                    $this->createFields($metadata, $annotation, $fieldName);
                }
            }
        }
    }

    private function createFields(ClassMetadataInfo $metadata, ObjectReference $annotation, $fieldName)
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

        unset($metadata->fieldMappings[$fieldName], $metadata->fieldNames[$fieldName]);

        $metadata->mapField($typeField);
        $metadata->mapField($idField);

        $className = $metadata->getName();
        if (!isset($this->config[$className])) {
            $this->config[$className] = [];
        }
        $this->config[$className][$fieldName] = [
            'type' => $typeField['fieldName'],
            'id' => $idField['fieldName'],
        ];
    }
}
