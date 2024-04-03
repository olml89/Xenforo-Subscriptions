<?php declare(strict_types=1);

namespace olml89\XenforoBots\Repository;

use olml89\XenforoBots\Entity\Bot;
use olml89\XenforoBots\Exception\BotRemovalException;
use olml89\XenforoBots\Exception\BotStorageException;
use olml89\XenforoBots\Service\ErrorHandler;
use olml89\XenforoBots\XF\Entity\User;
use Throwable;
use XF\Mvc\Entity\Finder;

final class BotRepository
{
    public function __construct(
        private readonly ErrorHandler $errorHandler,
        private readonly Finder $botFinder,
    ) {}

    /**
     * @return Bot[]
     */
    public function getAll(): array
    {
        return $this
            ->botFinder
            ->fetch()
            ->toArray();
    }

    /**
     * @return Bot[]
     */
    public function getByUsers(User ...$users): array
    {
        if (empty($users)) {
            return [];
        }

        $userIds = array_map(
            fn (User $user): int => $user->user_id,
            $users
        );

        return $this
            ->botFinder
            ->where('user_id', $userIds)
            ->fetch()
            ->toArray();
    }

    public function getByUser(User $user): ?Bot
    {
        /** @var ?Bot $bot */
        $bot = $this
            ->botFinder
            ->where('user_id', $user->user_id)
            ->fetchOne();

        return $bot;
    }

    public function get(string $bot_id): ?Bot
    {
        /** @var ?Bot $bot */
        $bot = $this
            ->botFinder
            ->whereId($bot_id)
            ->fetchOne();

        return $bot;
    }

    /**
     * @throws BotStorageException
     */
    public function save(Bot $bot): void
    {
        try {
            $bot->save();
        }
        catch (Throwable $e) {
            throw BotStorageException::entity(
                entity: $bot,
                context: $this->errorHandler->handle($e),
            );
        }
    }

    /**
     * @throws BotRemovalException
     */
    public function delete(Bot $bot): void
    {
        try {
            $bot->delete();
        }
        catch (Throwable $e) {
            throw BotRemovalException::entity(
                entity: $bot,
                context: $this->errorHandler->handle($e),
            );
        }
    }
}
