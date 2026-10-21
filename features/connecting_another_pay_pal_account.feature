@managing_payment_methods
Feature: Connecting another PayPal account
    In order to take the payments of each channel to its own PayPal account
    As an Administrator
    I want to be able to connect another PayPal account

    Background:
        Given the store operates on a single channel in "United States"
        And the store allows paying with "PayPal" with "PayPal" factory name
        And I am logged in as an administrator

    @ui
    Scenario: Being offered to connect another PayPal account
        When I browse payment methods
        Then I should be able to connect another PayPal account

    @ui
    Scenario: Being told to create a PayPal payment method through the onboarding
        When I try to create a new payment method with "PayPal" gateway factory
        Then I should be told to create the PayPal payment method through the onboarding
