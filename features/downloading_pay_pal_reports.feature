@managing_payment_methods
Feature: Downloading PayPal reports
    In order to get known about my PayPal sale
    As an Administrator
    I want to be able do download reports about my PayPal account

    Background:
        Given the store operates on a single channel in "United States"
        And the store allows paying with "PayPal" with "PayPal" factory name
        And I am logged in as an administrator

    @ui
    Scenario: Downloading PayPal payout report
        When I am browsing payment methods
        And I download report for "PayPal" payment method
        Then yesterday report's CSV file should be successfully downloaded
