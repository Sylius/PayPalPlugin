@managing_payments
Feature: Downloading PayPal reports
    In order to get known about my PayPal sale
    As an Administrator
    I want to be able do download reports about my PayPal account

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @ui
    Scenario: Downloading PayPal payout report
        When I browse payments
        And I download PayPal report
        Then yesterday report csv file should be successfully downloaded
