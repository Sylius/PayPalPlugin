<?php

namespace Sylius\PayPalPlugin\Model;

use Webmozart\Assert\Assert;

class PaymentMethod
{
    const ANY_PAYMENT = 'UNRESTRICTED';
    const IMMEDIATE_PAYMENT = 'IMMEDIATE_PAYMENT_REQUIRED';

    const TEL_SEC = 'TEL';
    const WEB_SEC = 'WEB';
    const CCD_SEC = 'CCD';
    const PPD_SEC = 'PPD';

    /** @var string */
    private $payerSelected;
    /** @var string */
    private $payeePreferred;
    /** @var string */
    private $standardEntryClassCode;

    public function __construct(
        string $payeePreferred,
        ?string $payerSelected = null,
        ?string $standardEntryClassCode = null
    ) {
        $this->payeePreferred = $payeePreferred;
        $this->payerSelected = $payerSelected;
        $this->standardEntryClassCode = $standardEntryClassCode;
    }

    public function toArray(): array
    {
        $paymentMethod = [
            'payee_preferred' => $this->getPayeePreferred()
        ];

        if(!is_null($this->payerSelected)) {
            $paymentMethod['payer_selected'] = $this->getPayerSelected();
        }

        if(!is_null($this->standardEntryClassCode)) {
            $paymentMethod['standard_entry_class_code'] = $this->getStandardEntryClassCode();
        }

        return $paymentMethod;
    }

    private function getPayeePreferred(): string
    {
        Assert::inArray($this->payeePreferred, [self::ANY_PAYMENT, self::IMMEDIATE_PAYMENT]);
        return $this->payeePreferred;
    }

    private function getPayerSelected(): string
    {
        Assert::minLength($this->payerSelected, 1);
        Assert::regex($this->payerSelected, "^[0-9A-Z_]+$");
        return $this->payerSelected;
    }

    private function getStandardEntryClassCode(): string
    {
        Assert::inArray($this->standardEntryClassCode, [self::TEL_SEC, self::CCD_SEC, self::PPD_SEC, self::WEB_SEC]);
        return $this->standardEntryClassCode;
    }
}