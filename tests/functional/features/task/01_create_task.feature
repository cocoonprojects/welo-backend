Feature: Create task
	As an organization member
	I want to create a new task into one of my organization streams
	in order to allow the team to start the estimation

Scenario: Cannot create a task anonymously
	Given that I want to make a new "Task"
	And that its "subject" is "My First Task"
	And that its "description" is "My First Task description"
	And that its "streamID" is "00000000-1000-0000-0000-000000000000"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 401

Scenario: Successfully creating a task into a stream and with a subject
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to make a new "Task"
	And that its "subject" is "My First Task"
	And that its "description" is "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed ac quam ac urna varius vehicula ut a mi. Sed at tincidunt diam, porta euismod magna. Vestibulum nisl eros, hendrerit quis quam ut, laoreet gravida elit. Nunc ornare ex iaculis orci posuere, id sollicitudin metus vulputate. Nunc laoreet tempus egestas. Integer imperdiet diam tellus, a congue est lacinia a. Nunc porta nisl sed turpis volutpat lacinia. Suspendisse volutpat dui vel gravida cursus. Aenean sollicitudin sagittis enim, in sed"
	And that its "streamID" is "00000000-1000-0000-0000-000000000000"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 201
	And the header "Location" should be "/task-management/tasks/[0-9a-z\-]+"

Scenario: Cannot create a task outside a stream
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to make a new "Task"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 400

Scenario: Cannot create a task into a non existing stream
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to make a new "Task"
	And that its "subject" is "UNA ROTONDA SUL MARE"
	And that its "description" is "IL NOSTRO DISCO CHE SUONA"
	And that its "streamID" is ""
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 404

Scenario: Cannot create a task into a non existing stream
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to make a new "Task"
	And that its "subject" is "UNA ROTONDA SUL MARE"
	And that its "description" is "IL NOSTRO DISCO CHE SUONA"
	And that its "streamID" is "00000000-xxxx-0000-0000-000000000000"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 404

Scenario: Cannot create a task without a subject
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to make a new "Task"
	And that its "description" is "description"
	And that its "subject" is ""
	And that its "streamID" is "00000000-1000-0000-0000-000000000000"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 400

Scenario: Cannot create a task without a description
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to make a new "Task"
	And that its "subject" is "subject"
	And that its "description" is ""
	And that its "streamID" is "00000000-1000-0000-0000-000000000000"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 400

Scenario: Successfully creating a work item idea into a stream and with a subject
	Given that I am authenticated as "mark.rogers@ora.local"
	And that I want to make a new "Work Item Idea"
	And that its "subject" is "My First Task"
	And that its "description" is "It's a new work item idea"
	And that its "streamID" is "00000000-1000-0000-0000-000000000000"
	And that its "status" is "0"
	When I request "/00000000-0000-0000-1000-000000000000/task-management/tasks"
	Then the response status code should be 201
	And the header "Location" should be "/task-management/tasks/[0-9a-z\-]+"