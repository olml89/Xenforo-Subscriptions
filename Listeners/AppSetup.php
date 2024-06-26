<?php declare(strict_types=1);

namespace olml89\XenforoBots\Listeners;

use GuzzleHttp\Client;
use Laminas\Validator\Uuid as LaminasUuid;
use olml89\XenforoBots\Factory\ApiKeyFactory;
use olml89\XenforoBots\Factory\BotFactory;
use olml89\XenforoBots\Factory\BotSubscriptionFactory;
use olml89\XenforoBots\Factory\UserFactory;
use olml89\XenforoBots\Finder\ConversationMessageFinder;
use olml89\XenforoBots\Finder\PostFinder;
use olml89\XenforoBots\Repository\ApiKeyRepository;
use olml89\XenforoBots\Repository\BotRepository;
use olml89\XenforoBots\Repository\BotSubscriptionRepository;
use olml89\XenforoBots\Repository\UserRepository;
use olml89\XenforoBots\Service\Authorizer;
use olml89\XenforoBots\Finder\BotSubscriptionFinder;
use olml89\XenforoBots\Service\ErrorHandler;
use olml89\XenforoBots\Service\NotificationEnqueuer;
use olml89\XenforoBots\Service\UuidGenerator;
use olml89\XenforoBots\Service\Notifier\WebhookNotifier;
use olml89\XenforoBots\Finder\BotFinder;
use olml89\XenforoBots\UseCase\Bot\Create as CreateBot;
use olml89\XenforoBots\UseCase\Bot\Delete as DeleteBot;
use olml89\XenforoBots\UseCase\Bot\Index as IndexBots;
use olml89\XenforoBots\UseCase\Bot\Retrieve as RetrieveBot;
use olml89\XenforoBots\UseCase\BotSubscription\Activate as ActivateBotSubscription;
use olml89\XenforoBots\UseCase\BotSubscription\Create as CreateBotSubscription;
use olml89\XenforoBots\UseCase\BotSubscription\Deactivate as DeactivateBotSubscription;
use olml89\XenforoBots\UseCase\BotSubscription\Delete as DeleteBotSubscription;
use olml89\XenforoBots\UseCase\BotSubscription\Index as IndexBotSubscriptions;
use olml89\XenforoBots\UseCase\BotSubscription\Retrieve as RetrieveBotSubscription;
use olml89\XenforoBots\UseCase\BotSubscription\Update as UpdateBotSubscription;
use olml89\XenforoBots\UseCase\Notification\Content\ContentFactory;
use olml89\XenforoBots\UseCase\Notification\Notify as NotifyNotifiable;
use olml89\XenforoBots\UseCase\Notification\PublicInteraction\PublicInteractionFactory;
use olml89\XenforoBots\XF\Validator\Md5Token;
use olml89\XenforoBots\XF\Validator\Uuid;
use Stripe\Util\RandomGenerator;
use XF\App;
use XF\Container;
use XF\Repository\User;
use XF\Validator\AbstractValidator;

final class AppSetup
{
    private static function createJsonHttpClient(App $app): Client
    {
        return $app->http()->createClient([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => true,
        ]);
    }

    public static function listen(App $app): void
    {
        /** @var Container $container */
        $container = $app->container();

        /**
         * Factories
         */
        $container[ApiKeyFactory::class] = function() use ($app): ApiKeyFactory
        {
            return new ApiKeyFactory(
                entityManager: $app->em(),
                app: $app,
            );
        };

        $container[BotFactory::class] = function() use ($app): BotFactory
        {
            return new BotFactory(
                entityManager: $app->em(),
                uuidGenerator: $app->get(UuidGenerator::class),
            );
        };

        $container[BotSubscriptionFactory::class] = function() use($app): BotSubscriptionFactory
        {
            return new BotSubscriptionFactory(
                entityManager: $app->em(),
                uuidGenerator: $app->get(UuidGenerator::class),
            );
        };

        $container[UserFactory::class] = function() use ($app): UserFactory
        {
            /** @var User $userRepository */
            $userRepository = $app->repository('XF:User');

            return new UserFactory(
                userRepository: $userRepository,
            );
        };

        $container[ContentFactory::class] = function() use ($app): ContentFactory
        {
            return new ContentFactory(
                botRepository: $app->get(BotRepository::class),
            );
        };

        $container[PublicInteractionFactory::class] = function() use ($app): PublicInteractionFactory
        {
            return new PublicInteractionFactory();
        };

        /**
         * Finders
         */
        $container[BotFinder::class] = function() use($app): BotFinder
        {
            return new BotFinder(
                botRepository: $app->get(BotRepository::class),
            );
        };

        $container[BotSubscriptionFinder::class] = function() use($app): BotSubscriptionFinder
        {
            return new BotSubscriptionFinder(
                botSubscriptionRepository: $app->get(BotSubscriptionRepository::class),
            );
        };

        $container[ConversationMessageFinder::class] = function() use($app): ConversationMessageFinder
        {
            return new ConversationMessageFinder(
                conversationMessageFinder: $app->finder('XF:ConversationMessage'),
            );
        };

        $container[PostFinder::class] = function() use($app): PostFinder
        {
            return new PostFinder(
                postFinder: $app->finder('XF:Post'),
            );
        };

        /**
         * Repositories
         */
        $container[ApiKeyRepository::class] = function() use ($app): ApiKeyRepository
        {
            return new ApiKeyRepository(
                errorHandler: $app->get(ErrorHandler::class),
            );
        };

        $container[BotRepository::class] = function() use ($app): BotRepository
        {
            return new BotRepository(
                errorHandler: $app->get(ErrorHandler::class),
                botFinder: $app->finder('olml89\XenforoBots:Bot'),
            );
        };

        $container[BotSubscriptionRepository::class] = function() use($app): BotSubscriptionRepository
        {
            return new BotSubscriptionRepository(
                errorHandler: $app->get(ErrorHandler::class),
                botSubscriptionFinder: $app->finder('olml89\XenforoBots:BotSubscription'),
            );
        };

        $container[UserRepository::class] = function() use ($app): UserRepository
        {
            return new UserRepository(
                errorHandler: $app->get(ErrorHandler::class),
            );
        };

        /**
         * Services
         */
        $container[Authorizer::class] = function() use ($app): Authorizer
        {
            return new Authorizer(
                botFinder: $app->get(BotFinder::class),
            );
        };

        $container[ErrorHandler::class] = function() use($app, $container): ErrorHandler
        {
            return new ErrorHandler(
                error: $app->error(),
                debug: $container['config']['debug'],
            );
        };

        $container[NotificationEnqueuer::class] = function() use($app): NotificationEnqueuer
        {
            return new NotificationEnqueuer(
                jobManager: $app->jobManager(),
                contentFactory: $app->get(ContentFactory::class),
                publicInteractionFactory: $app->get(PublicInteractionFactory::class),
            );
        };

        $container[UuidGenerator::class] = function() use($app): UuidGenerator
        {
            return new UuidGenerator(stripeRandomGenerator: new RandomGenerator());
        };

        $container[WebhookNotifier::class] = function() use($app): WebhookNotifier
        {
            return new WebhookNotifier(
                httpClient: self::createJsonHttpClient($app),
            );
        };

        /**
         * UseCases
         */
        $container[IndexBots::class] = function() use ($app): IndexBots
        {
            return new IndexBots(
                botRepository: $app->get(BotRepository::class),
            );
        };

        $container[CreateBot::class] = function() use ($app): CreateBot
        {
            return new CreateBot(
                database: $app->db(),
                botRepository: $app->get(BotRepository::class),
                userFactory: $app->get(UserFactory::class),
                userRepository: $app->get(UserRepository::class),
                apiKeyFactory: $app->get(ApiKeyFactory::class),
                apiKeyRepository: $app->get(ApiKeyRepository::class),
                botFactory: $app->get(BotFactory::class),
            );
        };

        $container[RetrieveBot::class] = function() use ($app): RetrieveBot
        {
            return new RetrieveBot(
                botFinder: $app->get(BotFinder::class),
            );
        };

        $container[DeleteBot::class] = function() use ($app): DeleteBot
        {
            return new DeleteBot(
                botFinder: $app->get(BotFinder::class),
                botRepository: $app->get(BotRepository::class),
            );
        };

        $container[IndexBotSubscriptions::class] = function() use($app): IndexBotSubscriptions
        {
            return new IndexBotSubscriptions();
        };

        $container[CreateBotSubscription::class] = function() use($app): CreateBotSubscription
        {
            return new CreateBotSubscription(
                botSubscriptionFactory: $app->get(BotSubscriptionFactory::class),
                botSubscriptionRepository: $app->get(BotSubscriptionRepository::class),
            );
        };

        $container[RetrieveBotSubscription::class] = function() use($app): RetrieveBotSubscription
        {
            return new RetrieveBotSubscription(
                botSubscriptionFinder: $app->get(BotSubscriptionFinder::class),
            );
        };

        $container[UpdateBotSubscription::class] = function() use($app): UpdateBotSubscription
        {
            return new UpdateBotSubscription(
                botSubscriptionFinder: $app->get(BotSubscriptionFinder::class),
                botSubscriptionFactory: $app->get(BotSubscriptionFactory::class),
                botSubscriptionRepository: $app->get(BotSubscriptionRepository::class),
            );
        };

        $container[DeleteBotSubscription::class] = function() use($app): DeleteBotSubscription
        {
            return new DeleteBotSubscription(
                botSubscriptionFinder: $app->get(BotSubscriptionFinder::class),
                botSubscriptionRepository: $app->get(BotSubscriptionRepository::class),
            );
        };

        $container[ActivateBotSubscription::class] = function() use ($app): ActivateBotSubscription
        {
            return new ActivateBotSubscription(
                botSubscriptionFinder: $app->get(BotSubscriptionFinder::class),
                botSubscriptionRepository: $app->get(BotSubscriptionRepository::class),
            );
        };

        $container[DeactivateBotSubscription::class] = function() use ($app): DeactivateBotSubscription
        {
            return new DeactivateBotSubscription(
                botSubscriptionFinder: $app->get(BotSubscriptionFinder::class),
                botSubscriptionRepository: $app->get(BotSubscriptionRepository::class),
            );
        };

        $container[NotifyNotifiable::class] = function() use($app): NotifyNotifiable
        {
            return new NotifyNotifiable(
                webhookNotifier: $app->get(WebhookNotifier::class),
                error: $app->error(),
            );
        };

        /**
         * Custom validators
         */
        $container->extendFactory(
            type: 'validator',
            callable: function($class, array $params, Container $container, callable $original) use ($app): AbstractValidator
            {
                return match ($class) {
                    'Uuid' => new Uuid(
                        laminasUuid: new LaminasUuid(),
                        app: $app,
                    ),
                    'Md5Token' => new Md5Token(
                        app: $app,
                    ),
                    default => $original($class, $params, $container, $original)
                };
            }
        );
    }
}
