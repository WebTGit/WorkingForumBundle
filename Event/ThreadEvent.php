<?php

namespace Yosimitso\WorkingForumBundle\Event;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Contracts\Translation\TranslatorInterface;
use Yosimitso\WorkingForumBundle\Entity\Thread;
use Yosimitso\WorkingForumBundle\Service\SubscriptionService;

/**
 * Class ThreadEvent
 * @package Yosimitso\WorkingForumBundle\Event
 */
class ThreadEvent
{
    private TranslatorInterface $translator;
    private SubscriptionService $subscriptionService;
    private array $paramSubscription;

    public function __construct(
        SubscriptionService $subscriptionService,
        array $paramSubscription
    )
    {
        $this->subscriptionService = $subscriptionService;
        $this->paramSubscription = $paramSubscription;
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Thread) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $uow = $entityManager->getUnitOfWork();

        // Get the changeset
        $changeset = $uow->getEntityChangeSet($entity);


        if (isset($changeset['slug'])) {
            if ($this->paramSubscription['enable']) {
                $this->subscriptionService->notifyTreadSubscriptions($entity);
                $this->subscriptionService->notifyThreadApplicationOwner($entity);
                // This should be executed in a command, it does basically the same as notifyTreadSubscriptions, but sends all threads that were not sent by the time of execution and sets the threads notificationSent-flag to true.
//                $this->subscriptionService->notifyTreadSubscriptionsTask();
            }
        }

    }
}