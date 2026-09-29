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

namespace Sylius\PayPalPlugin\PackageTracking\Twig\Component;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Controller\AuthorizationCheckerInterface;
use Sylius\Bundle\ResourceBundle\Controller\EventDispatcherInterface;
use Sylius\Bundle\ResourceBundle\Controller\FlashHelperInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceUpdateHandlerInterface;
use Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponentTrait;
use Sylius\Bundle\UiBundle\Twig\Component\TemplatePropTrait;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Form\Extension\ShipmentShipTypeExtension;
use Sylius\PayPalPlugin\PackageTracking\Manager\ShipmentTrackingManagerInterface;
use Sylius\PayPalPlugin\PackageTracking\Model\ShipmentTrackingData;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Exception\UpdateHandlingException;
use Sylius\Resource\Metadata\RegistryInterface;
use Sylius\Resource\ResourceActions;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;

#[AsLiveComponent]
final class ShipmentShipFormComponent
{
    /** @use ResourceFormComponentTrait<ShipmentInterface> */
    use ResourceFormComponentTrait;

    use TemplatePropTrait;

    public const REDIRECT_TO_ORDER = 'order';

    public const REDIRECT_TO_INDEX = 'index';

    /**
     * @param RepositoryInterface<ShipmentInterface> $shipmentRepository
     * @param class-string $shipmentClass
     * @param class-string $formClass
     */
    public function __construct(
        RepositoryInterface $shipmentRepository,
        FormFactoryInterface $formFactory,
        string $shipmentClass,
        string $formClass,
        private readonly RegistryInterface $resourceRegistry,
        private readonly RequestConfigurationFactoryInterface $requestConfigurationFactory,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ResourceUpdateHandlerInterface $resourceUpdateHandler,
        private readonly FlashHelperInterface $flashHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentTrackingManagerInterface $shipmentTrackingManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->initialize($shipmentRepository, $formFactory, $shipmentClass, $formClass);
    }

    #[LiveAction]
    public function ship(#[LiveArg] string $redirectTo = self::REDIRECT_TO_ORDER): RedirectResponse
    {
        $this->submitForm();

        /** @var ShipmentInterface $shipment */
        $shipment = $this->getForm()->getData();
        $configuration = $this->requestConfiguration($redirectTo);

        if (!$this->authorizationChecker->isGranted($configuration, $configuration->getPermission(ResourceActions::UPDATE))) {
            throw new AccessDeniedException();
        }

        $event = $this->eventDispatcher->dispatchPreEvent(ResourceActions::UPDATE, $configuration, $shipment);
        if ($event->isStopped()) {
            $this->flashHelper->addFlashFromEvent($configuration, $event);

            return $this->redirect($shipment, $redirectTo);
        }

        $this->entityManager->beginTransaction();

        try {
            $trackingData = $this->trackingData();
            if (null !== $trackingData?->getCarrier()) {
                $this->shipmentTrackingManager->updateCarrier($shipment, $trackingData->getCarrier(), $trackingData->getCarrierNameOther());
            }

            $this->resourceUpdateHandler->handle($shipment, $configuration, $this->entityManager);
            $this->entityManager->commit();
        } catch (UpdateHandlingException $exception) {
            $this->entityManager->rollback();
            $this->flashHelper->addErrorFlash($configuration, $exception->getFlash());

            return $this->redirect($shipment, $redirectTo);
        }

        $this->flashHelper->addSuccessFlash($configuration, ResourceActions::UPDATE, $shipment);
        $this->eventDispatcher->dispatchPostEvent(ResourceActions::UPDATE, $configuration, $shipment);

        return $this->redirect($shipment, $redirectTo);
    }

    protected function getDataModelValue(): string
    {
        return 'norender|*';
    }

    private function trackingData(): ?ShipmentTrackingData
    {
        $form = $this->getForm();
        if (!$form->has(ShipmentShipTypeExtension::TRACKING_FIELD_NAME)) {
            return null;
        }

        $trackingData = $form->get(ShipmentShipTypeExtension::TRACKING_FIELD_NAME)->getData();

        return $trackingData instanceof ShipmentTrackingData ? $trackingData : null;
    }

    private function requestConfiguration(string $redirectTo): RequestConfiguration
    {
        $parameters = [
            'event' => 'ship',
            'section' => 'admin',
            'permission' => true,
            'state_machine' => ['graph' => 'sylius_shipment', 'transition' => 'ship'],
        ];
        if (self::REDIRECT_TO_INDEX === $redirectTo) {
            $parameters['flash'] = 'sylius.shipment.shipped';
        }

        return $this->requestConfigurationFactory->create(
            $this->resourceRegistry->get('sylius.shipment'),
            new Request(attributes: ['_sylius' => $parameters]),
        );
    }

    private function redirect(ShipmentInterface $shipment, string $redirectTo): RedirectResponse
    {
        if (self::REDIRECT_TO_INDEX === $redirectTo) {
            return new RedirectResponse($this->urlGenerator->generate('sylius_admin_shipment_index'));
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_admin_order_show', ['id' => $shipment->getOrder()?->getId()]));
    }
}
