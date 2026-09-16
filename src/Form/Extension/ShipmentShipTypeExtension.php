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

namespace Sylius\PayPalPlugin\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\ShipmentShipType;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\Provider\CarrierProviderInterface;
use Sylius\PayPalPlugin\Provider\OrderPayPalPaymentProviderInterface;
use Sylius\PayPalPlugin\Repository\ShipmentTrackingRepositoryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ShipmentShipTypeExtension extends AbstractTypeExtension
{
    private const CARRIER_LABEL_PREFIX = 'sylius_paypal.carrier.';

    private const TRANSLATION_DOMAIN = 'messages';

    private const LIVE_COMPONENT_ROUTE = 'ux_live_component';

    public function __construct(
        private readonly CarrierProviderInterface $carrierProvider,
        private readonly ShipmentTrackingRepositoryInterface $shipmentTrackingRepository,
        private readonly ShipmentTrackingManagerInterface $shipmentTrackingManager,
        private readonly OrderPayPalPaymentProviderInterface $orderPayPalPaymentProvider,
        private readonly TranslatorInterface&TranslatorBagInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('carrier', ChoiceType::class, [
                'label' => 'sylius_paypal.form.shipment.carrier',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'sylius_paypal.form.shipment.select_carrier',
                'choices' => $this->getCarrierChoices(),
                'choice_translation_domain' => self::TRANSLATION_DOMAIN,
            ])
            ->add('carrier_name_other', TextType::class, [
                'label' => 'sylius_paypal.form.shipment.carrier_name_other',
                'mapped' => false,
                'required' => false,
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'prefillCarrier']);
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'validateAndPersistCarrier'], -10);
    }

    public function prefillCarrier(FormEvent $event): void
    {
        $shipment = $event->getData();
        if (!$shipment instanceof ShipmentInterface) {
            return;
        }

        $tracking = $this->shipmentTrackingRepository->findOneByShipment($shipment);
        if (null === $tracking) {
            return;
        }

        $form = $event->getForm();
        $form->get('carrier')->setData($tracking->getCarrier());
        $form->get('carrier_name_other')->setData($tracking->getCarrierNameOther());
    }

    public function validateAndPersistCarrier(FormEvent $event): void
    {
        $form = $event->getForm();
        $shipment = $event->getData();
        if (!$shipment instanceof ShipmentInterface || !$form->isValid()) {
            return;
        }

        if ($this->isLiveComponentRerender()) {
            return;
        }

        $order = $shipment->getOrder();
        if (!$order instanceof OrderInterface || null === $this->orderPayPalPaymentProvider->provide($order)) {
            return;
        }

        $trackingNumber = $shipment->getTracking();
        $carrier = $this->stringOrNull($form->get('carrier')->getData());
        $carrierNameOther = $this->stringOrNull($form->get('carrier_name_other')->getData());

        if (null !== $trackingNumber && '' !== $trackingNumber && null === $carrier) {
            $form->get('carrier')->addError(new FormError('sylius_paypal.shipment_tracking.carrier_required'));

            return;
        }

        if (null !== $carrier && null === $carrierNameOther && $this->carrierProvider->isOther($carrier)) {
            $form->get('carrier_name_other')->addError(new FormError('sylius_paypal.shipment_tracking.carrier_name_other_required'));

            return;
        }

        if (null !== $carrier) {
            $this->shipmentTrackingManager->updateCarrier($shipment, $carrier, $carrierNameOther);
        }
    }

    public static function getExtendedTypes(): iterable
    {
        yield ShipmentShipType::class;
    }

    private function isLiveComponentRerender(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request && self::LIVE_COMPONENT_ROUTE === $request->attributes->get('_route');
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

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
