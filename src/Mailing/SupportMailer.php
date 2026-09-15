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

namespace Wlindabla\MailingBundle\Mailing;

use Wlindabla\MailingBundle\Mailing\MailerFactoryInterface;
use Wlindabla\MailingBundle\Mailing\SupportMailerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Concrete email sending service dedicated to support, management, and customer relation.
 *
 * This class implements SupportMailerInterface and uses the 'support' transport.
 * Its main responsibility is to handle interactive communications and notifications 
 * across the platform (for instance, sending contact forms or document submissions).
 *
 * **Discussion Channel Logic (Reply-To Mechanism):**
 * The key strength of this implementation lies in its ability to bridge administrators 
 * and external clients. When the system notifies management of a customer action, the client's email 
 * is injected into the 'Reply-To' field. This allows management to receive the notification in their 
 * professional inbox and, with a single click on "Reply", initiate or continue the conversation 
 * directly with the client by email—completely transparently and without further system interaction.
 *
 * It also natively supports:
 * - Dynamic attachment handling (PDF files, documents, etc.).
 * - On-the-fly image embedding (Inline Embedding) directly inside Twig templates.
 * - Asynchronous email routing to prevent blocking the application's main thread.
 *
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Infrastructure\Service\Mailing
 */
final class SupportMailer implements SupportMailerInterface
{
    private const CONFIG_TYPE = 'support';

    public function __construct(
        private readonly MailerFactoryInterface $mailerFactory,
        private readonly ?LoggerInterface $logger = null) {}

    public function send(
        string|array $recipientEmail,
        string $subject,
        string $htmlTemplate,
        ?array $context = null,
        ?string $senderEmail=null,
        ?string $replyToEmail = null,
        ?array $attachments = []
    ): void {

        $fromConfig = $this->mailerFactory->fromConfig(self::CONFIG_TYPE);

        if (!isset($fromConfig['address'], $fromConfig['name'])) {
            throw new RuntimeException(
                'Invalid support configuration: "address" and "name" keys are required'
            );
        }

        try {
            $supportEmail = $this->mailerFactory->createTemplateEmail(
                $senderEmail ??  $fromConfig['address'],
                $fromConfig['name'],
                $recipientEmail, 
                $subject,
                $htmlTemplate,
                $context ?? []
            );

            if(!empty($attachments)){
                foreach ($attachments as $path => $name) {
                    // If the file ends with an image extension, embed it (for Twig)
                    // Otherwise, attach it conventionally (PDF, etc.)
                    if (preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $path)) {
                        $supportEmail->embedFromPath($path, $name);
                    } else {
                        // The second parameter is the filename displayed to the user
                        $supportEmail->attachFromPath($path, is_string($name) ? $name : null);
                    }
                }
            }

            $supportEmail->replyTo($replyToEmail ?? $senderEmail);

            $supportEmail->getHeaders()->addTextHeader('X-Transport', self::CONFIG_TYPE);
            
            $this->mailerFactory->sendAsync($supportEmail);

        } catch (RuntimeException $e) {
            $this->logger?->error('Failed to retrieve support configuration', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Failed to send support email', [
                'recipient' =>  $recipientEmail,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                sprintf('Unable to send support email: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}