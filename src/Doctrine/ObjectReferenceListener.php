<?php

namespace Arthem\ObjectReferenceBundle\Doctrine;

use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;

#[AsDoctrineListener(event: Events::loadClassMetadata, priority: 500)]
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preFlush)]
#[AsDoctrineListener(event: Events::postLoad)]
class ObjectReferenceListener
{
    private array $config = [];

    public function __construct(
        private readonly ObjectMapper $objectMapper,
    ) {
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

        if (empty($this->config[$class])) {
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

    public function prePersist(PrePersistEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();
        $this->loadConfiguration($eventArgs->getObjectManager(), get_class($object));
        $this->syncReferenceFields($object);
    }

    /**
     * Reference properties are not mapped, so Doctrine cannot detect their changes:
     * the type and id fields must be synced before the change sets are computed.
     */
    public function preFlush(PreFlushEventArgs $eventArgs): void
    {
        $uow = $eventArgs->getObjectManager()->getUnitOfWork();

        $entities = $uow->getScheduledEntityInsertions();
        foreach ($uow->getIdentityMap() as $classEntities) {
            foreach ($classEntities as $entity) {
                $entities[spl_object_id($entity)] = $entity;
            }
        }

        foreach ($entities as $entity) {
            if ($uow->isUninitializedObject($entity)) {
                continue;
            }

            $this->syncReferenceFields($entity);
        }
    }

    private function syncReferenceFields(object $object): void
    {
        $class = get_class($object);
        if (empty($this->config[$class])) {
            return;
        }

        $reflClass = new \ReflectionClass($object);
        foreach ($this->config[$class] as $fieldName => $field) {
            $reflProp = $reflClass->getProperty($fieldName);
            if (!$reflProp->isInitialized($object)) {
                continue;
            }

            $value = $reflProp->getValue($object);
            // A closure is the lazy reference set by postLoad and never resolved, hence unchanged.
            if ($value instanceof \Closure) {
                continue;
            }

            $id = null;
            $type = null;
            if (is_object($value)) {
                $id = $value->getId();
                $type = $this->objectMapper->getObjectKey($value);
            }

            $reflClass->getProperty($field['id'])->setValue($object, $id);
            $reflClass->getProperty($field['type'])->setValue($object, $type);
        }
    }

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
        foreach ($metadata->getReflectionClass()->getProperties() as $property) {
            $attributes = $property->getAttributes(ObjectReference::class);
            if (empty($attributes)) {
                continue;
            }

            $fieldName = $property->getName();
            $fieldConfig = [
                'field' => $fieldName,
                'type' => $fieldName.'Type',
                'id' => $fieldName.'Id',
            ];

            if ($metadata->hasField($fieldName)) {
                $this->replaceReferenceField($metadata, $attributes[0]->newInstance(), $fieldConfig);
            } elseif (!$metadata->hasField($fieldConfig['type']) || !$metadata->hasField($fieldConfig['id'])) {
                continue;
            }

            $config[$fieldName] = $fieldConfig;
        }

        $this->config[$className] = $config;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $this->loadMetadataForObjectClass($eventArgs->getObjectManager(), $eventArgs->getClassMetadata());
    }

    /**
     * Replaces the mapped reference field by the two fields holding the referenced object type and id.
     *
     * @param array{field: string, type: string, id: string} $fieldConfig
     */
    private function replaceReferenceField(ClassMetadata $metadata, ObjectReference $attribute, array $fieldConfig): void
    {
        $fieldMapping = $metadata->getFieldMapping($fieldConfig['field']);

        $typeField = [
            'fieldName' => $fieldConfig['type'],
            'type' => Types::STRING,
            'length' => $attribute->getKeyLength(),
            'nullable' => $fieldMapping['nullable'],
        ];

        // Copy field type as it represents the ID specification
        $idField = [
            'fieldName' => $fieldConfig['id'],
            'type' => $fieldMapping['type'],
            'length' => $fieldMapping['length'],
            'nullable' => $fieldMapping['nullable'],
        ];

        unset($metadata->fieldMappings[$fieldConfig['field']]);
        unset($metadata->fieldNames[array_search($fieldConfig['field'], $metadata->fieldNames, true)]);

        $metadata->mapField($typeField);
        $metadata->mapField($idField);
    }
}
