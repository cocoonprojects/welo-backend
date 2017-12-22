Feature: Create organization
  As a user
  I want to create a new organization
  in order to have a new group of people that can work on organization related streams

  Scenario: Cannot create an organization anonymously
    Given that I want to make a new "Organization"
    And that its "subject" is "My First Organization"
    When I request "/organizations"
    Then the response status code should be 401

  Scenario: Successfully creating an organization
    Given that I am authenticated as "mark.rogers@ora.local"
    And that I want to make a new "Organization"
    And that its "name" is "My First Organization"
    When I request "/organizations"
    Then the response status code should be 201
    And the response should be JSON
    And the header "Location" should be "/organizations/[0-9a-z\-]+"
    And the "name" property should be "My First Organization"

  Scenario: Successfully creating an organization without a name
    Given that I am authenticated as "mark.rogers@ora.local"
    And that I want to make a new "Organization"
    When I request "/organizations"
    Then the response status code should be 201
    And the header "Location" should be "/organizations/[0-9a-z\-]+"

  @settings
  Scenario: Get organization settings
    Given that I am authenticated as "mark.rogers@ora.local"
    When I send a PUT request to "/00000000-0000-0000-1000-000000000000/settings" with JSON body:
    """
      {
        "assignment_of_shares_timebox": "10",
        "assignment_of_shares_remind_interval": "7",
        "item_idea_voting_timebox": "7",
        "item_idea_voting_remind_interval": "5",
        "completed_item_voting_timebox": "7",
        "completed_item_interval_close_task": "10",
        "tasks_limit_per_page": "10",
        "personal_transaction_limit_per_page": "10",
        "org_transaction_limit_per_page": "10",
        "org_members_limit_per_page": "20",
        "flow_welcome_card_text": "Banana!!!!",
        "shiftout_days": "90",
        "shiftout_min_item": "2",
        "shiftout_min_credits": "5000",
        "manage_priorities": "1"
      }
    """
    Then the response status code should be 202
    When I request "/00000000-0000-0000-1000-000000000000/settings"
    Then the response should be a JSON like:
    """
      {
        "settings": {
            "assignment_of_shares_timebox": "10",
            "assignment_of_shares_remind_interval": "7",
            "item_idea_voting_timebox": "7",
            "item_idea_voting_remind_interval": "5",
            "completed_item_voting_timebox": "7",
            "completed_item_interval_close_task": "10",
            "tasks_limit_per_page": "10",
            "personal_transaction_limit_per_page": "10",
            "org_transaction_limit_per_page": "10",
            "org_members_limit_per_page": "20",
            "flow_welcome_card_text": "Banana!!!!",
            "shiftout_days": "90",
            "shiftout_min_item": "2",
            "shiftout_min_credits": "5000",
            "manage_priorities": "1"
          }
      }
    """