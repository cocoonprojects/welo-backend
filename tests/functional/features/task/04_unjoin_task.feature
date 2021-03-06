Feature: Unjoin a task
	As a member of a task if I haven't estimated it yet
	I want to unjoin the task
	in order to not be involved any more in task related activities

Scenario: Successfully unjoining an ongoing task the logged user is member of
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to delete a "Member"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000000/members"
	Then the response status code should be 200

Scenario: Unjoining an ongoing task the logged user isn't member of is invariant
	Given that I am authenticated as "bruce.wayne@ora.local" 
	And that I want to delete a "Member"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000000/members"
	Then the response status code should be 204
	
Scenario: Cannot unjoin a not existing task
	Given that I am authenticated as "mark.rogers@ora.local" 
	And that I want to delete a "Member"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-0000000000x0/members"
	Then the response status code should be 404

@skip
Scenario: Cannot unjoin a completed task
	Given that I am authenticated as "paul.smith@ora.local" 
	And that I want to delete a "Member"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000001/members"
	Then the response status code should be 412

@skip
Scenario: Cannot unjoin an accepted task
	Given that I am authenticated as "paul.smith@ora.local" 
	And that I want to delete a "Member"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000002/members"
	Then the response status code should be 412

Scenario: Organization admin can successfully unjoin a member of an ongoing task
	Given that I am authenticated as "mark.rogers@ora.local"  
	And that I want to delete a "Member"
	And I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000000/members/20000000-0000-0000-0000-000000000000"
	Then the response status code should be 200
	#And echo last response

# depends on previous test
Scenario: paul cards again
	Given that I am authenticated as "paul.smith@ora.local" 
	When I request "/flow-management/cards"
	Then the response should contain 'Paul Smith has just left this item'

