@managing_payment_methods
Feature: Trying to onboard more than one PayPal seller
    In order to handle PayPal integration properly
    As an Administrator
    I want to be prevented from onboarding more than one PayPal seller

    Background:
        Given the store operates on a single channel in "United States"
        And the store allows paying with "PayPal" with "PayPal" factory name
        And I am logged in as an administrator

    @ui
    Scenario: Trying to onboard second PayPal seller
        When I try to create a new payment method with "PayPal" gateway factory
        Then I should be notified that I cannot onboard more than one PayPal seller

    @ui
    Scenario: Being able to create different payment methods
        When I want to create a new offline payment method
        Then I should not be notified that I cannot onboard more than one PayPal seller
