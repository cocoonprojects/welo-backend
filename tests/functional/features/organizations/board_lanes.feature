@lanes
Feature: Lanes management in Welo
  As a user
  I want to create manage board lanes inside welo

  Scenario: Updating a lane
      Given that I am authenticated as "mark.rogers@ora.local"
      And the organization "00000000-0000-0000-1000-000000000000" has the following lanes:
        | id                                    | name          |
        | f3d75d13-a38f-415c-a461-1e71ad730624  | prima lane    |
        | 8d1bba08-24c5-4d63-8ee8-031475688edf  | seconda lane  |
        | 0bad8967-77d2-4005-97b3-8514f15d56ba  | terza lane    |

      When I send a PUT request to "/00000000-0000-0000-1000-000000000000/settings/lanes/8d1bba08-24c5-4d63-8ee8-031475688edf" with JSON body:
      """
        {"name": "cambiata"}
      """
      Then the response status code should be 200
      When I send a GET request to "/00000000-0000-0000-1000-000000000000/settings/lanes"
      Then the response should be like:
      """
        {
            "@uuid@": {
                "name": "cambiata",
                "items": "0"
            },
            "@uuid1@": {
                "name": "prima lane",
                "items": "0"
            },
            "@uuid2@": {
                "name": "terza lane",
                "items": "0"
            }
        }
      """

  Scenario: Creating a lane
    Given that I am authenticated as "mark.rogers@ora.local"
    When I send a POST request to "/00000000-0000-0000-1000-000000000000/settings/lanes" with JSON body:
      """
      {"name": "banana"}
      """
    Then the response status code should be 201
    When I send a GET request to "/00000000-0000-0000-1000-000000000000/settings/lanes"
    Then the response should be like:
      """
        {
            "@uuid@": {
                "name": "banana",
                "items": "0"
            },
            "@uuid1@": {
                "name": "cambiata",
                "items": "0"
            },
            "@uuid2@": {
                "name": "prima lane",
                "items": "0"
            },
            "@uuid3@": {
                "name": "terza lane",
                "items": "0"
            }
        }
      """

    Scenario: Deleting a lane
    Given that I am authenticated as "mark.rogers@ora.local"
    When I send a DELETE request to "/00000000-0000-0000-1000-000000000000/settings/lanes/8d1bba08-24c5-4d63-8ee8-031475688edf"
    Then the response status code should be 200
    When I send a GET request to "/00000000-0000-0000-1000-000000000000/settings/lanes"
    Then the response should be like:
      """
        {
            "@uuid@": {
                "name": "banana",
                "items": "0"
            },
            "@uuid1@": {
                "name": "prima lane",
                "items": "0"
            },
            "@uuid2@": {
                "name": "terza lane",
                "items": "0"
            }
        }
      """
