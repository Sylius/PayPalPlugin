@managing_orders
Feature: Marking order as refunded via PayPal
    In order to keep order aligned with its state on PayPal
    As an Administrator
    I want to be have order marked as refunded after the PayPal-initialized refund

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Green Arrow"
        And the store ships everywhere for free
        And the store allows paying with "PayPal" with "PayPal" factory name
        And there is a customer "oliver@teamarrow.com" that placed an order "#00000001"
        And the customer bought a single "Green Arrow"
        And the customer chose "Free" shipping method to "United States" with "PayPal" payment
        And this order is already paid as "EDE12424" PayPal order
        And I am logged in as an administrator

    @ui
    Scenario: Having order marked as refunded after PayPal-initialized refund
        Given request from PayPal about "EDE12424" order refund has been received
        When I view the summary of the refunded order "#00000001"
        Then it should have payment with state refunded
        And it should have order's payment state "Refunded"
