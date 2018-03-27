Feature: Edit task
	As a task owner
	I want to edit an ongoing task
	in order to fix mistakes made during creation

Scenario: Successfully updating the subject and the description of an ongoing task
	Given that I am authenticated as "mark.rogers@ora.local"
	And the organization "00000000-0000-0000-1000-000000000000" has the following lanes:
		| id                                    | name          |
		| f3d75d13-a38f-415c-a461-1e71ad730624  | prima lane    |
		| 8d1bba08-24c5-4d63-8ee8-031475688edf  | seconda lane  |
		| 0bad8967-77d2-4005-97b3-8514f15d56ba  | terza lane    |
	And that I want to update a "Task"
	And that its "subject" is "This update subject is a lot better than the previous one"
	And that its "description" is "This update description is a lot better than the previous one"
	And that its "lane" is "f3d75d13-a38f-415c-a461-1e71ad730624"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000000"
	Then the response status code should be 202

Scenario: Successfully updating the subject and the description of a completed task
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to update a "Task"
	And that its "subject" is "This update subject is a lot better than the previous one"
	And that its "description" is "This update description is a lot better than the previous one"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000001"
	Then the response status code should be 202

Scenario: Successfully updating the subject and the description of an accepted task
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to update a "Task"
	And that its "subject" is "This update subject is a lot better than the previous one"
	And that its "description" is "This update description is a lot better than the previous one"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000002"
	Then the response status code should be 202

Scenario: Cannot update a task with an empty subject
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to update a "Task"
	And that its "subject" is ""
	And that its "description" is "This update description is a lot better than the previous one"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000000"
	Then the response status code should be 400

Scenario: Cannot update a task with an empty description
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to update a "Task"
	And that its "subject" is "This update subject is a lot better than the previous one"
	And that its "description" is ""
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks/00000000-0000-0000-0000-000000000000"
	Then the response status code should be 400

Scenario: Cannot update the entire collection of tasks
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to update a "Task"
	And that its "subject" is "This update subject is a lot better than the previous one"
	And that its "description" is "This update description is a lot better than the previous one"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 405