<?php


namespace Yosimitso\WorkingForumBundle\Service;

use Doctrine\ORM\EntityManager;
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
    private EntityManager $em;
    private MailerInterface $mailer;
    private TranslatorInterface $translator;
    private string $siteTitle;
    private ?string $senderAddress;
    private Environment $templating;
    private ?string $senderName;

    public function __construct(
        EntityManager $em,
        MailerInterface $mailer,
        TranslatorInterface $translator,
        string $siteTitle,
        Environment $templating,
        ?string $senderAddress,
        ?string $senderName
    ) {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->siteTitle = $siteTitle;
        $this->senderAddress = $senderAddress;
        $this->senderName = $senderName;
        $this->templating = $templating;

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
        $notifs = $this->em->getRepository(Subscription::class)->findBy(['thread' => $post->getThread()->getId()]);
        if (!count($notifs)) {
            return false;
        }
        $emailTranslation = $this->getEmailTranslation($post->getThread()->getSubforum(), $post->getThread(), $post, $post->getUser());
        
        if (!is_null($notifs)) {
            foreach ($notifs as $notif) {
                try {
                    if (
                        !empty($notif->getUser()->getEmailAddress()) and            // Only if valid Email
                        $notif->getUser()->getId() !== $post->getUser()->getId()    // Post User is not equal to Notif User
                    ) {
                        $emailTranslation['user'] = $notif->getUser();
                        $locale = $notif->getUser()->getLanguage()->getLocaleCode();
                        $email = (new Email())
                            ->subject($this->translator->trans('subscription.emailNotification.subject', $emailTranslation, 'YosimitsoWorkingForumBundle', $locale))
                            ->from(new Address($this->senderAddress, $this->senderName))
                            ->to($notif->getUser()->getEmailAddress())
                            ->html(
                                $this->templating->render(
                                    '@YosimitsoWorkingForum/Email/notification_new_message_'. $locale .'.html.twig',
                                    $emailTranslation
                                )
                            )
                        ;

                        $this->mailer->send($email);
                    }
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }

            return true;
        }
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

    public function notifyPostApplicationOwner(Post $post)
    {
        $emailTranslation = $this->getEmailTranslation($post->getThread()->getSubforum(), $post->getThread(), $post, $post->getUser());

        $locale = 'de';

        $email = (new Email())
            ->subject($this->translator->trans('subscription.emailNotification.subject', $emailTranslation, 'YosimitsoWorkingForumBundle', $locale))
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

    public function notifyThreadApplicationOwner(Thread $entity)
    {
        $locale = 'de';

        $params = [
            'siteTitle' => $this->siteTitle,
            'threadLabel' => $entity->getLabel(),
            'thread' => $entity,
            'threadUser' => $entity->getAuthor()
        ];

        $email = (new Email())
            ->subject($this->translator->trans('subscription.thread.emailNotification.subject', $params, 'YosimitsoWorkingForumBundle', $locale))
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

    public function notifyTreadSubscriptions(Thread $entity)
    {
        $notifs = $this->em->getRepository(User::class)->findBy(['notifyNewThreads' => true]);
        if (!count($notifs))
        {
            return false;
        }

        $params = [
            'siteTitle' => $this->siteTitle,
            'threadLabel' => $entity->getLabel(),
            'thread' => $entity,
            'threadUser' => $entity->getAuthor()
        ];

        if (!is_null($notifs))
        {
            foreach ($notifs as $user)
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
                            ->subject($this->translator->trans('subscription.thread.emailNotification.subject', $params, 'YosimitsoWorkingForumBundle', $locale))
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
                } catch (\Exception $e)
                {
                    throw new \Exception($e->getMessage());
                }
            }

            return true;
        }
    }

    public function notifyTreadSubscriptionsTask()
    {
        // Threads where notificationSent is false
        $threads = $this->em->getRepository(Thread::class)->findBy(['notificationSent' => false]);
        if (!count($threads))
        {
            return false;
        }

        $threadsParams = [];
        foreach ($threads as $thread)
        {
            $threadsParams[] = [
                'threadLabel' => $thread->getLabel(),
                'thread' => $thread,
                'threadUser' => $thread->getAuthor()
            ];

            $thread->setNotificationSent(true);
            $this->em->persist($thread);
        }
        $this->em->flush();

        // Users where notifyNewThreads is true
        $notifs = $this->em->getRepository(User::class)->findBy(['notifyNewThreads' => true]);
        if (!count($notifs))
        {
            return false;
        }

        $params = [
            'siteTitle' => $this->siteTitle,
            'threads' => $threadsParams,
        ];

        if (!is_null($notifs))
        {
            foreach ($notifs as $user)
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
                            ->subject($this->translator->trans('subscription.thread.emailNotification.subject_task', $params, 'YosimitsoWorkingForumBundle', $locale))
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

            return true;
        }
    }
}
