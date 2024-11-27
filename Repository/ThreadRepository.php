<?php

namespace Yosimitso\WorkingForumBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Yosimitso\WorkingForumBundle\Entity\Forum;
use Yosimitso\WorkingForumBundle\Entity\Post;
use Yosimitso\WorkingForumBundle\Entity\Subforum;
use Doctrine\ORM\Query;
use Yosimitso\WorkingForumBundle\Entity\Thread;
use Yosimitso\WorkingForumBundle\Entity\UserInterface;
use App\Entity\User\UserDetail;


class ThreadRepository extends EntityRepository
{
    public function getThread(int $start = 0, int $limit = 10)
    {
        $queryBuilder = $this->_em->createQueryBuilder();
        $query = $queryBuilder
            ->select('a')
            ->addSelect('b')
            ->from($this->_entityName, 'a')
            ->join(Post::class, 'b', 'WITH', 'a.id = b.thread')
            ->orderBy('a.note', 'desc')
            ->setMaxResults($limit)
            ->getQuery()
        ;

        return $query->getScalarResult();
    }

    /**
     * @return Thread[]
     */
    public function search(string $keywords, int $start = 0, int $limit = 100, array $whereSubforum = []) : ?array
    {
        if (empty($whereSubforum)) {
            return null;
        }

        $where = '';

        // New Version Yosimitso
        foreach ($keywords as $word)
        {
            $where .= "(thread.label LIKE '%" . $word . "%' OR thread.subLabel LIKE '%" . $word . "%' OR post.content LIKE '%" . $word . "%') OR";
        }

        $where = rtrim($where, ' OR');

//        foreach ($keywords as $word)
//        {
//            $where .= "(thread.label LIKE '%" . $word . "%' OR thread.subLabel LIKE '%" . $word . "%' OR post.content LIKE '%" . $word . "%') OR";
//        }

//        $where .= "(thread.label LIKE '%" . $keywords . "%' OR thread.subLabel LIKE '%" . $keywords . "%' OR post.content LIKE '%" . $keywords . "%') OR";

//        $where = rtrim($where, ' OR');

        $queryBuilder = $this->_em->createQueryBuilder();
        $queryBuilder
            ->select('thread')
            ->addSelect('subforum')
            ->addSelect('forum')
            ->addSelect('author.avatarUrl AS author_avatarUrl, author.nickname AS author_nickname')
            ->addSelect('authorDetail')

            ->addSelect('lastReplyUser.avatarUrl AS lastReplyUser_avatarUrl, lastReplyUser.nickname AS lastReplyUser_nickname')
            ->addSelect('lastReplyUserDetail')

            ->from($this->_entityName, 'thread')
            ->join(Post::class, 'post', 'WITH', 'post.thread = thread.id')
            ->join(UserInterface::class,'author','WITH','thread.author = author.id')
            ->join(UserDetail::class,'authorDetail','WITH','thread.author = authorDetail.user')
            ->join(UserInterface::class, 'lastReplyUser', 'WITH', 'thread.lastReplyUser = lastReplyUser.id')
            ->join(UserDetail::class,'lastReplyUserDetail','WITH','thread.lastReplyUser = lastReplyUserDetail.user')
            ->join(Subforum::class,'subforum','WITH','thread.subforum = subforum.id')
            ->join(Forum::class, 'forum', 'WITH', 'subforum.forum = forum.id')
//            ->where($where)
            ->orWhere('thread.label LIKE :keywords OR thread.subLabel LIKE :keywords OR post.content LIKE :keywordsEscaped ')
            ->andWhere('post.moderateReason IS NULL')
            ->setParameter('keywords', sprintf("%%%s%%", $keywords))
            ->setParameter('keywordsEscaped', sprintf("%%%s%%", htmlentities(strip_tags($keywords))))
        ;

        if (!empty($whereSubforum))
        {
            $queryBuilder->andWhere('subforum.id IN ('.implode(',',$whereSubforum).')');
        }
        $queryBuilder->setMaxResults($limit)

        ;
        $query = $queryBuilder;
        $result = $query->getQuery()->getScalarResult();

        //List Every Thread only once
        $threadIds = [];
        foreach ($result as $id => $r){
            if(!in_array($r['thread_id'], $threadIds)){
                $threadIds[] = $r['thread_id'];
            }else{
                unset($result[$id]);
            }
        }


        return $result;
    }

    public function getAllBySubforum($subforum, $withPosts = false) : array
    {
        $query = $this->_em->createQueryBuilder()
            ->select('thread')
            ->addSelect('subforum')
            ->addSelect('forum')
            ->addSelect('author.avatarUrl AS author_avatarUrl, author.nickname AS author_nickname')
            ->addSelect('authorDetail')
            ->addSelect('lastReplyUser.avatarUrl AS lastReplyUser_avatarUrl, lastReplyUser.nickname AS lastReplyUser_nickname')
            ->addSelect('lastReplyUserDetail')
            ->from($this->_entityName, 'thread')
            ->join(UserInterface::class,'author','WITH','thread.author = author.id')
            ->join(UserDetail::class,'authorDetail','WITH','thread.author = authorDetail.user')
            ->join(UserInterface::class, 'lastReplyUser', 'WITH', 'thread.lastReplyUser = lastReplyUser.id')
            ->join(UserDetail::class,'lastReplyUserDetail','WITH','thread.lastReplyUser = lastReplyUserDetail.user')
            ->join(Subforum::class,'subforum','WITH','thread.subforum = subforum.id')
            ->join(Forum::class, 'forum', 'WITH', 'subforum.forum = forum.id')
            ->where('subforum.id = '.$subforum->getId())
            ->andWhere('thread.slug != :slug_not_empty')
            ->orderBy('thread.pin', 'DESC')
            ->addOrderBy('thread.lastReplyDate', 'DESC')
            ->setParameter('slug_not_empty', '')
        ;

        if ($withPosts) {
            $query->addSelect('post')
                ->join(Post::class,'post','WITH','post.thread = thread.id');
        }
        $result = $query->getQuery()->getScalarResult();

        return $result;
    }
}
