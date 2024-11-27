<?php

namespace Yosimitso\WorkingForumBundle\Twig\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Yosimitso\WorkingForumBundle\Entity\Post;


class QuoteTwigExtension extends AbstractExtension
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly TranslatorInterface $translator,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        protected readonly Security $security
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'quote',
                [$this, 'quote']
            ),
        ];
    }

    public function quote(string $text, $locale=null, $user=null)
    {
        $content = preg_replace_callback('#\[quote=([0-9]+)\]#',
            function ($listQuote) use ($locale, $user)
            {

                // User muss übergeben werden, sonst, wird im E-Mail der Fullname des Schreibenden angezeigt
                if($user === null){
                    $user = $this->security->getUser();
                }
                /** @var Post $post */
                $post = $this->entityManager
                    ->getRepository(Post::class)
                    ->findOneById((int) $listQuote[1])
                ;

                //Fullname forcen wenn Admin oder PostUser gleich CurrentUser
                $forceFullname = ($this->authorizationChecker->isGranted('ROLE_ADMIN') or $post->getUser()->getId() === $user->getId());


                if (!is_null($post) && empty($post->getModerateReason())) {

                    $blockquote = "\n>**" . $post->getUser()->getFullname($forceFullname) . ' ' . $this->translator->trans('forum.has_written', [], 'YosimitsoWorkingForumBundle', $locale) . ":** <br>"
                        .$this->markdownQuote($this->quote($post->getContent(), $locale, $user)) . "\n\n";

                    return $blockquote;
                }

                return '';
            },
            $text
        );

        return $content;
    }

    private function markdownQuote(string $text) {
        return preg_replace('/\n/', "\n >", $text );
    }

    public function getName()
    {
        return 'quote';
    }
}
