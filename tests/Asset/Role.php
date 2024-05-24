<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace LmcRbacMvcTest\Asset;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Laminas\Permissions\Rbac\RoleInterface;
use LmcRbacMvcTest\Asset\Permission;

/**
 * @ORM\Entity
 * @ORM\Table(name="roles")
 */
#[ORM\Entity]
#[ORM\Table(name:"roles")]
class Role implements RoleInterface
{
    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    #[ORM\Id]
    #[ORM\Column(type:"integer")]
    #[ORM\GeneratedValue]
    private ?int $id;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=32, unique=true)
     */
    #[ORM\Column(type:"string", length:32, unique:true)]
    private ?string $name;

    /**
     * @var Collection
     *
     * @ORM\JoinTable(name="role_children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     * @ORM\InverseJoinColumn(name="child_id", referencedColumnName="id")
     * @ORM\ManyToMany(targetEntity="role", cascade={"persist"})
     */
    #[ORM\JoinTable(name:"role_children")]
    #[ORM\JoinColumn(name:"parent_id", referencedColumnName:"id")]
    #[ORM\InverseJoinColumn(name:"child_id", referencedColumnName:"id")]
    #[ORM\ManyToMany(targetEntity:"Role", cascade:["persist"])]
    private Collection $children;

    /**
     * @var Collection
     *
     * @ORM\ManyToMany(targetEntity="Role", cascade={"persist"})
     */
    //private Collection $parents;


    /**
     * @var Collection
     *
     * @ORM\ManyToMany(targetEntity="Permission", indexBy="name", fetch="EAGER", cascade={"persist"})
     */
    #[ORM\ManyToMany(targetEntity: "Permission", cascade: ["persist"], fetch: "EAGER", indexBy: "name")]
    private Collection $permissions;

    /**
     * Init the Doctrine collection
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->children = new ArrayCollection();
        $this->permissions = new ArrayCollection();
    }

    /**
     * Get the role identifier
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Add a permission
     *
     * @param  string $name
     * @return void
     */
    public function addPermission(string $name):void
    {
        $permission = new Permission($name);

        $this->permissions[$name] = $permission;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hasPermission(string $name): bool
    {
        return isset($this->permissions[$name]);
    }

    public function addChild(RoleInterface $child): void
    {
        $this->children[] = $child;
    }

    public function getChildren(): iterable
    {
        return $this->children;
    }

    public function addParent(RoleInterface $parent): void
    {
        // TODO: Implement addParent() method.
    }

    public function getParents(): iterable
    {
        return [];
        //return $this->parents;
    }
}