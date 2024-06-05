# Object Reference bundle

```php
namespace App\Entity;

use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class Story
{
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)] 
    #[ObjectReference(keyLength: 15)] 
    private \Closure|Actor $actor;
    private $actorId; // must be declared, even if not used
    private $actorType; // must be declared, even if not used

    /**
     * @return object|null
     */
    public function getActor(): ?Actor
    {
        if ($this->actor instanceof \Closure) {
            $this->actor = $this->actor->call($this);
        }

        return $this->actor;
    }

    public function setActor(?Actor $actor)
    {
        $this->actor = $actor;
    }
}
```
