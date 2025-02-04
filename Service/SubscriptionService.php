<?php


namespace Yosimitso\WorkingForumBundle\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Yosimitso\WorkingForumBundle\Entity\Post;
use Yosimitso\WorkingForumBundle\Entity\Subforum;
use Yosimitso\WorkingForumBundle\Entity\Subscription;
use Yosimitso\WorkingForumBundle\Entity\Thread;
use Yosimitso\WorkingForumBundle\Entity\UserInterface;
use App\Entity\User\User;


class SubscriptionService
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly MailerInterface $mailer,
        protected readonly TranslatorInterface $translator,
        protected readonly string $siteTitle,
        protected readonly Environment $templating,
        protected readonly ?string $senderAddress,
        protected readonly ?string $senderName,
        protected readonly LoggerInterface $logger,
    ) {
        if (empty($this->senderAddress)) {
            trigger_error('The parameter "yosimitso_working_forum.mailer_sender_address" is empty, email delivering might failed');
        }
        if (empty($this->senderName)) {
            trigger_error('The parameter "yosimitso_working_forum.mailer_sender_name" is empty, email delivering might failed');
        }

    }

    /**
     * Notify subscribed users of a new post
     * @throws \Exception
     */
    public function notifySubscriptions(Post $post) : bool
    {
        if (is_null($post->getThread())) {
            return false;
        }

        $subscriptions = $this->em->getRepository(Subscription::class)->findBy(['thread' => $post->getThread()->getId()]);
        if (!count($subscriptions)) {
            return false;
        }

        $emailTranslation = $this->getEmailTranslation($post->getThread()->getSubforum(), $post->getThread(), $post, $post->getUser());

        foreach ($subscriptions as $subscriptionItem) {
            try {
                if (
                    !empty($subscriptionItem->getUser()->getEmailAddress()) and            // Only if valid Email
                    $subscriptionItem->getUser()->getId() !== $post->getUser()->getId()    // User that created the post is not equal to subscribed User
                ) {
                    $emailTranslation['user'] = $subscriptionItem->getUser();
                    $locale = $subscriptionItem->getUser()->getLanguage()->getLocaleCode();
                    $email = (new Email())
                        ->subject($this->translator->trans('subscription.post.emailNotification.subject', [
                            '%siteTitle%' => $emailTranslation['siteTitle'],
                            '%threadLabel%' => $emailTranslation['threadLabel']
                        ], 'YosimitsoWorkingForumBundle', $locale))
                        ->from(new Address($this->senderAddress, $this->senderName))
                        ->to($subscriptionItem->getUser()->getEmailAddress())
                        ->html(
                            $this->templating->render(
                                '@YosimitsoWorkingForum/Email/notification_new_message_'. $locale .'.html.twig',
                                $emailTranslation
                            )
                        )
                    ;

                    $this->mailer->send($email);
                }
            } catch (\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
                // Fehler protokollieren und dann mit der nächsten E-Mail fortfahren
                $this->logger->error('Ungültige E-Mail-Adresse für User-ID ' . $subscriptionItem->getUser()->getId() . ': ' . $e->getMessage());
                // Optionale weitere Massnahmen, z.B. in eine Datenbank loggen oder den Benutzer markieren.
                continue; // Mit dem nächsten Benutzer fortfahren
            } catch (\Exception $e) {
                // Auch andere Exceptions werden hier abgefangen, protokolliert und der Versand wird fortgesetzt
                $this->logger->error('Fehler beim Senden an User-ID ' . $subscriptionItem->getUser()->getId() . ': ' . $e->getMessage());
                continue;
            }
        }

        return true;
    }

    /**
     * Get translated variables for email content
     */
    private function getEmailTranslation(Subforum $subforum, Thread $thread, Post $post, UserInterface $user) : array
    {
        return [
            'siteTitle' => $this->siteTitle,
            'subforumName' => $subforum->getName(),
            'threadLabel' => $thread->getLabel(),
            'threadAuthor' => $thread->getAuthor()->getUsername(),
            'user' => $user,
            'thread' => $post->getThread(),
            'post' => $post,
            'postUser' => $post->getUser()
        ];
    }


    /**
     * Notify the Application Owner of a new post
     * @throws \Exception
     */
    public function notifyPostApplicationOwner(Post $post)
    {
        $emailTranslation = $this->getEmailTranslation($post->getThread()->getSubforum(), $post->getThread(), $post, $post->getUser());

        // Would be better to set it to the default locale
        $locale = 'de';

        $email = (new Email())
            ->subject($this->translator->trans('subscription.post.emailNotification.subject', [
                '%siteTitle%' => $emailTranslation['siteTitle'],
                '%threadLabel%' => $emailTranslation['threadLabel']
            ], 'YosimitsoWorkingForumBundle', $locale))
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to($this->senderAddress)
            ->html(
                $this->templating->render(
                    '@YosimitsoWorkingForum/Email/notification_new_message_lm.html.twig',
                    $emailTranslation
                )
            )
        ;

        $this->mailer->send($email);
    }

    /**
     * Notify the Application Owner of a new thread
     * @throws \Exception
     */
    public function notifyThreadApplicationOwner(Thread $entity)
    {
        // Would be better to set it to the default locale
        $locale = 'de';

        $params = [
            'siteTitle' => $this->siteTitle,
            'threadLabel' => $entity->getLabel(),
            'thread' => $entity,
            'threadUser' => $entity->getAuthor()
        ];

        $email = (new Email())
            ->subject($this->translator->trans('subscription.thread.emailNotification.subject', [
                '%siteTitle%' => $params['siteTitle'],
                '%threadLabel%' => $params['threadLabel']
            ], 'YosimitsoWorkingForumBundle', $locale))
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to($this->senderAddress)
            ->html(
                $this->templating->render(
                    '@YosimitsoWorkingForum/Email/notification_new_thread_lm.html.twig',
                    $params
                )
            )
        ;

        $this->mailer->send($email);
    }

    /**
     * Notify subscribed users of a new thread
     * @throws \Exception
     */
    public function notifyTreadSubscriptions(Thread $entity)
    {
        $subscribedUsers = $this->em->getRepository(User::class)->findBy(['notifyNewThreads' => true]);
        if (!count($subscribedUsers))
        {
            return false;
        }

        $params = [
            'siteTitle' => $this->siteTitle,
            'threadLabel' => $entity->getLabel(),
            'thread' => $entity,
            'threadUser' => $entity->getAuthor()
        ];

        foreach ($subscribedUsers as $user)
        {
            try
            {
                if (
                    !empty($user->getEmailAddress()) and // Only if valid Email
                    $user->getId() !== $entity->getAuthor()->getId()    // Post User is not equal to Notif User
                )
                {
                    $params['user'] = $user;
                    $locale = $user->getLanguage()->getLocaleCode();
                    $email = (new Email())
                        ->subject($this->translator->trans('subscription.thread.emailNotification.subject', [
                            '%siteTitle%' => $params['siteTitle'],
                            '%threadLabel%' => $params['threadLabel']
                        ], 'YosimitsoWorkingForumBundle', $locale))
                        ->from(new Address($this->senderAddress, $this->senderName))
                        ->to($user->getEmailAddress())
                        ->html(
                            $this->templating->render(
                                '@YosimitsoWorkingForum/Email/notification_new_thread_' . $locale . '.html.twig',
                                $params
                            )
                        );

                    $this->mailer->send($email);
                }
            } catch (\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
                // Fehler protokollieren und dann mit der nächsten E-Mail fortfahren
                $this->logger->error('Ungültige E-Mail-Adresse für User-ID ' . $user->getId() . ': ' . $e->getMessage());
                // Optionale weitere Massnahmen, z.B. in eine Datenbank loggen oder den Benutzer markieren.
                continue; // Mit dem nächsten Benutzer fortfahren
            } catch (\Exception $e) {
                // Auch andere Exceptions werden hier abgefangen, protokolliert und der Versand wird fortgesetzt
                $this->logger->error('Fehler beim Senden an User-ID ' . $user->getId() . ': ' . $e->getMessage());
                continue;
            }
        }

        return true;
    }

    // Target: Users
    // to be used by a symfony command for daily updates
    // sends a daily mail of new threads to users who want to be notified
    /**
     * Notify subscribed users of all new thread
     * @throws \Exception
     */
    public function notifyTreadSubscriptionsTask(): bool
    {
        // Threads where notificationSent is false
        $threads = $this->em->getRepository(Thread::class)->findBy(['notificationSent' => false]);
        if (!count($threads))
        {
            return false;
        }

        // Generate the Thread Parameters
        $threadsParams = [];
        foreach ($threads as $thread)
        {
            $threadsParams[] = [
                'threadLabel' => $thread->getLabel(),
                'thread' => $thread,
                'threadUser' => $thread->getAuthor()
            ];
        }

        // Users where notifyNewThreads is true
        $subscribedUsers = $this->em->getRepository(User::class)->findBy(['notifyNewThreads' => true]);
        if (!count($subscribedUsers))
        {
            return false;
        }

        $params = [
            'siteTitle' => $this->siteTitle,
            'threads' => $threadsParams,
        ];

        foreach ($subscribedUsers as $user)
        {
            try
            {
                if (
                    !empty($user->getEmailAddress()) // Only if valid Email
                )
                {
                    $params['user'] = $user;
                    $locale = $user->getLanguage()->getLocaleCode();
                    $email = (new Email())
                        ->subject($this->translator->trans('subscription.thread.emailNotification.subject_task', [
                            '%siteTitle' => $params['siteTitle']
                        ], 'YosimitsoWorkingForumBundle', $locale))
                        ->from(new Address($this->senderAddress, $this->senderName))
                        ->to($user->getEmailAddress())
                        ->html(
                            $this->templating->render(
                                '@YosimitsoWorkingForum/Email/task/notification_new_thread_' . $locale . '.html.twig',
                                $params
                            )
                        );
                    $this->mailer->send($email);
                }
            } catch (\Exception $e)
            {
                throw new \Exception($e->getMessage());
            }
        }

        // Set Notification sent to true, so they won't be sent again
        foreach ($threads as $thread)
        {
            $thread->setNotificationSent(true);
            $this->em->persist($thread);
        }
        $this->em->flush();

        return true;
    }
}
