@managing_orders
Feature: Managing orders paid with the PayPal account of their channel
    In order to keep orders of every channel aligned with the merchant's PayPal account
    As an Administrator
    I want orders to be refunded and notified about through the PayPal account of their channel

    Background:
        Given the store operates on a single channel in "United States"
        And the store allows paying with "PayPal" with "PayPal" factory name
        And the store operates on another channel named "Web-EU"
        And the store has a product "Green Arrow"
        And the store ships everywhere for free
        And the store allows paying with "PayPal EU" through the PayPal account "EU-CLIENT-ID"
        And there is a customer "oliver@teamarrow.com" that placed an order "#00000001"
        And the customer bought a single "Green Arrow"
        And the customer chose "Free" shipping method to "United States" with "PayPal EU" payment
        And every PayPal request is authorized by the PayPal account "EU-CLIENT-ID"
        And I am logged in as an administrator

    @ui
    Scenario: Refunding an order through the PayPal account of its channel
        Given this order is already paid as "EDE12424" PayPal order with "RRE33212" PayPal payment
        And I am viewing the summary of this order
        When I mark this order's payment as refunded
        Then I should be notified that the order's payment has been successfully refunded
        And it should have payment with state refunded

    @ui
    Scenario: Having an order refunded by a webhook of the PayPal account of its channel
        Given this order is already paid as "EDE12424" PayPal order with "CAPTURE_ID" PayPal capture
        When PayPal notifies the "PayPal EU" payment method that the "CAPTURE_ID" capture has been refunded
        And I view the summary of the refunded order "#00000001"
        Then it should have payment with state refunded
