<?php declare(strict_types=1);

namespace olml89\Subscriptions\ValueObjects\Md5Hash;

use olml89\Subscriptions\Exceptions\InputException;

final class InvalidMd5HashException extends InputException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            message: sprintf('Must represent a valid MD5 hash, \'%s\' provided', $hash),
            errorCode: 'invalid_md5_hash',
        );
    }
}
