<?php

namespace Harentius\BlogBundle;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\ORM\EntityManager;
use Harentius\BlogBundle\Entity\Article;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Rating
{
    const TIME_TO_REMEMBER_IP = 60;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * @param RequestStack $requestStack
     * @param EntityManager $em
     * @param CacheProvider $cache
     */
    public function __construct(RequestStack $requestStack, EntityManager $em, CacheProvider $cache)
    {
        $this->requestStack = $requestStack;
        $this->em = $em;
        $this->cache = $cache;
    }

    /**
     * @param Response $response
     * @param Article $article
     * @param string $type
     * @return Response
     */
    public function rate(Response $response, Article $article, $type = 'like')
    {
        $this->accessDeniedIfRated($article);

        if ($type === 'like') {
            $article->increaseLikesCount();
        } elseif ($type === 'dislike') {
            $article->increaseDisLikesCount();
        }

        $this->addToRated($response, $article, $type);
        $this->em->flush($article);

        return $response;
    }

    /**
     * @param Article $article
     * @return bool
     */
    public function isLiked(Article $article)
    {
        return in_array($article->getId(), json_decode($this->getRequest()->cookies->get('harentius_blog_articles_like', '[]')));
    }

    /**
     * @param Article $article
     * @return bool
     */
    public function isDisLiked(Article $article)
    {
        return in_array($article->getId(), json_decode($this->getRequest()->cookies->get('harentius_blog_articles_dislike', '[]')));
    }

    /**
     * @param Article $article
     * @return bool
     */
    public function isRated(Article $article)
    {
        $ip = $this->getRequest()->getClientIp();

        if ($this->cache->contains($ip)) {
            return true;
        }

        return $this->isLiked($article) || $this->isDisLiked($article);
    }

    /**
     * @param Article $article
     */
    private function accessDeniedIfRated(Article $article)
    {
        if ($this->isRated($article)) {
            throw new AccessDeniedHttpException('You already voted for this article');
        }
    }

    /**
     * @param Response $response
     * @param Article $article
     * @param string $type
     */
    private function addToRated(Response $response, Article $article, $type)
    {
        $articleId = $article->getId();
        $key = "harentius_blog_articles_{$type}";
        $rated = json_decode($this->getRequest()->cookies->get($key, '[]'));

        if (!in_array($articleId, $rated)) {
            $rated[] = $articleId;
            $response->headers->setCookie(new Cookie($key, json_encode($rated)));
            $this->cache->save($ip = $this->getRequest()->getClientIp(), true, self::TIME_TO_REMEMBER_IP);
        }
    }

    /**
     * @return Request|null
     */
    private function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }
}