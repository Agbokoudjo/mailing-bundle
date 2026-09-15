<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Wlindabla\MailingBundle\Mailing\SystemMailerInterface;
use Wlindabla\MailingBundle\Mailing\MailerFactory;
use Wlindabla\MailingBundle\Mailing\MailerFactoryInterface;
use Wlindabla\MailingBundle\Mailing\MailerManagerEntreprise;
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\SupportMailer;
use Wlindabla\MailingBundle\Mailing\SupportMailerInterface;
use Wlindabla\MailingBundle\Mailing\SystemMailer;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->public(false)
    ;

    // MailerFactory: $fromAddresses is overridden in WlindablaMailingBundle::loadExtension()
    // from the "wlindabla_mailing.from_addresses" config once merged/processed.
    $services->set(MailerFactory::class);
    $services->alias(MailerFactoryInterface::class, MailerFactory::class)->public(false);

    $services->set(SystemMailer::class);
    $services->alias(SystemMailerInterface::class, SystemMailer::class)->public(false);

    $services->set(SupportMailer::class);
    $services->alias(SupportMailerInterface::class, SupportMailer::class)->public(false);

    $services->set(MailerManagerEntreprise::class);
    $services->alias(MailerManagerInterface::class, MailerManagerEntreprise::class)->public(false);
};
