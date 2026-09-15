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

/**
 * @author AGBOKOUDJO Franck <internationaleswebservices@gmail.com>
 * @package App\Application\Service\Mailer
 */
interface PriorityInterface
{
    /**
     * Highest priority (critical) - processed first.
     * 
     * Examples: Password resets, 2FA verification codes
     */
    public const PRIORITY_HIGHEST = 1;

    /**
     * High priority - processed quickly.
     * 
     * Examples: Registration confirmation, password change notifications
     */
    public const PRIORITY_HIGH = 2;

    /**
     * Normal priority (default) - processed in standard order.
     * 
     * Examples: Account notifications, informational emails
     */
    public const PRIORITY_NORMAL = 3;

    /**
     * Low priority - processed when the system is less busy.
     * 
     * Examples: Newsletters, weekly digests
     */
    public const PRIORITY_LOW = 4;

    /**
     * Lowest priority - processed last.
     * 
     * Examples: Monthly statistics, automated reports
     */
    public const PRIORITY_LOWEST = 5;

}