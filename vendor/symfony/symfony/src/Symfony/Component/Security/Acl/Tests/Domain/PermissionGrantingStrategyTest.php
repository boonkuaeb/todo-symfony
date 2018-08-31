<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\Acl;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\PermissionGrantingStrategy;

class PermissionGrantingStrategyTest extends TestCase
{
    public function testIsGrantedObjectAcesHavePriority()
    {
        $strategy = new PermissionGrantingStrategy();
        $acl = $this->getAcl($strategy);
        $sid = new UserSecurityIdentity('johannes', 'Foo');

        $acl->insertClassAce($sid, 1);
        $acl->insertObjectAce($sid, 1, 0, false);
        $this->assertFalse($strategy->isGranted($acl, array(1), array($sid)));
    }

    public function testIsGrantedFallsBackToClassAcesIfNoApplicableObjectAceWasFound()
    {
        $strategy = new PermissionGrantingStrategy();
        $acl = $this->getAcl($strategy);
        $sid = new UserSecurityIdentity('johannes', 'Foo');

        $acl->insertClassAce($sid, 1);
        $this->assertTrue($strategy->isGranted($acl, array(1), array($sid)));
    }

    public function testIsGrantedFavorsLocalAcesOverParentAclAces()
    {
        $strategy = new PermissionGrantingStrategy();
        $sid = new UserSecurityIdentity('johannes', 'Foo');

        $acl = $this->getAcl($strategy);
        $acl->insertClassAce($sid, 1);

        $parentAcl = $this->getAcl($strategy);
        $acl->setParentAcl($parentAcl);
        $parentAcl->insertClassAce($sid, 1, 0, false);

        $this->assertTrue($strategy->isGranted($acl, array(1), array($sid)));
    }

    public function testIsGrantedFallsBackToParentAcesIfNoLocalAcesAreApplicable()
    {
        $strategy = new PermissionGrantingStrategy();
        $sid = new UserSecurityIdentity('johannes', 'Foo');
        $anotherSid = new UserSecurityIdentity('ROLE_USER', 'Foo');

        $acl = $this->getAcl($strategy);
        $acl->insertClassAce($anotherSid, 1, 0, false);

        $parentAcl = $this->getAcl($strategy);
        $acl->setParentAcl($parentAcl);
        $parentAcl->insertClassAce($sid, 1);

        $this->assertTrue($strategy->isGranted($acl, array(1), array($sid)));
    }

    /**
     * @expectedException \Symfony\Component\Security\Acl\Exception\NoAceFoundException
     */
    public function testIsGrantedReturnsExceptionIfNoAceIsFound()
    {
        $strategy = new PermissionGrantingStrategy();
        $acl = $this->getAcl($strategy);
        $sid = new UserSecurityIdentity('johannes', 'Foo');

        $strategy->isGranted($acl, array(1), array($sid));
    }

    public function testIsGrantedFirstApplicableEntryMakesUltimateDecisionForPermissionIdentityCombination()
    {
        $strategy = new PermissionGrantingStrategy();
        $acl = $this->getAcl($strategy);
        $sid = new UserSecurityIdentity('johannes', 'Foo');
        $aSid = new RoleSecurityIdentity('ROLE_USER');

        $acl->insertClassAce($aSid, 1);
        $acl->insertClassAce($sid, 1, 1, false);
        $acl->insertClassAce($sid, 1, 2);
        $this->assertFalse($strategy->isGranted($acl, array(1), array($sid, $aSid)));

        $acl->insertObjectAce($sid, 1, 0, false);
        $acl->insertObjectAce($aSid, 1, 1);
        $this->assertFalse($strategy->isGranted($acl, array(1), array($sid, $aSid)));
    }

    public function testIsGrantedCallsAuditLoggerOnGrant()
    {
        $strategy = new PermissionGrantingStrategy();
        $acl = $this->getAcl($strategy);
        $sid = new UserSecurityIdentity('johannes', 'Foo');

        $logger = $this->getMockBuilder('Symfony\Component\Security\Acl\Model\AuditLoggerInterface')->getMock();
        $logger
            ->expects($this->once())
            ->method('logIfNeeded')
        ;
        $strategy->setAuditLogger($logger);

        $acl->insertObjectAce($sid, 1);
        $acl->updateObjectAuditing(0, true, false);

        $this->assertTrue($strategy->isGranted($acl, array(1), array($sid)));
    }

    public function testIsGrantedCallsAuditLoggerOnDeny()
    {
        $strategy = new PermissionGrantingStrategy();
        $acl = $this->getAcl($strategy);
        $sid = new UserSecurityIdentity('johannes', 'Foo');

        $logger = $this->getMockBuilder('Symfony\Component\Security\Acl\Model\AuditLoggerInterface')->getMock();
        $logger
            ->expects($this->once())
            ->method('logIfNeeded')
        ;
        $strategy->setAuditLogger($logger);

        $acl->insertObjectAce($sid, 1, 0, false);
        $acl->updateObjectAuditing(0, false, true);

        $this->assertFalse($strategy->isGranted($acl, array(1), array($sid)));
    }

    /**
     * @dataProvider getAllStrategyTests
     */
    public function testIsGrantedStrategies($maskStrategy, $aceMask, $requiredMask, $result)
    {
        $strategy = new PermissionGrantingStrategy();
        $acl = $this->getAcl($strategy);
        $sid = new UserSecurityIdentity('johannes', 'Foo');

        $acl->insertObjectAce($sid, $aceMask, 0, true, $maskStrategy);

        if (false === $result) {
            if (method_exists($this, 'expectException')) {
                $this->expectException('Symfony\Component\Security\Acl\Exception\NoAceFoundException');
            } else {
                $this->setExpectedException('Symfony\Component\Security\Acl\Exception\NoAceFoundException');
            }

            $strategy->isGranted($acl, array($requiredMask), array($sid));
        } else {
            $this->assertTrue($strategy->isGranted($acl, array($requiredMask), array($sid)));
        }
    }

    public function getAllStrategyTests()
    {
        return array(
            array('all', 1 << 0 | 1 << 1, 1 << 0, true),
            array('all', 1 << 0 | 1 << 1, 1 << 2, false),
            array('all', 1 << 0 | 1 << 10, 1 << 0 | 1 << 10, true),
            array('all', 1 << 0 | 1 << 1, 1 << 0 | 1 << 1 || 1 << 2, false),
            array('any', 1 << 0 | 1 << 1, 1 << 0, true),
            array('any', 1 << 0 | 1 << 1, 1 << 0 | 1 << 2, true),
            array('any', 1 << 0 | 1 << 1, 1 << 2, false),
            array('equal', 1 << 0 | 1 << 1, 1 << 0, false),
            array('equal', 1 << 0 | 1 << 1, 1 << 1, false),
            array('equal', 1 << 0 | 1 << 1, 1 << 0 | 1 << 1, true),
        );
    }

    protected function getAcl($strategy)
    {
        static $id = 1;

        return new Acl($id++, new ObjectIdentity(1, 'Foo'), $strategy, array(), true);
    }
}
