@managing_orders
Feature: Refunding an order from the Admin panel
    In order to return the money to the Customer
    As an Administrator
    I want to be able to refund a PayPal-paid order

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Green Arrow"
        And the store ships everywhere for free
        And the store allows paying with "PayPal" with "PayPal" factory name
        And there is a customer "oliver@teamarrow.com" that placed an order "#00000001"
        And the customer bought a single "Green Arrow"
        And the customer chose "Free" shipping method to "United States" with "PayPal" payment
        And this order is already paid as "EDE12424" PayPal order with "RRE33212" PayPal payment
        And I am logged in as an administrator

    @ui
    Scenario: Initializing the PayPal refund
        Given I am viewing the summary of this order
        When I mark this order's payment as refunded
        Then I should be notified that the order's payment has been successfully refunded
        And it should have payment with state refunded
        And it's payment state should be refunded
