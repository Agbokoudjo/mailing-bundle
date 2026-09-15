# Wlindabla Mailing Bundle

Symfony bundle to centralize the sending of transactional (`system`) emails
and support/direction (`support`) emails, with Twig templates, priorities,
attachments, inline image embedding, and DKIM signing.

Compatible with **PHP 8.1+** and **Symfony 6.4 / 7.x / 8.x**.

## Installation

```bash
composer require wlindabla/mailing-bundle
```

If `symfony/flex` doesn't enable the bundle automatically, add it to
`config/bundles.php`:

```php
return [
    // ...
    Wlindabla\MailingBundle\WlindablaMailingBundle::class => ['all' => true],
];
```

## Prerequisites: mailer transports

The bundle only orchestrates email creation and sending — it relies on the
`system` and `support` transports being configured on Symfony's own
`framework.mailer`, typically in `config/packages/mailer.yaml`:

```yaml
framework:
    mailer:
        transports:
            # 1. Transport for the system (notifications)
            system: '%env(SYSTEM_MAILER_DSN)%'

            # 2. Transport for support (client forms)
            support: '%env(SUPPORT_MAILER_DSN)%'
        dkim_signer:
            key: 'file://%kernel.project_dir%/var/certificates/dkim.pem'
            domain: 'yourdomain.com'
            select: 's1'
```

Note: `framework.mailer.dkim_signer` is Symfony's own native DKIM signing,
applied transport-wide to every outgoing email. It is separate from the
bundle's `wlindabla_mailing.dkim` block described below, which only exposes
parameters for the optional, manual `MailerFactory::applyDkimSignature()`
call (see the open note on DKIM further down).

## Configuration

`config/packages/wlindabla_mailing.yaml`:

```yaml
wlindabla_mailing:
    from_addresses:
        system:
            address: '%env(APP_MAIL_FROM_SYSTEM)%'
            name: '%env(APP_MAIL_FROM_SYSTEM_NAME)%'
        support:
            address: '%env(APP_MAIL_FROM_SUPPORT)%'
            name: '%env(APP_MAIL_FROM_SUPPORT_NAME)%'

    dkim:
        private_key: '%env(resolve:default::MAILER_DKIM)%'
        domain: '%env(APP_DKIM_DOMAIN)%'
        selector: 'default'
```

`from_addresses` accepts an arbitrary key per sender type (`system`,
`support`, or any other business name); each bundle service knows which type
to read via its own `CONFIG_TYPE` constant.

The `dkim` block is exposed as container parameters
(`wlindabla_mailing.dkim.private_key`, `.domain`, `.selector`) for manual use
with `MailerFactory::applyDkimSignature()` — see the DKIM section below.

## Provided services

| Interface | Implementation | Role |
|---|---|---|
| `MailerFactoryInterface` | `MailerFactory` | Low-level `Email` construction/sending, validation, DKIM |
| `EmailSenderInterface` | `SystemMailer` | Automated system emails (confirmation, password reset...) |
| `SupportMailerInterface` | `SupportMailer` | Support/direction emails with dynamic Reply-To |
| `MailerManagerInterface` | `MailerManagerEntreprise` | High-level facade orchestrating the two mailers above |

All services are `autowire`/`autoconfigure`, `public: false`. Inject the
interface that matches your need:

```php
use Wlindabla\MailingBundle\Mailing\MailerManagerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;

final class RegistrationHandler
{
    public function __construct(
        private readonly MailerManagerInterface $mailerManager,
    ) {}

    public function onUserRegistered(User $user, string $token): void
    {
        $this->mailerManager->notifyBySystem(
            $user->getEmail(),
            'Activate your account',
            'emails/register_confirmation.html.twig',
            ['user' => $user, 'token' => $token],
            PriorityInterface::PRIORITY_HIGH,
        );
    }
}
```

## Open note: DKIM and the `MailerFactoryInterface` interface

`MailerFactory::applyDkimSignature()` and `addTextHeader()` exist on the
concrete implementation but **are not declared on `MailerFactoryInterface`**.
This is carried over as-is from the original implementation (namespace
change only, no logic change). In practice, a consumer who type-hints
against the interface cannot call `applyDkimSignature()` without casting to
the concrete class.

Two options going forward, to be decided based on how DKIM is actually used
in your projects:

1. Add both methods to `MailerFactoryInterface` (if DKIM signing must remain
   callable through the bundle's public interface).
2. Keep them off the interface and document them as an internal
   implementation detail, reserved for consumers who type-hint
   `MailerFactory` directly.

The current bundle makes no decision on your behalf: these methods are
present but are not invoked automatically anywhere (neither in `sendNow()`
nor in `sendAsync()`).

## License

MIT — see [LICENSE](LICENSE).