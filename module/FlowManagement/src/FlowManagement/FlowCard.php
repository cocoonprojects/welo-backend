<?php

namespace FlowManagement;

use Application\DomainEntity;
use Rhumsaa\Uuid\Uuid;
use Application\Entity\BasicUser;

class FlowCard extends DomainEntity implements FlowCardInterface {

	/**
	 * @var BasicUser
	 */
	private $recipient;
	/**
	 * @var array
	 */
	protected $content;
	/**
	 * @var \DateTime
	*/
	protected $createdAt;
	/**
	 * @var BasicUser
	 */
	protected $createdBy;
	/**
	 * @var \DateTime
	 */
	protected $mostRecentEditAt;
	/**
	 * @var BasicUser
	 */
	protected $mostRecentEditBy;
	/**
	 * @var Uuid
	 */
	protected $itemId = null;
	/**
	 * @var boolean
	 */
	protected $hidden;

	public static function create(BasicUser $recipient, $content, BasicUser $by, $itemId = null){
		$rv = new self();
		$event = FlowCardCreated::occur(Uuid::uuid4()->toString(), [
				'to' => $recipient->getId(),
				'content' => $content,
				'by' => $by->getId()
		]);
		$rv->recordThat($event);
		return $rv;
	}

	protected function whenFlowCardCreated(FlowCardCreated $event) {
		$this->id = Uuid::fromString($event->aggregateId());
		$this->recipient = BasicUser::createBasicUser($event->payload()['to']);
		$this->createdAt = $this->mostRecentEditAt = $event->occurredOn();
		$this->createdBy = $this->mostRecentEditBy = BasicUser::createBasicUser($event->payload()['by']);
		$this->hidden = false;
	}
	
	public function hide() {
		if($this->getHidden() == true){
			return;
		}
		$this->recordThat(FlowCardHidden::occur($this->id->toString(), array(
			'prevStatus' => $this->getHidden(),
			'item' => $this->getItemId()
		)));
	}
	
	public function whenFlowCardHidden(FlowCardHidden $event){
		$this->mostRecentEditAt = $event->occurredOn();
		$this->hidden = true;
	}
	
	public function getRecipient(){
		return $this->recipient;
	}
	
	public function getMostRecentEditAt(){
		return $this->mostRecentEditAt;
	}
	
	public function getMostRecentEditBy(){
		return $this->mostRecentEditBy;
	}
	
	public function getContent(){
		return $this->content;
	}
	
	public function getItemId(){
		return $this->itemId;
	}
	
	public function getHidden(){
		return $this->hidden;
	}
}