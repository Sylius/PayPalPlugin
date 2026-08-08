@managing_payment_methods
Feature: Managing multiple PayPal payment methods
    In order to switch between PayPal configurations
    As an Administrator
    I want to have multiple PayPal methods but only one enabled at a time

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Cannot create and enable a new PayPal method when another is already enabled
        Given the store allows paying with "PayPal Sandbox" with "PayPal" factory name
        When I create a new PayPal payment method "PayPal Production" and try to save it as enabled
        Then I should see a validation error that only one PayPal method can be enabled
        And the PayPal payment method "PayPal Production" should not exist

    @ui
    Scenario: Cannot enable an existing PayPal method when another is already enabled
        Given the store allows paying with "PayPal Sandbox" with "PayPal" factory name
        And the store has a disabled "PayPal Production" payment method with "PayPal" gateway factory
        When I try to enable the PayPal payment method "PayPal Production"
        Then I should see a validation error that only one PayPal method can be enabled
        And the PayPal payment method "PayPal Production" should still be disabled

    @ui
    Scenario: Can create and enable a new PayPal method when no other is enabled
        Given the store has a disabled "PayPal Sandbox" payment method with "PayPal" gateway factory
        When I create a new PayPal payment method "PayPal Production" and save it as enabled
        Then the new PayPal payment method should be in the list and enabled
