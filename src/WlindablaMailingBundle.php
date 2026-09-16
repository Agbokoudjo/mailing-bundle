<?php

declare(strict_types=1);

/*
 * This file is part of the project by AGBOKOUDJO Franck.
 *
 * (c) AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * Phone: +229 01 67 25 18 86
 * LinkedIn: https://www.linkedin.com/in/internationales-web-apps-services-120520193/
 * Github: https://github.com/Agbokoudjo/
 * Company: INTERNATIONALES WEB APPS & SERVICES
 *
 * For more information, please feel free to contact the author.
 */

namespace Wlindabla\MailingBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Wlindabla\MailingBundle\Mailing\MailerFactory;

/**
 * Symfony bundle encapsulating the email sending infrastructure
 * (system / support-direction) with priorities, attachments, and DKIM.
 *
 * Uses AbstractBundle (Symfony 6.1+): configuration and DI wiring
 * grouped in a single class, with no separate Extension/Configuration.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 */
final class WlindablaMailingBundle extends AbstractBundle
{
    /**
     * Alias used in config/packages/wlindabla_mailing.yaml
     */
    protected string $extensionAlias = 'wlindabla_mailing';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('from_addresses')
                    ->info('Sender configuration per type (system, support, ...).')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('address')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Sender email address.')
                            ->end()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('Sender display name.')
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('dkim')
                    ->info('DKIM signing settings, exposed as container parameters for manual use via MailerFactory::applyDkimSignature().')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('private_key')
                            ->defaultNull()
                            ->info('Path to the DKIM private key (e.g. /etc/dkim/default.private or file:///...).')
                        ->end()
                        ->scalarNode('domain')
                            ->defaultNull()
                            ->info('Signing domain name (e.g. mysite.com).')
                        ->end()
                        ->scalarNode('selector')
                            ->defaultValue('default')
                            ->info('DKIM selector (e.g. default, mail, s1).')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder
            ->getDefinition(MailerFactory::class)
            ->setArgument('$fromAddresses', $config['from_addresses']);

        $builder->setParameter('wlindabla_mailing.dkim.private_key', $config['dkim']['private_key']);
        $builder->setParameter('wlindabla_mailing.dkim.domain', $config['dkim']['domain']);
        $builder->setParameter('wlindabla_mailing.dkim.selector', $config['dkim']['selector']);
    }
}