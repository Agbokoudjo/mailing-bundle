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
call.

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

## Architecture overview
MailerManagerInterface ← recommended entry point for application code
├── SystemMailerInterface ← automated, one-way notifications
└── SupportMailerInterface ← interactive, reply-able correspondence both rely on
MailerFactoryInterface ← low-level Email construction / sending


In most application code you should depend on **`MailerManagerInterface`**.
Depend on `SystemMailerInterface` or `SupportMailerInterface` directly only
when a service has a single, well-defined mailing responsibility and
pulling in the full manager would be overkill. Depend on
`MailerFactoryInterface` only when building your own mailer service on top
of the bundle.

---

## `MailerFactoryInterface` / `MailerFactory`

**Role**: low-level building block. Creates `Email` objects from Twig
templates, validates sender/recipient addresses and the subject, enforces
priority bounds, sends emails through Symfony Mailer, and can apply a manual
DKIM signature.

**When to use it directly**: rarely from application code. Use it when you
need full control over the `Email` object before sending (custom headers,
non-standard attachment handling, conditional DKIM signing), or when you are
building a new mailer service on top of the bundle. For everyday
notifications, use `SystemMailerInterface`, `SupportMailerInterface`, or
`MailerManagerInterface` instead — they already wrap this factory correctly.

### Methods

| Method | Purpose |
|---|---|
| `createTemplateEmail(string $senderAddress, string $senderName, string\|array $recipientAddress, string $subject, string $templatePath, ?array $context): Email` | Builds a `TemplatedEmail` from a Twig template. Validates addresses and subject. Does **not** send it. |
| `sendNow(Email $email): void` | Sends immediately through `MailerInterface::send()`. |
| `sendAsync(Email $email): void` | Also calls `MailerInterface::send()`. See the behavior note below. |
| `fromConfig(string $type = 'system'): array` | Returns `['address' => ..., 'name' => ...]` for a configured sender type (`wlindabla_mailing.from_addresses`). Throws `RuntimeException` if the type is unknown. |
| `validatePriority(int $priority): void` | Throws `InvalidArgumentException` if the priority is outside `PriorityInterface::PRIORITY_HIGHEST`..`PRIORITY_LOWEST`. |
| `applyDkimSignature(Email $email, string $domainName, ?string $dkimKey = null): Email\|Message` | Manually signs an email with DKIM. Returns the original `$email` unchanged if `$dkimKey` is null or `$domainName` is empty. |
| `addTextHeader(Email $email, string $type, string $name, $body): Email` | Adds a custom text header to the email. |

> `applyDkimSignature()` and `addTextHeader()` are declared on `MailerFactory`
> but not on `MailerFactoryInterface`. Calling them requires type-hinting the
> concrete class rather than the interface — this is an open design
> decision, not yet resolved (see previous discussion).

### Behavior note: `sendNow()` vs `sendAsync()`

In the current implementation, `sendAsync()` simply calls `sendNow()`:

```php
public function sendAsync(Email $email): void
{
    $this->sendNow($email);
}
```

This is not necessarily a bug: Symfony's `Mailer::send()` internally
dispatches a `MessageEvent`, and if a Messenger bus is routed for
`Symfony\Component\Mailer\Messenger\SendEmailMessage` (in
`config/packages/messenger.yaml`), a listener intercepts that event and
queues the message instead of sending it immediately. In other words: **the
sync/async distinction is controlled by your Messenger routing
configuration, not by which factory method is called.**

Practical consequence: `SystemMailer` and `SupportMailer` both always call
`sendAsync()`. If you don't configure Messenger routing for
`SendEmailMessage`, every email — including "system" ones — is sent
synchronously despite the method name. If you do route it, both go through
the queue, and a listener such as `MessengerEmailFailureListener` (handling
`WorkerMessageFailedEvent`) becomes meaningful for retry/bounce handling.

### Example

```php
use Wlindabla\MailingBundle\Mailing\MailerFactoryInterface;

final class CustomInvoiceMailer
{
    public function __construct(
        private readonly MailerFactoryInterface $mailerFactory,
    ) {}

    public function sendInvoice(string $to, string $invoiceNumber, array $context): void
    {
        $from = $this->mailerFactory->fromConfig('support');

        $email = $this->mailerFactory->createTemplateEmail(
            $from['address'],
            $from['name'],
            $to,
            sprintf('Invoice #%s', $invoiceNumber),
            'emails/invoice.html.twig',
            $context,
        );

        $email->attachFromPath('/var/invoices/' . $invoiceNumber . '.pdf', 'Invoice.pdf');

        $this->mailerFactory->sendNow($email);
    }
}
```

---

## `SystemMailerInterface` / `SystemMailer`

**Role**: automated, one-way system notifications. Reads the `system`
sender configuration, validates priority, sends the email, and logs
success/failure.

**When to use it**: account activation, password reset, 2FA codes, security
alerts, account status changes — any notification the recipient is not
expected to reply to directly.

### Method

| Method | Purpose |
|---|---|
| `send(string\|array $recipientAddress, string $subject, string $templatePath, array $context, int $priority = PriorityInterface::PRIORITY_HIGH): void` | Builds and sends a system email using the `system` sender configuration. |

### Example

```php
use Wlindabla\MailingBundle\Mailing\SystemMailerInterface;
use Wlindabla\MailingBundle\Mailing\PriorityInterface;

final class PasswordResetHandler
{
    public function __construct(
        private readonly SystemMailerInterface $systemMailer,
    ) {}

    public function sendResetLink(User $user, string $token): void
    {
        $this->systemMailer->send(
            $user->getEmail(),
            'Reset your password',
            'emails/security/reset_password.html.twig',
            ['user' => $user, 'token' => $token],
            PriorityInterface::PRIORITY_HIGHEST,
        );
    }
}
```

---

## `SupportMailerInterface` / `SupportMailer`

**Role**: interactive, reply-able correspondence via the `support` sender
configuration. Its distinctive feature is the **Reply-To mechanism**: when
notifying an internal team about a client action, the client's own email
address can be injected into `Reply-To`, so a staff member can hit "Reply"
in their inbox and reach the client directly — no extra step through the
application.

**When to use it**: contact form submissions, document/manuscript
uploads notified to a team, official outgoing correspondence signed by
support/direction, anything involving attachments or inline images sent to
or from a human who might reply.

### Method

| Method | Purpose |
|---|---|
| `sendManager(string\|array $recipientEmail, string $subject, string $htmlTemplate, ?array $context, ?string $senderEmail, ?string $replyToEmail, array $attachments = []): void` | Builds and sends a support email. Attachments matching an image extension (`jpg`, `jpeg`, `png`, `gif`, `svg`) are embedded inline; others are attached as files. |

`$attachments` format: `['path/to/file' => 'Display_Name.ext']`.

### Example — notifying internal staff, with client Reply-To

```php
use Wlindabla\MailingBundle\Mailing\SupportMailerInterface;

final class ManuscriptSubmissionHandler
{
    public function __construct(
        private readonly SupportMailerInterface $supportMailer,
    ) {}

    public function notifyEditorialTeam(ManuscriptSubmission $submission): void
    {
        $this->supportMailer->sendManager(
            'editorial-team@yourdomain.com',
            'New manuscript submission',
            'emails/notification_submission.html.twig',
            ['submission' => $submission],
            null,                            // sender: uses the 'support' config default
            $submission->getEmail(),         // Reply-To: the submitting author
            ['/var/uploads/' . $submission->getFilePath() => 'Manuscript.pdf'],
        );
    }
}
```

---

## `MailerManagerInterface` / `MailerManagerEntreprise`

**Role**: the **recommended, high-level entry point** for application code.
It is a thin facade composing `SystemMailerInterface` and
`SupportMailerInterface`, so business/application services only depend on
one interface and don't need to reason about which underlying mailer
(system vs. support) or sender configuration to pick — each method already
encodes that choice.

**When to use it**: as the default choice for any service that needs to
send an email. Prefer this over injecting `SystemMailerInterface` or
`SupportMailerInterface` directly, unless a service is deliberately scoped
to a single mailing concern.

### Methods

#### `notifyBySystem()`

```php
public function notifyBySystem(
    string|array $recipientAddress,
    string $subject,
    string $templatePath,
    array $context,
    int $priority = PriorityInterface::PRIORITY_NORMAL,
): void;
```

Delegates to `SystemMailerInterface::send()`. Use it for automated,
one-way notifications where the recipient is a user of your platform and no
reply is expected — account activation, password resets, security alerts.

```php
$this->mailerManager->notifyBySystem(
    $user->getEmail(),
    'Activate your account',
    'emails/register_confirmation.html.twig',
    ['user' => $user, 'token' => $token],
    PriorityInterface::PRIORITY_HIGH,
);
```

#### `notifyToDirection()`

```php
public function notifyToDirection(
    string|array $directionEmail,
    string $subject,
    string $htmlTemplate,
    ?array $context = null,
    ?string $clientEmail = null,
    ?array $attachments = [],
): void;
```

Delegates to `SupportMailerInterface::sendManager()`, using the `support`
sender configuration. Use it to alert an internal team or the direction
about something a client did on the platform (a form was filled, a file was
uploaded). Passing `$clientEmail` sets it as `Reply-To`, so staff can reply
straight to the client from their own mail client.

```php
$this->mailerManager->notifyToDirection(
    'secretariat@yourdomain.com',
    'New manuscript submission',
    'emails/notification_submission.html.twig',
    ['submission' => $manuscriptSubmission],
    $manuscriptSubmission->getEmail(),                    // client's email → Reply-To
    ['/var/uploads/doc.pdf' => 'Manuscript_Author.pdf'],
);
```

#### `sendMailByDirection()`

```php
public function sendMailByDirection(
    string|array $recipientEmail,
    string $subject,
    string $htmlTemplate,
    ?array $context = null,
    ?string $replyToEmail = null,
    ?array $attachments = [],
): void;
```

Also delegates to `SupportMailerInterface::sendManager()`, but for the
opposite direction: an official, outgoing message signed by
support/direction and sent **to** a client or external third party (e.g.
`support@yourdomain.com` as sender). Use it for formal, one-off
communications — acceptance/rejection notices, receipts, signed documents.

```php
$this->mailerManager->sendMailByDirection(
    'external-client@example.com',
    'Your manuscript has been validated — Acme Editions',
    'emails/client_manuscript_validation.html.twig',
    ['client' => $client, 'comments' => $evaluationData],
    null,
    ['/path/to/attestation.pdf' => 'Attestation_Reception.pdf'],
);
```

### `notifyToDirection()` vs `sendMailByDirection()` — which one?

Both end up calling the same underlying `sendManager()` method with the
`support` sender configuration; the difference is purely about **direction
and intent**, not implementation:

| | `notifyToDirection()` | `sendMailByDirection()` |
|---|---|---|
| Direction | Client action → internal team | Direction/support → external client |
| Typical recipient | `secretariat@...`, `direction@...` | The client's own address |
| `Reply-To` | Client's address (`$clientEmail`) — lets staff reply directly | Optional alternate reply address, if different from `support@...` |
| Example use case | "A new form was submitted" | "Your document has been approved" |

---

## `PriorityInterface`

Defines the priority levels used across `SystemMailer` and, indirectly,
`MailerManagerInterface::notifyBySystem()`:

| Constant | Value | Typical use |
|---|---|---|
| `PRIORITY_HIGHEST` | 1 | Password reset, 2FA codes |
| `PRIORITY_HIGH` | 2 | Registration confirmation, password change |
| `PRIORITY_NORMAL` | 3 | Account notifications, informational emails |
| `PRIORITY_LOW` | 4 | Newsletters, weekly digests |
| `PRIORITY_LOWEST` | 5 | Monthly statistics, automated reports |

`MailerFactory::validatePriority()` throws `InvalidArgumentException` if a
value outside `[1, 5]` is passed.

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