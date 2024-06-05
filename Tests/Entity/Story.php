<?php

namespace Arthem\ObjectReferenceBundle\Tests\Entity;

use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Story extends AbstractUuidEntity
{
    #[ORM\Column(type: Types::STRING, length: 36, nullable: false)]
    #[ObjectReference(keyLength: 15)]
    private \Closure|PersonInterface $person;
    private $personId; // must be declared, even if not used
    private $personType; // must be declared, even if not used

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[ObjectReference(keyLength: 15)]
    private \Closure|PersonInterface $owner;
    private $ownerId; // must be declared, even if not used
    private $ownerType; // must be declared, even if not used

    public function getPerson(): ?PersonInterface
    {
        if ($this->person instanceof \Closure) {
            $this->person = $this->person->call($this);
        }

        return $this->person;
    }

    public function setPerson(?PersonInterface $person): void
    {
        $this->person = $person;
    }

    public function getOwner(): ?PersonInterface
    {
        if ($this->owner instanceof \Closure) {
            $this->owner = $this->owner->call($this);
        }

        return $this->owner;
    }

    public function setOwner(?PersonInterface $owner): void
    {
        $this->owner = $owner;
    }
}
