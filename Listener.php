<?php declare(strict_types=1);

namespace olml89\Subscriptions;

use GuzzleHttp\Client;
use olml89\Subscriptions\Repositories\SubscriptionRepository;
use olml89\Subscriptions\Repositories\XFUserRepository;
use olml89\Subscriptions\Services\WebhookVerifier\WebhookVeryfier;
use olml89\Subscriptions\Services\XFUserFinder\XFUserFinder;
use olml89\Subscriptions\UseCases\Subscription\CreateSubscription;
use XF\App;
use XF\Container;

final class Listener
{
    private static function createJsonHttpClient(App $app): Client
    {
        return $app->http()->createClient([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'http_errors' => true,
        ]);
    }

    public static function appSetup(App $app): void
    {
        /** @var Container $container */
        $container = $app->container();

        $container[XFUserRepository::class] = function() use($app): XFUserRepository
        {
            return new XFUserRepository($app->em());
        };

        $container[XFUserFinder::class] = function() use($app): XFUserFinder
        {
            return new XFUserFinder($app->get(XFUserRepository::class));
        };

        $container[WebhookVeryfier::class] = function() use($app): WebhookVeryfier
        {
            return new WebhookVeryfier(self::createJsonHttpClient($app));
        };

        $container[SubscriptionRepository::class] = function() use($app): SubscriptionRepository
        {
            return new SubscriptionRepository($app->em());
        };

        $container[CreateSubscription::class] = function() use($app): CreateSubscription
        {
            return new CreateSubscription(
                xFUserFinder: $app->get(XFUserFinder::class),
                webhookVeryfier: $app->get(WebhookVeryfier::class),
                subscriptionRepository: $app->get(SubscriptionRepository::class),
            );
        };
    }
}
