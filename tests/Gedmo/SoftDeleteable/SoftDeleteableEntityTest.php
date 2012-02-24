<?php

namespace Gedmo\SoftDeleteable;

use Tool\BaseTestCaseORM;
use Doctrine\Common\EventManager;
use Doctrine\Common\Util\Debug,
    SoftDeleteable\Fixture\Entity\Article,
    SoftDeleteable\Fixture\Entity\Comment,
    Gedmo\SoftDeleteable\SoftDeleteableListener;

/**
 * These are tests for SoftDeleteable behavior
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @package Gedmo.SoftDeleteable
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class SoftDeleteableEntityTest extends BaseTestCaseORM
{
    const ARTICLE_CLASS = 'SoftDeleteable\Fixture\Entity\Article';
    const COMMENT_CLASS = 'SoftDeleteable\Fixture\Entity\Comment';
    const SOFT_DELETEABLE_FILTER_NAME = 'soft-deleteable';

    private $softDeleteableListener;

    protected function setUp()
    {
        parent::setUp();

        $evm = new EventManager;
        $this->softDeleteableListener = new SoftDeleteableListener();
        $evm->addEventSubscriber($this->softDeleteableListener);
        $config = $this->getMockAnnotatedConfig();
        $config->addFilter(self::SOFT_DELETEABLE_FILTER_NAME, 'Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter');
        $this->em = $this->getMockSqliteEntityManager($evm, $config);
        $this->em->getFilters()->enable(self::SOFT_DELETEABLE_FILTER_NAME);
    }

    public function testSoftDeleteable()
    {
        $repo = $this->em->getRepository(self::ARTICLE_CLASS);
        $commentRepo = $this->em->getRepository(self::COMMENT_CLASS);

        $comment = new Comment();
        $commentField = 'comment';
        $commentValue = 'Comment 1';
        $comment->setComment($commentValue);
        $art0 = new Article();
        $field = 'title';
        $value = 'Title 1';
        $art0->setTitle($value);
        $art0->addComment($comment);

        $this->em->persist($art0);

        $this->em->flush();
        
        $art = $repo->findOneBy(array($field => $value));

        $this->assertNull($art->getDeletedAt());
        $this->assertNull($comment->getDeletedAt());

        $this->em->remove($art);
        $this->em->flush();

        $art = $repo->findOneBy(array($field => $value));
        $this->assertNull($art);
        $comment = $commentRepo->findOneBy(array($commentField => $commentValue));
        $this->assertNull($comment);

        // Now we deactivate the filter so we test if the entity appears in the result
        $this->em->getFilters()->disable(self::SOFT_DELETEABLE_FILTER_NAME);

        $this->em->clear();

        $art = $repo->findOneBy(array($field => $value));
        $this->assertTrue(is_object($art));
        $this->assertTrue(is_object($art->getDeletedAt()));
        $this->assertTrue($art->getDeletedAt() instanceof \DateTime);
        $comment = $commentRepo->findOneBy(array($commentField => $commentValue));
        $this->assertTrue(is_object($comment));
        $this->assertTrue(is_object($comment->getDeletedAt()));
        $this->assertTrue($comment->getDeletedAt() instanceof \DateTime);

        $art->setDeletedAt(null);
        $comment->setDeletedAt(null);
        $this->em->persist($art);
        $this->em->flush();

        $this->em->createQuery('UPDATE '.self::ARTICLE_CLASS.' a SET a.deletedAt = NULL')->execute();

        // Now we try with a DQL Delete query
        $this->em->getFilters()->enable(self::SOFT_DELETEABLE_FILTER_NAME);
        $dql = sprintf('DELETE FROM %s a WHERE a.%s = :%s',
            self::ARTICLE_CLASS, $field, $field);
        $query = $this->em->createQuery($dql);
        $query->setParameter($field, $value);
        $query->setHint(
            \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\SoftDeleteable\Query\TreeWalker\SoftDeleteableWalker'
        );
        $query->execute();

        $art = $repo->findOneBy(array($field => $value));
        $this->assertNull($art);

        // Now we deactivate the filter so we test if the entity appears in the result
        $this->em->getFilters()->disable(self::SOFT_DELETEABLE_FILTER_NAME);
        $this->em->clear();
        
        $art = $repo->findOneBy(array($field => $value));
        
        $this->assertTrue(is_object($art));
        $this->assertTrue(is_object($art->getDeletedAt()));
        $this->assertTrue($art->getDeletedAt() instanceof \DateTime);
    }

    protected function getUsedEntityFixtures()
    {
        return array(
            self::ARTICLE_CLASS,
            self::COMMENT_CLASS
        );
    }

    private function populate()
    {
        
    }
}