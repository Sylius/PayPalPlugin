<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\PackageTracking\Form\Type;

use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\PayPalPlugin\PackageTracking\Provider\CarrierProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ShipmentTrackingType extends AbstractType
{
    private const CARRIER_LABEL_PREFIX = 'sylius_paypal.carrier.';

    private const TRANSLATION_DOMAIN = 'messages';

    public function __construct(
        private readonly CarrierProviderInterface $carrierProvider,
        private readonly TranslatorInterface&TranslatorBagInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('carrier', ChoiceType::class, [
                'label' => 'sylius_paypal.form.shipment.carrier',
                'required' => false,
                'placeholder' => 'sylius_paypal.form.shipment.select_carrier',
                'choices' => $this->getCarrierChoices(),
                'choice_translation_domain' => self::TRANSLATION_DOMAIN,
            ])
            ->add('carrier_name_other', TextType::class, [
                'label' => 'sylius_paypal.form.shipment.carrier_name_other',
                'property_path' => 'carrierNameOther',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShipmentTrackingData::class,
            'label' => false,
            'constraints' => [new Valid()],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'sylius_paypal_shipment_tracking';
    }

    /** @return array<string, string> */
    private function getCarrierChoices(): array
    {
        $catalogue = $this->translator->getCatalogue();
        $choices = [];

        foreach ($this->carrierProvider->getCarrierCodes() as $code) {
            $label = self::CARRIER_LABEL_PREFIX . $code;
            $choices[$catalogue->has($label, self::TRANSLATION_DOMAIN) ? $label : $code] = $code;
        }

        return $choices;
    }
}
