<?php

namespace Yosimitso\WorkingForumBundle\Twig\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Yosimitso\WorkingForumBundle\Entity\Post;

/**
 * Class QuoteTwigExtension
 *
 * @package Yosimitso\WorkingForumBundle\Twig\Extension
 */
class QuoteTwigExtension extends AbstractExtension
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    private $authorizationChecker;

    /**
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface    $translator
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        AuthorizationCheckerInterface $authorizationChecker,
        Security $security
    )
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->authorizationChecker = $authorizationChecker;
        $this->security = $security;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [
            new TwigFilter(
                'quote',
                [$this, 'quote']
            ),
        ];
    }

    /**
     * @param string $text
     *
     * @return mixed
     */
    public function quote($text, $locale=null, $user=null)
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

    /**
     * @param string $text
     * @return string|string[]|null
     */
    private function markdownQuote($text) {
        return preg_replace('/\n/', "\n >", $text );
    }
    /**
     * @return string
     */
    public function getName()
    {
        return 'quote';
    }
}
