@managing_payment_methods
Feature: Browsing PayPal payment methods
    In order to see at a glance how PayPal is set up
    As an Administrator
    I want to see the payment sources and the mode of a PayPal payment method in the payment method list

    Background:
        Given the store operates on a single channel in "United States"
        And the store allows paying with "PayPal" with "PayPal" factory name
        And I am logged in as an administrator

    @ui
    Scenario: Seeing the payment sources a PayPal payment method offers by default
        When I browse payment methods
        Then the "PayPal" payment method should offer "PayPal"
        And the "PayPal" payment method should offer "Pay Later"
        And the "PayPal" payment method should offer "Visa"
        And the "PayPal" payment method should not offer "Venmo"
        And the "PayPal" payment method should not offer "Trustly"
        And the "PayPal" payment method should be marked as working in sandbox mode
        And I should not see PayPal suggested as a payment method

    @ui
    Scenario: Seeing a payment source enabled in the PayPal configuration
        Given the store allows paying with Trustly through PayPal
        When I browse payment methods
        Then the "PayPal" payment method should offer "Trustly"

    @ui
    Scenario: Seeing the payment sources beyond the first six under "and more"
        Given the store allows paying with Venmo through PayPal
        And the store allows paying with Apple Pay through PayPal
        And the store allows paying with Google Pay through PayPal
        And the store allows paying with Trustly through PayPal
        When I browse payment methods
        Then the "PayPal" payment method should offer "Trustly"
        And the "PayPal" payment method should not offer "Visa"
        And the "PayPal" payment method should hide "Visa, Mastercard, American Express" under "and more"
