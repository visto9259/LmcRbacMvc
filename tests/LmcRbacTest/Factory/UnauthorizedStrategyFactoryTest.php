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

namespace LmcRbacTest\Factory;

use Interop\Container\ContainerInterface;
use LmcRbac\Factory\UnauthorizedStrategyFactory;

/**
 * @covers \LmcRbac\Factory\UnauthorizedStrategyFactory
 */
class UnauthorizedStrategyFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testFactory()
    {
        $unauthorizedStrategyOptions = $this->getMock('LmcRbac\Options\UnauthorizedStrategyOptions');

        $moduleOptionsMock = $this->getMock('LmcRbac\Options\ModuleOptions');
        $moduleOptionsMock->expects($this->once())
                          ->method('getUnauthorizedStrategy')
                          ->will($this->returnValue($unauthorizedStrategyOptions));

        $serviceLocatorMock = $this->prophesize('Laminas\ServiceManager\ServiceLocatorInterface');
        $serviceLocatorMock->willImplement(ContainerInterface::class);
        $serviceLocatorMock->get('LmcRbac\Options\ModuleOptions')->willReturn($moduleOptionsMock)->shouldBeCalled();

        $factory              = new UnauthorizedStrategyFactory();
        $unauthorizedStrategy = $factory->createService($serviceLocatorMock->reveal());

        $this->assertInstanceOf('LmcRbac\View\Strategy\UnauthorizedStrategy', $unauthorizedStrategy);
    }
}