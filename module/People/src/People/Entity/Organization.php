<?php

namespace People\Entity;

use Application\Entity\BasicUser;
use Rhumsaa\Uuid\Uuid;
use Zend\Permissions\Acl\Resource\ResourceInterface;
use Doctrine\ORM\Mapping AS ORM;
use Application\Entity\EditableEntity;
use People\ValueObject\OrganizationParams;
use People\Organization as OrganizationAggregate;

/**
 * @ORM\Entity
 * @ORM\Table(name="organizations")
 */
class Organization extends EditableEntity implements ResourceInterface
{
	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @var string
	 */
	private $name;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @var bool
     */
	private $syncErrorsNotification = false;

	/**
	 * @ORM\Column(type="json_array", nullable=true)
	 * @var string
	 */
	private $settings = [];

	/**
	 * @ORM\Column(type="json_array", nullable=true)
	 * @var array
	 */
	private $lanes = [];

	public function getName()
	{
		return $this->name;
	}

	public function setName($name)
	{
		$this->name = $name;

		return $this;
	}

	public function setSyncErrorsNotification($enabled)
    {
	    $this->syncErrorsNotification = $enabled;

	    return $this;
    }

	public function setSettings($settingKey, $settingValue){
		if(is_array($settingValue)){
			foreach ($settingValue as $key=>$value){
				$this->settings[$settingKey][$key] = $value;
			}
		}else{
			$this->settings[$settingKey] = $settingValue;
		}
		return $this;
	}

	public function getSettings($key = null){
		if(is_null($key)){
			return $this->settings;
		}
		if(array_key_exists($key, $this->settings)){
			return $this->settings[$key];
		}
		return null;
	}

	public function setLanes($lanes){
        $this->lanes = $lanes;
		return $this;
	}

	public function getLanes() {
        return $this->lanes;
	}

	public function getSortedLanes()
    {
        natsort($this->lanes);

        return $this->lanes;
    }

	public function addLane(Uuid $id, $name, BasicUser $user, \DateTime $when)
    {
	    $this->lanes[$id->toString()] = $name;

	    $this->setMostRecentEditBy($user);
	    $this->setMostRecentEditAt($when);
    }

	public function updateLane(Uuid $id, $name, BasicUser $user, \DateTime $when)
    {
	    $this->lanes[$id->toString()] = $name;

	    $this->setMostRecentEditBy($user);
	    $this->setMostRecentEditAt($when);
    }

	public function deleteLane(Uuid $id, BasicUser $user, \DateTime $when)
    {
	    unset($this->lanes[$id->toString()]);

	    $this->setMostRecentEditBy($user);
	    $this->setMostRecentEditAt($when);
    }

	public function getParams() {

		$settings = $this->getSettings(OrganizationAggregate::ORG_SETTINGS);

		if (is_array($settings)) {
			return OrganizationParams::fromArray($settings['params']);
		}

		if ($settings instanceof OrganizationParams) {
		    return $settings;
        }

		return OrganizationParams::createWithDefaults();
	}


	public function getResourceId()
	{
		return 'Ora\Organization';
	}
}