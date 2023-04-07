<?php declare(strict_types=1);

namespace olml89\XenforoSubscriptions\XF\Api\Controller;

use olml89\XenforoSubscriptions\UseCase\Subscription\Create;
use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Reply\ApiResult;
use XF\App;
use XF\Http\Request;
use XF\Mvc\Reply\Exception;

final class Subscriptions extends AbstractController
{
    private Create $createSubscription;

    public function __construct(App $app, Request $request)
    {
        $this->createSubscription = $app->get(Create::class);

        parent::__construct($app, $request);
    }

    /**
     * @throws Exception
     */
    public function actionPost(): ApiResult
    {
        $this->assertRequiredApiInput([
            'user_id',
            'password',
            'webhook',
        ]);

        $subscription = $this->createSubscription->create(
            user_id: $this->request->filter('user_id', 'uint'),
            password: $this->request->filter('password', 'str'),
            webhook: $this->request->filter('webhook', 'str'),
        );

        return $this->apiSuccess(['subscription' => $subscription->toApiResult()]);
    }
}
