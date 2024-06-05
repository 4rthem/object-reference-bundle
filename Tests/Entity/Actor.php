<?php

namespace Arthem\ObjectReferenceBundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Actor extends AbstractUuidEntity implements PersonInterface
{
    public function getType(): string
    {
        return 'actor';
    }
}
