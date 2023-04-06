<?php declare(strict_types=1);

namespace olml89\XenforoSubscriptions\UseCases\Subscription;

use olml89\XenforoSubscriptions\Exceptions\ErrorHandler;
use olml89\XenforoSubscriptions\XF\Mvc\Reply\InternalServerErrorException;
use XF\Db\Exception as XFDatabaseException;

final class SaveSubscriptionException extends InternalServerErrorException
{
    public function __construct(XFDatabaseException $xfDatabaseException, ErrorHandler $errorHandler)
    {
        $context = $errorHandler->handle($xfDatabaseException);

        parent::__construct(
            message: 'The subscription has failed',
            errorCode: 'subscription.store.error.database_error',
            context: $context,
        );
    }
}
