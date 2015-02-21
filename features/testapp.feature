Feature: Test json rpc client verification
  In order to test the extension easily
  As a JsonRpcApi feature tester
  I want to be able to find features automatically


  Scenario: Sending simple request
    Given a file named "features/send_request.feature" with:
      """
      Feature: Exercise JsonRpc request sending
        In order to validate the send request step
        As a context developer
        I need to be able to send a request with values in a scenario
        Scenario:
          Given I set request id "125"
          When  I send a request to "test.app" with params:
            | name      | Elena   |
            | lastname  | Berkova |
          Then response is successfully with contain result:
            | name      | Elena   |
            | lastname  | Berkova |
      """
    When I run "behat features/send_request.feature"
    Then it should pass with:
      """
      1 scenario (1 passed)
      """

  Scenario: Get error
    Given a file named "features/send_request_error.feature" with:
      """
      Feature: Response with error
        In order to validate the send request step
        As a context developer
        I need to be able to check error Id and message
        Scenario:
          Given I set request id "125"
          When  I send a request to "test.app.notfound" with params:
            | name      | Elena   |
          Then response should be error with id "-32601", message "Method not found"
      """
    When I run "behat features/send_request_error.feature"
    Then it should pass with:
      """
      1 scenario (1 passed)
      """

  Scenario: Get error with data
    Given a file named "features/send_request_error_data.feature" with:
      """
      Feature: Response with error
        In order to validate the send request step
        As a context developer
        I need to be able to check error Id and message
        Scenario:
          Given I set request id "125"
          When  I send a request to "test.errorWithData" with params:
            | errorCode  | -400  |
            | message    | Param validation error  |
            | data       | {"name": "Must be shortly"} |
          Then response should be error with id "-400", message "Param validation error", data:
            | name  |  Must be shortly |
      """
    When I run "behat features/send_request_error_data.feature"
    Then it should pass with:
      """
      1 scenario (1 passed)
      """



Background:
     Given a file named "behat.yml" with:
       """
       default:
           formatters:
               progress: ~
           extensions:
               Solution\JsonRpcApiExtension:
                   base_url: http://127.0.0.1:8080/json-rpc
           suites:
               default:
                   contexts: ['Solution\JsonRpcApiExtension\Context\JsonRpcClientContext']
       """