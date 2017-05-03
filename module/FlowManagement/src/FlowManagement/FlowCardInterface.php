<?php

namespace FlowManagement;

interface FlowCardInterface{
	
	const WELCOME_CARD = 'Welcome';
	const VOTE_IDEA_CARD = 'VoteIdea';
	const VOTE_COMPLETED_ITEM_CARD = 'VoteCompletedItem';
	const VOTE_COMPLETED_ITEM_VOTING_CLOSED_CARD = 'VoteCompletedItemVotingClosed';
	const VOTE_COMPLETED_ITEM_REOPENED_CARD = 'VoteCompletedItemReopened';
	const ITEM_OWNER_CHANGED_CARD = 'ItemOwnerChanged';
	const ITEM_MEMBER_REMOVED_CARD = 'ItemMemberRemoved';
	const ORGANIZATION_MEMBER_ROLE_CHANGED_CARD = 'OrganizationMemberRoleChanged';
	const CREDITS_ADDED_CARD = 'CreditsAdded';
	const CREDITS_SUBTRACTED_CARD = 'CreditsSubtracted';
	const ITEM_CLOSED_CARD = 'ItemClosed';

	public function getId();
	
	public function getRecipient();
	
	public function getMostRecentEditAt();
	
	public function getMostRecentEditBy();
	
	public function getContent();
}