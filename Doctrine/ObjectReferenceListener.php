<?php

namespace Arthem\ObjectReferenceBundle\Doctrine;

use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\Persistence\ObjectManager;

final class ObjectReferenceListener implements EventSubscriber
{
    private const CONFIG_KEY = 'orl_config';
    private array $config = [];

    public function __construct(
        private readonly ObjectMapper $objectMapper,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
            Events::prePersist,
            Events::postLoad,
        ];
    }

    private function loadConfiguration(ObjectManager $objectManager, string $class): void
    {
        if (isset($this->config[$class])) {
            return;
        }

        $factory = $objectManager->getMetadataFactory();
        $this->loadMetadataForObjectClass($objectManager, $factory->getMetadataFor($class));
    }

    public function postLoad(PostLoadEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();
        $class = get_class($object);
        $em = $eventArgs->getObjectManager();
        $this->loadConfiguration($em, $class);

        if (!isset($this->config[$class])) {
            return;
        }

        foreach ($this->config[$class] as $fieldName => $field) {
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

    public function prePersist(PrePersistEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();
        $class = get_class($object);
        $this->loadConfiguration($eventArgs->getObjectManager(), $class);

        if (!isset($this->config[$class])) {
            return;
        }

        $reflClass = new \ReflectionClass($object);
        foreach ($this->config[$class] as $fieldName => $field) {
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
     * event subscribers must subscribe to loadClassMetadata event.
     *
     * @param object $metadata
     */
    public function loadMetadataForObjectClass(ObjectManager $objectManager, ClassMetadata $metadata): void
    {
        if ($metadata->isMappedSuperclass) {
            return;
        }

        $className = $metadata->getName();
        if (isset($this->config[$className])) {
            return;
        }

        $config = [];
        $class = $metadata->getReflectionClass();

        foreach ($metadata->fieldMappings as $fieldName => $fieldMapping) {
            if (!$class->hasProperty($fieldName)) {
                continue;
            }

            if (isset($fieldMapping[self::CONFIG_KEY])) {
                $origFieldName = $fieldMapping[self::CONFIG_KEY]['field'];
                $config[$origFieldName] = $fieldMapping[self::CONFIG_KEY];
            } else {
                $reflectionProperty = new \ReflectionProperty($class->getName(), $fieldName);
                $attributes = $reflectionProperty->getAttributes(ObjectReference::class);
                if (!empty($attributes)) {
                    foreach ($attributes as $attribute) {
                        $config = $this->createFields($config, $metadata, $attribute->newInstance(), $fieldName);
                    }
                }
            }
        }

        $cmf = $objectManager->getMetadataFactory();
        $cmf->setMetadataFor($class->getName(), $metadata);

        if (!empty($config)) {
            $this->config[$className] = $config;
        }
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $this->loadMetadataForObjectClass($eventArgs->getObjectManager(), $eventArgs->getClassMetadata());
    }

    private function createFields(array $config, ClassMetadata $metadata, ObjectReference $annotation, $fieldName): array
    {
        $fieldMapping = $metadata->getFieldMapping($fieldName);

        if (!is_array($fieldMapping)) {
            assert($fieldMapping instanceof FieldMapping);

            $fieldMapping = [
                'fieldName' => $fieldMapping->fieldName,
                'nullable' => $fieldMapping->nullable,
                'type' => $fieldMapping->type,
                'length' => $fieldMapping->length,
            ];
        }

        $fieldConfig = [
            'field' => $fieldMapping['fieldName'],
            'type' => $fieldMapping['fieldName'].'Type',
            'id' => $fieldMapping['fieldName'].'Id',
        ];

        $typeField = [
            'fieldName' => $fieldConfig['type'],
            'type' => Types::STRING,
            'length' => $annotation->getKeyLength(),
            'nullable' => $fieldMapping['nullable'],
            self::CONFIG_KEY => $fieldConfig,
        ];

        // Copy field type as it represents the ID specification
        $idField = [
            'fieldName' => $fieldConfig['id'],
            'type' => $fieldMapping['type'],
            'length' => $fieldMapping['length'],
            'nullable' => $fieldMapping['nullable'],
        ];

        unset($metadata->fieldMappings[$fieldName]);
        unset($metadata->fieldNames[array_search($fieldName, $metadata->fieldNames, true)]);

        $metadata->mapField($typeField);
        $metadata->mapField($idField);

        $config[$fieldName] = $fieldConfig;

        return $config;
    }
}
