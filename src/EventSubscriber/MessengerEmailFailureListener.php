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

namespace Wlindabla\MailingBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Mime\Email;

final class MessengerEmailFailureListener implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if (!($message instanceof SendEmailMessage)) {
            return;
        }

        $originalEmail = $message->getMessage();
        $error = $event->getThrowable();

        if (!($error instanceof TransportExceptionInterface) || !($originalEmail instanceof Email)) {
            return ;
        }

        $errorMessage = $error->getMessage();
        $to = implode(', ', array_map(fn ($addr) => $addr->getAddress(), $originalEmail->getTo()));

        $this->logger->error(
            sprintf(
                'ASYNCHRONOUS SEND FAILURE via Messenger to [%s]. Cause: %s',
                $to,
                $errorMessage
            )
        );

        // --- Logic to determine whether the failure is permanent (hard bounce) ---

        // SMTP servers often report invalid addresses with 5xx status codes
        // or specific messages (e.g. User unknown, Mailbox not found).

        // 1. Define the permanent-failure indicators (this is often transport-dependent!)
        $isPermanentFailure = false;

        // Example check for 5xx codes or specific messages
        // (the exact method depends on how TransportExceptionInterface exposes the SMTP code)
        if (str_contains($errorMessage, 'User unknown') || 
            str_contains($errorMessage, 'Mailbox not found') || 
            preg_match('/^5\d{2}/', $errorMessage)) {

            $isPermanentFailure = true;
        }

        $receiver = $event->getReceiverName();
        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf('The "%s" receiver does not support removing specific messages.', $receiver));
        }

        if ($isPermanentFailure) {
            // 2. Mark the task as PERMANENTLY FAILED
            // This prevents Messenger from retrying the task (bypasses the retry strategy).
            $receiver->reject($envelope);

            $this->logger->warning(
                sprintf(
                    'PERMANENT FAILURE detected for email [%s]. The task will NOT be retried.',
                    $to
                )
            );
        }
    }
}