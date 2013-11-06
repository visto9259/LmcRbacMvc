<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbacTest\Service;
use Zend\Permissions\Rbac\Rbac;
use Zend\ServiceManager\Config;
use ZfcRbac\Assertion\AssertionPluginManager;
use ZfcRbac\Service\AuthorizationService;
use ZfcRbac\Service\RbacEvent;
use ZfcRbacTest\Asset\InvokableAssertion;

/**
 * @covers \ZfcRbac\Service\AuthorizationService
 */
class AuthorizationServiceTest extends \PHPUnit_Framework_TestCase
{
    public function grantedProvider()
    {
        return array(
            // Simple is granted
            array(
                'guest',
                'read',
                null,
                true
            ),

            // Simple is allowed from parent
            array(
                'member',
                'read',
                null,
                true
            ),

            // Simple is refused
            array(
                'guest',
                'write',
                null,
                false
            ),

            // Simple is refused from parent
            array(
                'guest',
                'delete',
                null,
                false
            ),

            // Simple is refused from dynamic assertion
            array(
                'member',
                'read',
                function() { return false; },
                false
            ),

            // Simple is refused from no role
            array(
                array(),
                'read',
                null,
                false
            ),
        );
    }

    /**
     * @dataProvider grantedProvider
     */
    public function testGranted($role, $permission, $assertion = null, $isGranted)
    {
        // Let's fill the RBAC container with some values
        $rbac = new Rbac();

        $rbac->addRole('admin');
        $rbac->addRole('member', 'admin');
        $rbac->addRole('guest', 'member');

        $rbac->getRole('guest')->addPermission('read');
        $rbac->getRole('member')->addPermission('write');
        $rbac->getRole('admin')->addPermission('delete');

        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');
        $identityProvider->expects($this->once())
                         ->method('getIdentityRoles')
                         ->will($this->returnValue($role));

        $pluginManager = new AssertionPluginManager();

        $authorizationService = new AuthorizationService($rbac, $identityProvider, $pluginManager);

        $this->assertEquals($isGranted, $authorizationService->isGranted($permission, $assertion));
    }

    /**
     * Assert that event to load roles and permissions is not triggered if no role can be found in an
     * identity, because it will be refused anyway
     */
    public function testDoesNotLoadIfNoIdentityIsFound()
    {
        $rbac = new Rbac();

        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');
        $identityProvider->expects($this->once())
                         ->method('getIdentityRoles')
                         ->will($this->returnValue(array()));

        $pluginManager = new AssertionPluginManager();

        $authorizationService = new AuthorizationService($rbac, $identityProvider, $pluginManager);

        $eventManager = $this->getMock('Zend\EventManager\EventManagerInterface');
        $authorizationService->setEventManager($eventManager);

        $eventManager->expects($this->never())
                     ->method('trigger');

        $authorizationService->isGranted('foo');
    }

    public function testLoadRolesAndPermissions()
    {
        $rbac = new Rbac();

        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');
        $identityProvider->expects($this->exactly(2))
                         ->method('getIdentityRoles')
                         ->will($this->returnValue(array('role1')));

        $pluginManager = new AssertionPluginManager();

        $authorizationService = new AuthorizationService($rbac, $identityProvider, $pluginManager);

        $eventManager = $this->getMock('Zend\EventManager\EventManagerInterface');
        $authorizationService->setEventManager($eventManager);

        $eventManager->expects($this->exactly(2))
                     ->method('trigger')
                     ->with($this->logicalOr(
                        $this->equalTo(RbacEvent::EVENT_LOAD_ROLES),
                        $this->equalTo(RbacEvent::EVENT_LOAD_PERMISSIONS)
                     ));

        // Call twice to assert initialization is not done twice
        $authorizationService->isGranted('foo');
        $authorizationService->isGranted('foo');
    }

    public function testCanRegisterAssertions()
    {
        $rbac             = new Rbac();
        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');

        $assertionPluginManager = new AssertionPluginManager(new Config(array(
            'invokables' => array(
                'ZfcRbacTest\Asset\InvokableAssertion' => 'ZfcRbacTest\Asset\InvokableAssertion'
            )
        )));

        $authorizationService = new AuthorizationService($rbac, $identityProvider, $assertionPluginManager);

        $this->assertFalse($authorizationService->hasAssertion('edit'));

        // Test with assertions fetched from plugin manager
        $authorizationService->registerAssertion('edit', 'ZfcRbacTest\Asset\InvokableAssertion');
        $this->assertTrue($authorizationService->hasAssertion('edit'));
        $this->assertInstanceOf('ZfcRbacTest\Asset\InvokableAssertion', $authorizationService->getAssertion('edit'));

        // Test with a concrete instance
        $assertion = new InvokableAssertion();
        $authorizationService->registerAssertion('edit', $assertion);
        $this->assertSame($assertion, $authorizationService->getAssertion('edit'));

        // Test with a callable
        $assertion = function(Rbac $rbac) {
            return true;
        };

        $authorizationService->registerAssertion('edit', $assertion);
        $this->assertSame($assertion, $authorizationService->getAssertion('edit'));
        $this->assertInternalType('callable', $authorizationService->getAssertion('edit'));
    }
}