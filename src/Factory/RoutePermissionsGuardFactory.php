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

namespace LmcRbacMvc\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use LmcRbacMvc\Guard\RouteGuard;
use LmcRbacMvc\Guard\RoutePermissionsGuard;
use LmcRbacMvc\Options\ModuleOptions;
use LmcRbacMvc\Service\AuthorizationService;

/**
 * Create a route guard for checking permissions
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @author  JM Lerouxw <jmleroux.pro@gmail.com>
 * @license MIT
 */
class RoutePermissionsGuardFactory implements FactoryInterface
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $options = [])
    {
        $this->setCreationOptions($options);
    }

    /**
     * {@inheritDoc}
     */
    public function setCreationOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return RoutePermissionsGuard
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        if (null === $options) {
            $options = [];
        }

        /* @var ModuleOptions $moduleOptions */
        $moduleOptions = $container->get(ModuleOptions::class);

        /* @var AuthorizationService $authorizationService */
        $authorizationService = $container->get(AuthorizationService::class);

        $routeGuard = new RoutePermissionsGuard($authorizationService, $options);
        $routeGuard->setProtectionPolicy($moduleOptions->getProtectionPolicy());

        return $routeGuard;
    }

    /**
     * @param \Laminas\ServiceManager\AbstractPluginManager|ServiceLocatorInterface $serviceLocator
     * @return RouteGuard
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator->getServiceLocator(), RoutePermissionsGuard::class, $this->options);
    }
}
