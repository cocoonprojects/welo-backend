<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
class BasicUser
{
	/**
	 * @ORM\Id
     * @ORM\Column(type="string")
	 */
	protected $id;

	/**
	 * @ORM\Column(type="string", length=100, nullable=TRUE)
	 */
	protected $firstname;

	/**
	 * @ORM\Column(type="string", length=100, nullable=TRUE)
	 */
	protected $lastname;

	/**
	 * @param $id string
	 * @return BasicUser
	 */
	public static function createBasicUser($id) {
		$rv = new self();
		$rv->id = $id;
		return $rv;
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		return $this->id;
	}

	public function setFirstname($firstname)
	{
		$this->firstname = $firstname;

		return $this;
	}

	public function getFirstname()
	{
		return $this->firstname;
	}

	public function setLastname($lastname)
	{
		$this->lastname = $lastname;

		return $this;
	}

	public function getLastname()
	{
		return $this->lastname;
	}
}