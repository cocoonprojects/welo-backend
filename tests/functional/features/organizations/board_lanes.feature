Feature: Lanes management in Welo
  As a user
  I want to create manage board lanes inside welo

    @lanes
    Scenario: Create organization lanes settings
      Given that I am authenticated as "mark.rogers@ora.local"
      When I send a POST request to "/00000000-0000-0000-1000-000000000000/settings/lanes" with JSON body:
      """
      {"name": "banana"}

      """
      Then the response status code should be 201

      When I send a GET request to "/00000000-0000-0000-1000-000000000000/settings/lanes"
      Then echo last response