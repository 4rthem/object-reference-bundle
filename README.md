# Object Reference bundle

```php
namespace AppBundle\Entity;

use Arthem\Bundle\ObjectReferenceBundle\Mapping\Annotation\ObjectReference;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table
 * @ORM\Entity
 */
class Story
{
    /**
     * @var object|callable
     *
     * @ORM\Column(type="string", length=36)
     * @ObjectReference()
     */
    private $actor;
    private $actorId; // must be declared, even if not used
    private $actorType; // must be declared, even if not used

    /**
     * @return object|null
     */
    public function getActor()
    {
        if ($this->actor instanceof \Closure) {
            $this->actor = $this->actor->call($this);
        }

        return $this->actor;
    }

    /**
     * @param object|null $actor
     */
    public function setActor($actor)
    {
        $this->actor = $actor;
    }
}
```
