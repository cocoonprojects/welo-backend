<?php

namespace Application\Entity;

use Application\InvalidArgumentException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use People\Entity\Organization as ReadModelOrganization;
use People\Entity\OrganizationMembership;
use People\Organization;
use Rhumsaa\Uuid\Uuid;
use Zend\Permissions\Acl\Role\RoleInterface;
use Zend\Permissions\Acl\Resource\ResourceInterface;
use FlowManagement\Entity\FlowCard;

/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 */
class User extends BasicUser implements RoleInterface, ResourceInterface
{
    const STATUS_ACTIVE = 1;

    const ROLE_ADMIN = 'admin';
    const ROLE_GUEST = 'guest';
    const ROLE_USER = 'user';
    const ROLE_SYSTEM = 'system';

    const SYSTEM_USER = '00000000-0000-0000-0000-000000000000';

    const EVENT_CREATED = 'User.Created';

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="createdBy_id", referencedColumnName="id", nullable=TRUE)
     * @var BasicUser
     */
    protected $createdBy;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $mostRecentEditAt;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="mostRecentEditBy_id", referencedColumnName="id", nullable=TRUE)
     * @var BasicUser
     */
    protected $mostRecentEditBy;

    /**
     * @ORM\Column(type="string", length=200, unique=TRUE)
     * @var string
     */
    private $email;

    /**
     * @ORM\Column(type="json_array", nullable=TRUE)
     */
    private $secondaryEmails;

    /**
     * @ORM\Column(type="string", nullable=TRUE)
     * @var string
     */
    private $picture;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    private $status;

    /**
     * @ORM\OneToMany(targetEntity="People\Entity\OrganizationMembership", mappedBy="member", indexBy="organization_id", cascade={"persist"})
     * @var OrganizationMembership[]
     */
    private $memberships;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $role = self::ROLE_USER;

    /**
    * @ORM\Column(type="string", nullable=TRUE)
    * @var string
    */
    private $kanbanizeUsername;

    /**
     * @ORM\OneToMany(targetEntity="FlowManagement\Entity\FlowCard", mappedBy="recipient", cascade={"persist"})
     * @var FlowCard[]
     */
    private $flowcards;

    private function __construct()
    {
        $this->memberships = new ArrayCollection();
        $this->flowcards = new ArrayCollection();
        $this->secondaryEmails = [];
    }

    /**
     * Creates an user with role ROLE_USER
     *
     * @param Uuid      $id         id
     * @param string    $email      primary email
     * @param string    $firstname  firstname
     * @param string    $lastname   lastname
     * @param string    $picture    picture
     * @param string    $role       role
     * @param User      $createdBy  creator
     *
     * @return User
     */
    public static function createUser(
        Uuid $id,
        $email = null,
        $firstname = null,
        $lastname = null,
        $picture = null,
        $role = self::ROLE_USER,
        User $createdBy = null)
    {
        $u = new self();
        $u->id = (string) $id;
        $u->email =  $email;
        $u->firstname = $firstname;
        $u->lastname = $lastname;
        $u->picture = $picture;
        $u->role = $role;
        $u->status = self::STATUS_ACTIVE;
        $u->createdAt = new \DateTime();
        $u->createdBy = $createdBy;
        $u->mostRecentEditAt = $u->createdAt;
        $u->mostRecentEditBy = $u->createdBy;

        return $u;
    }

    /**
     * @deprecated use User::createUser instead
     */
    public static function create(User $createdBy = null)
    {
        $rv = new self();
        $rv->id = Uuid::uuid4()->toString();
        $rv->status = self::STATUS_ACTIVE;
        $rv->createdAt = new \DateTime();
        $rv->createdBy = $createdBy;
        $rv->mostRecentEditAt = $rv->createdAt;
        $rv->mostRecentEditBy = $rv->createdBy;
        return $rv;
    }

    /**
     * @param ReadModelOrganization|Organization $organization
     * @param string $role
     * @return $this
     */
    public function addMembership($organization, $role = OrganizationMembership::ROLE_CONTRIBUTOR)
    {
        $org = null;
        if ($organization instanceof Organization) {
            $org = new ReadModelOrganization($organization->getId());
            $org->setName($organization->getName());
        } elseif ($organization instanceof ReadModelOrganization) {
            $org = $organization;
        } else {
            throw new InvalidArgumentException('First argument must be of type People\\Organization or People\\Entity\\Organization: ' . get_class($organization) . ' given');
        }
        $membership = new OrganizationMembership($this, $org, $role);
        $this->memberships->set($org->getId(), $membership);
        return $this;
    }

    /**
     * @param ReadModelOrganization|Organization $organization
     * @return $this
     */
    public function removeMembership($organization)
    {
        if (!($organization instanceof Organization) && !($organization instanceof ReadModelOrganization)) {
            throw new InvalidArgumentException('First argument must be of type People\\Organization or People\\Entity\\Organization: ' . get_class($organization) . ' given');
        }
        $this->memberships->remove($organization->getId());
    }

    /**
     * @param string|ReadModelOrganization|Organization $organization
     * @return bool
     */
    public function isMemberOf($organization)
    {
        $key = $organization;
        if ($organization instanceof Organization || $organization instanceof ReadModelOrganization) {
            $key = $organization->getId();
        }
        return $this->memberships->containsKey($key);
    }

    public function isContributorOf($organization)
    {
        $key = $organization;
        if ($organization instanceof Organization ||
           $organization instanceof ReadModelOrganization) {
            $key = $organization->getId();
        }
        $membership = $this->memberships->get($key);

        if (is_null($membership)) {
            return false;
        }

        return $membership->getRole() == OrganizationMembership::ROLE_CONTRIBUTOR;
    }

    public function isRoleMemberOf($organization)
    {
        $key = $organization;
        if ($organization instanceof Organization ||
           $organization instanceof ReadModelOrganization) {
            $key = $organization->getId();
        }
        $membership = $this->memberships->get($key);

        if (is_null($membership)) {
            return false;
        }

        return $membership->getRole() == OrganizationMembership::ROLE_MEMBER;
    }

    /**
     * @param string|ReadModelOrganization|Organization $organization
     * @return bool
     */
    public function isOwnerOf($organization)
    {
        $key = $organization;
        if ($organization instanceof Organization || $organization instanceof ReadModelOrganization) {
            $key = $organization->getId();
        }
        $membership = $this->memberships->get($key);
        if (is_null($membership)) {
            return false;
        }
        return $membership->getRole() == OrganizationMembership::ROLE_ADMIN;
    }

    /**
     * @param string|ReadModelOrganization|Organization $organization
     * @return OrganizationMembership|null
     */
    public function getMembership($organization)
    {
        $id = is_object($organization) ? $organization->getId() : $organization;
        return $this->memberships->get($id);
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getPicture()
    {
        return $this->picture;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getRoleId()
    {
        return $this->role;
    }

    public function isAdmin()
    {
        return $this->getRole() === self::ROLE_ADMIN;
    }

    public function getOrganizationMemberships()
    {
        return $this->memberships->toArray();
    }

    public function getFlowCards()
    {
        return $this->flowcards;
    }

    public function setRole($role)
    {
        $this->role = $role;
        return $this;
    }

    public function addFlowCard(FlowCard $card)
    {
        $this->flowcards[] = $card;
        return $this;
    }

    public function getDislayedName()
    {
        $fullname = $this->getFirstname() . ' ' . $this->getLastname();

        if ($fullname != ' ') {
            return $fullname;
        }

        return $this->getEmail();
    }

    public function getSecondaryEmails()
    {
        return $this->secondaryEmails;
    }

    public function hasSecondaryEmail($email)
    {
        if (is_null($this->secondaryEmails)) {
            return false;
        }
        return array_search($email, $this->secondaryEmails)!==false;
    }

    public function setSecondaryEmails($emails)
    {
        $this->secondaryEmails = $emails;
    }

    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getResourceId()
    {
        return 'Ora\User';
    }
}
