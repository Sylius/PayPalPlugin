@checkout
Feature: Paying with the PayPal account of the channel
    In order to have my payment reach the merchant of the shop I am buying in
    As a Customer
    I want to pay with the PayPal account of the channel I am browsing

    Background:
        Given the store operates on a single channel in "United States"
        And the store allows paying with "PayPal" with "PayPal" factory name
        And the store operates on another channel named "Web-EU"
        And the store has a product "Lannister Coat" priced at "$29.99"
        And the store allows shipping with "Pigeon Mail"
        And the store allows paying with "PayPal EU" through the PayPal account "EU-CLIENT-ID"
        And I am in the "Web-EU" channel
        And I am a logged in customer
        And the customer has product "Lannister Coat" in the cart
        And I am at the checkout addressing step

    @ui
    Scenario: Being offered the PayPal account of the channel on the payment page
        When I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Pigeon Mail" shipping method
        And I complete the shipping step
        And I complete the payment step
        And I confirm my order
        And I go to the PayPal payment page of my order
        Then the payment page should use the PayPal account "EU-CLIENT-ID"

    @ui
    Scenario: Paying by card with the PayPal account of the channel
        Given every PayPal request is authorized by the PayPal account "EU-CLIENT-ID"
        And PayPal will approve the capture of my card payment
        When I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Pigeon Mail" shipping method
        And I complete the shipping step
        And I complete the payment step
        And I confirm my order
        And I go to the PayPal payment page of my order
        And I start a card payment for my order
        And I complete the card payment
        Then the card payment should be completed
