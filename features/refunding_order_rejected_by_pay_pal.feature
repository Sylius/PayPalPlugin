@managing_orders
Feature: Refunding an order that PayPal does not refund
    In order to know that the money has not been returned to the Customer
    As an Administrator
    I want to see why PayPal rejected the refund

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Green Arrow"
        And the store ships everywhere for free
        And the store allows paying with "PayPal" with "PayPal" factory name
        And there is a customer "oliver@teamarrow.com" that placed an order "#00000001"
        And the customer bought a single "Green Arrow"
        And the customer chose "Free" shipping method to "United States" with "PayPal" payment
        And this order is already paid as "EDE12424" PayPal order whose refund PayPal rejects
        And I am logged in as an administrator

    @ui
    Scenario: Being notified that PayPal rejected the refund
        Given I am viewing the summary of this order
        When I mark this order's payment as refunded
        Then I should be notified that PayPal rejected the refund
        And it should have payment with state completed
        And it should have order's payment state "Paid"
