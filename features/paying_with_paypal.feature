@checkout
Feature: Paying with PayPal
    In order to pay for my order
    As a Customer
    I want to choose between PayPal and my card

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Targaryen T-Shirt" priced at "$19.99"
        And the store allows shipping with "Aardvark Stagecoach"
        And the store allows paying with "PayPal" with "PayPal" factory name
        And I am a logged in customer
        And the customer has product "Targaryen T-Shirt" in the cart
        And I am at the checkout addressing step

    @ui
    Scenario: Being offered both PayPal and card after placing the order
        When I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Aardvark Stagecoach" shipping method
        And I complete the shipping step
        And I complete the payment step
        And I confirm my order
        And I go to the PayPal payment page of my order
        Then I should be able to pay with PayPal
        And I should be able to pay by card
        And the payment page should be a part of the shop

    @ui
    Scenario: Being offered Trustly once the channel opts in
        Given the store allows paying with Trustly through PayPal
        When I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Aardvark Stagecoach" shipping method
        And I complete the shipping step
        And I complete the payment step
        And I confirm my order
        And I go to the PayPal payment page of my order
        Then I should be able to pay with Trustly
        And I should be able to pay with PayPal

    @ui
    Scenario: Completing a card payment PayPal does not challenge
        Given PayPal will approve the capture of my card payment
        When I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Aardvark Stagecoach" shipping method
        And I complete the shipping step
        And I complete the payment step
        And I confirm my order
        And I go to the PayPal payment page of my order
        And I start a card payment for my order
        And I complete the card payment
        Then the card payment should be completed

    @ui
    Scenario: A card payment PayPal declines during 3D Secure is not completed
        Given PayPal will decline the 3D Secure challenge for my card payment
        When I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Aardvark Stagecoach" shipping method
        And I complete the shipping step
        And I complete the payment step
        And I confirm my order
        And I go to the PayPal payment page of my order
        And I start a card payment for my order
        And I complete the card payment
        Then the card payment should be declined, leaving the order payable

    @ui
    Scenario: A card payment that needs a retry sends the buyer back to the payment page
        Given PayPal will ask to retry the 3D Secure challenge for my card payment
        When I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Aardvark Stagecoach" shipping method
        And I complete the shipping step
        And I complete the payment step
        And I confirm my order
        And I go to the PayPal payment page of my order
        And I start a card payment for my order
        And I complete the card payment
        Then the card payment should require a retry, returning the buyer to the payment page
