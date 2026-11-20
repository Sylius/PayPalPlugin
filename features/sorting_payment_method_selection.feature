@checkout
Feature: Sorting payment methods by position during checkout
    In order to see the payment methods in the order the store chose
    As a Customer
    I want to have them sorted by their position

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Targaryen T-Shirt" priced at "$19.99"
        And the store allows shipping with "Aardvark Stagecoach"
        And the store allows paying with "Paypal Express Checkout" at position 0
        And the store allows paying with "Cash on Delivery" at position 2
        And the store allows paying with "Offline" at position 1

    @ui
    Scenario: Seeing PayPal after the payment methods placed before it
        Given the store allows paying with "PayPal" with "PayPal" factory name at position 4
        And I am a logged in customer
        And the customer has product "Targaryen T-Shirt" in the cart
        When I am at the checkout addressing step
        And I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Aardvark Stagecoach" shipping method
        And I complete the shipping step
        Then I should have "Paypal Express Checkout" payment method available as the first choice
        And I should have "Paypal Express Checkout" payment method selected

    @ui
    Scenario: Seeing PayPal first when the store placed it first
        Given the store allows paying with "PayPal" with "PayPal" factory name at position 0
        And I am a logged in customer
        And the customer has product "Targaryen T-Shirt" in the cart
        When I am at the checkout addressing step
        And I specify the billing address as "Ankh Morpork", "Frost Alley", "90210", "United States" for "Jon Snow"
        And I complete the addressing step
        And I select "Aardvark Stagecoach" shipping method
        And I complete the shipping step
        Then I should have "PayPal" payment method available as the first choice
        And I should have "PayPal" payment method selected
