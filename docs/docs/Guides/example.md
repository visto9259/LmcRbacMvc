---
title: A Real World Example
sidebar_position: 1
---

In this example we are going to create a very little real world application. We will create a controller
`PostController` that interacts with a service called `PostService`. For the sake of simplicity we will only
cover the `delete`-methods of both parts.

Let's start by creating a controller that has the `PostService` as dependency:

```php
class PostController extends \Laminas\Mvc\Controller\AbstractActionController
{
    protected PostService $postService;

    public function __construct(PostService $postService)
    {
        $this->postService = $postService;
    }

    // addAction(), editAction(), etc...

    public function deleteAction()
    {
        $id = $this->params()->fromRoute('id');

        $this->postService->deletePost($id);

        return $this->redirect()->toRoute('posts');
    }
}
```

Since we have a dependency, let's inject it using the `ControllerManager`, we will do this inside our `Module` class

```php
class Module
{
    public function getConfig()
    {
        return [
            'controllers' => [
                'factories' => [
                    'PostController' => function ($container) {
                        return new PostController(
                            $container->get('PostService')
                        );
                    },
                ],
            ],
        ];
    }
}
```

Now that we have this in place let us quickly define our `PostService`. We will be using a Service that makes use
of Doctrine, so we require a `Doctrine\Persistence\ObjectManager` as dependency.

```php
use Doctrine\Persistence\ObjectManager;

class PostService
{
    protected $objectManager;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function deletePost($id)
    {
        $post = $this->objectManager->find('Post', $id);
        $this->objectManager->remove($post);
        $this->objectManager->flush();
    }
}
```

And for this one, too, let's quickly create the factory, again within our `Module` class.

```php
class Module
{
    // getAutoloaderConfig(), getConfig(), etc...

    public function getServiceConfig()
    {
        return [
            'factories' => [
                 'PostService' => function($container) {
                     return new PostService(
                         $container->get('doctrine.entitymanager.orm_default')
                     );
                 }
            ]
        ];
    }
}
```

With this set up we can now cover some best practices.

## Best practices

Ideally, you should not protect your applications using only guards (Route or Controller guards).
This leaves your application open for some undesired side-effects.
As a best practice you should protect all your services or controllers by injecting the authorization service.
But let's go step by step:

Assuming the application example above we can easily use LmcRbacMvc to protect our route using the following guard:

```php
return [
    'lmc_rbac' => [
        'guards' => [
            \Lmc\Rbac\Mvc\Guard\RouteGuard::class => [
                'post/delete' => ['admin']
            ]
        ]
    ]
];
```

Now, any users that do not have the "admin" role will receive a 403 error (unauthorized) when trying to access
the "post/delete" route. However, this does not prevent the service (which should contain the actual logic in a properly
design application) to be injected and used elsewhere in your code. For instance:

```php
class PostController
{
    protected $postService;

    public function createAction()
    {
        // this action may have been reached through the "forward" method, hence bypassing guards
        $this->postService->deletePost('2');
    }
}
```

You see the issue!

The solution is to inject the `AuthorizationService` into your services and check for the
permissions before doing anything wrong. So let's modify our previously created `PostService` class

```php
use Doctrine\Persistence\ObjectManager;
use Lmc\Rbac\Mvc\Service\AuthorizationService;

class PostService
{
    protected $objectManager;

    protected $authorizationService;

    public function __construct(
        ObjectManager        $objectManager,
        AuthorizationService $authorizationService
    ) {
        $this->objectManager        = $objectManager;
        $this->authorizationService = $authorizationService;
    }

    public function deletePost($id)
    {
        // First check permission
        if (!$this->authorizationService->isGranted('deletePost')) {
            throw new UnauthorizedException('You are not allowed !');
        }

        $post = $this->objectManager->find('Post', $id);
        $this->objectManager->remove($post);
        $this->objectManager->flush();
    }
}
```

Since we now have an additional dependency we should inject it through our factory, again within our `Module` class.

```php
class Module
{
    // getAutoloaderConfig(), getConfig(), etc...

    public function getServiceConfig()
    {
        return [
            'factories' => [
                 'PostService' => function($sm) {
                     return new PostService(
                         $sm->get('doctrine.entitymanager.orm_default'),
                         $sm->get('Lmc\Rbac\Mvc\Service\AuthorizationService') // This is new!
                     );
                 }
            ]
        ];
    }
}
```

Alternatively, you can also protect your controllers using the `isGranted` helper (you do not need to inject
the AuthorizationService then):

```php
class PostController
{
    protected $postService;

    public function createAction()
    {
        if (!$this->isGranted('deletePost')) {
            throw new UnauthorizedException('You are not allowed !');
        }

        $this->postService->deletePost('2');
    }
}
```

While protecting services is the more defensive way (because services are usually the last part of the logic flow),
it is often complicated to deal with.
If your application is architectured correctly, it is often simpler to protect your controllers.

### When using guards then?

In fact, you should see guards as a very efficient way to quickly reject access to a hierarchy of routes or a
whole controller. For instance, assuming you have the following route config:

```php
return [
    'router' => [
        'routes' => [
            'admin' => [
                'type'    => 'Literal',
                'options' => [
                    'route' => '/admin'
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'users' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/users'
                        ]
                    ],
                    'invoices' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/invoices'
                        ]
                    ]
                ]
            ]
        ]
    ]
};
```

You can quickly reject access to all admin routes using the following guard:

```php
return [
    'lmc_rbac' => [
        'guards' => [
            'Lmc\Rbac\Mvc\Guard\RouteGuard' => [
                'admin*' => ['admin']
            ]
        ]
    ]
];
```

## A Real World Application Part 2 - Only delete your own Posts

If you jumped straight to this section please notice that we assume you have the knowledge that we presented in the
previous example. In here we will cover a very common use case. Users of our Application should only have delete
permissions to their own content. So let's quickly refresh our `PostService` class:

```php
use Doctrine\Persistence\ObjectManager;

class PostService
{
    protected $objectManager;

    protected $authorizationService;

    public function __construct(
        ObjectManager        $objectManager,
        AuthorizationService $authorizationService
    ) {
        $this->objectManager        = $objectManager;
        $this->authorizationService = $authorizationService;
    }

    public function deletePost($id)
    {
        // First check permission
        if (!$this->authorizationService->isGranted('deletePost')) {
            throw new UnauthorizedException('You are not allowed !');
        }

        $post = $this->objectManager->find('Post', $id);
        $this->objectManager->remove($post);
        $this->objectManager->flush();
    }
}
```

As we can see, we check within our Service if the User of our Application is allowed to delete the post with a check
against the `deletePost` permission. Now how can we achieve that only a user who is the owner of the Post to be able to
delete his own post, but other users can't? We do not want to change our Service with more complex logic because this
is not the task of such service. The Permission-System should handle this. And we can, for this we have the
`AssertionPluginManager` and here is how to do it:

First of all we need to write an Assertion. The Assertion will return a boolean statement about the current
identity being the owner of the post.

```php
namespace Your\Namespace;

use Lmc\Rbac\Mvc\Assertion\AssertionInterface;
use Lmc\Rbac\Mvc\Service\AuthorizationService;

class MustBeAuthorAssertion implements AssertionInterface
{
    /**
     * Check if this assertion is true
     *
     * @param  AuthorizationService $authorization
     * @param  mixed                $post
     *
     * @return bool
     */
    public function assert(AuthorizationService $authorization, $post = null)
    {
        return $authorization->getIdentity() === $post->getAuthor();
    }
}
```

This simple `MustBeAuthorAssertion` will check against the current `$authorization` if it equals the identity of the
current context Author. The second parameter is called the "context". A context can be anything (an object, a scalar,
an array...) and only makes sense in the context of the assertion.

Imagine a user calls `http://my.dom/post/delete/42`, so obviously he wants to delete the Post-Entity with ID#42. In
this case Entity#42 is our Context! If you're wondering how the context gets there, bare with me. We will get to
this later.

Now that we have written the Assertion, we want to make sure that this assertion will always be called, whenever we
check for the `deletePost` permission. We don't want others to delete our previous content! For this we have the so
called `assertion_map`. In this map we glue `assertions` and `permissions` together.

```php
// config/autoload/lmc_rbac.global.php or wherever your LmcRbac configuration file is
return [
    'lmc_rbac' => [
        'assertion_map' => [
            'deletePost' => 'Your\Namespace\MustBeAuthorAssertion'
        ]
    ]
];
```

Now, whenever some test the `deletePost` permission, it will automatically call the `MustBeAuthorAssertion` from
the `AssertionPluginManager`. This plugin manager is configured to automatically add unknown classes to an invokable.
However, some assertions may need dependencies. You can manually configure the assertion plugin manager as
shown below:

```php
// config/autoload/lmc_rbac.global.php or wherever your LmcRbac configuration file is
return [
    'lmc_rbac' => [
        // ... other rbac stuff
        'assertion_manager' => [
            'factories' => [
                'AssertionWithDependency' => 'Your\Namespace\AssertionWithDependencyFactory'
            ]
        ]
    ]
];
```

Now we need to remember about the **context**. Somehow we need to let the `AssertionPluginManager` know about our
context. This is done by simply passing it to the `isGranted()` method. For this we need to modify our Service
one last time.

```php
use Doctrine\Persistence\ObjectManager;

class PostService
{
    protected $objectManager;

    protected $authorizationService;

    public function __construct(
        ObjectManager        $objectManager,
        AuthorizationService $autorizationService
    ) {
        $this->objectManager        = $objectManager;
        $this->authorizationService = $autorizationService;
    }

    public function deletePost($id)
    {
        // Note, we now need to query for the post of interest first!
        $post = $this->objectManager->find('Post', $id);

        // Check the permission now with a given context
        if (!$this->authorizationService->isGranted('deletePost', $post)) {
            throw new UnauthorizedException('You are not allowed !');
        }

        $this->objectManager->remove($post);
        $this->objectManager->flush();
    }
}
```

And there you have it. The context is injected into the `isGranted()` method and now the `AssertionPluginManager` knows
about it and can do its thing. Note that in reality, after you have queried for the `$post` you would check if `$post`
is actually a real post. Because if it is an empty return value then you should throw an exception earlier without
needing to check against the permission.

## A Real World Application Part 3 - Admins can delete everything

Often, you want users with a specific role to be able to have full access to everything. For instance, admins could
delete all the posts, even if they don't own it.

However, with the previous assertion, even if the admin has the permission `deletePost`, it won't work because
the assertion will evaluate to false.

Actually, the answer is quite simple: deleting my own posts and deleting others' posts should be treated like
two different permissions (it makes sense if you think about it). Therefore, admins will have the permission
`deleteOthersPost` (as well as the permission `deletePost`, because admin could write posts, too).

The assertion must therefore be modified like this:

```php
namespace Your\Namespace;

use Lmc\Rbac\Mvc\Assertion\AssertionInterface;
use Lmc\Rbac\Mvc\Service\AuthorizationService;

class MustBeAuthorAssertion implements AssertionInterface
{
    /**
     * Check if this assertion is true
     *
     * @param  AuthorizationService $authorization
     * @param  mixed                $context
     *
     * @return bool
     */
    public function assert(AuthorizationService $authorization, $context = null)
    {
        if ($authorization->getIdentity() === $context->getAuthor()) {
            return true;
        }

        return $authorization->isGranted('deleteOthersPost');
    }
}
```

## A Real World Application Part 4 - Checking permissions in the view

If some part of the view needs to be protected, you can use the shipped ```isGranted``` view helper.

For example, lets's say that only users with the permissions ```post.manage``` will have a menu item to acces
the adminsitration panel :

In your template post-index.phtml

```php
<ul class="nav">
    <li><a href="/">Home</a></li>
    <li><a href="/posts/list">View posts</a></li>
    <?php if ($this->isGranted('post.manage'): ?>
    <li><a href="/posts/admin">Manage posts</a></li>
    <?php endif ?>
</ul>
```

You can even protect your menu item regarding a role, by using the ```hasRole``` view helper :

```php
<ul class="nav">
    <li><a href="/">Home</a></li>
    <li><a href="/posts/list">View posts</a></li>
    <?php if ($this->hasRole('admin'): ?>
    <li><a href="/posts/admin">Manage posts</a></li>
    <?php endif ?>
</ul>
```

In this last example, the menu item will be hidden for users who don't have the ```admin``` role.

## Using LmcRbacMvc with Doctrine ORM

First your User entity class must implement `Lmc\Rbac\Mvc\Identity\IdentityInterface` :

```php
use LmccUser\Entity\User as LmcUserEntity;
use Lmc\Rbac\Mvc\Identity\IdentityInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity
 * @ORM\Table(name="user")
 */
class User extends LmcUserEntity implements IdentityInterface
{
    /**
     * @var Collection
     * @ORM\ManyToMany(targetEntity="HierarchicalRole")
     */
    private $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    /**
     * {@inheritDoc}
     */
    public function getRoles()
    {
        return $this->roles->toArray();
    }

    /**
     * Set the list of roles
     * @param Collection $roles
     */
    public function setRoles(Collection $roles)
    {
        $this->roles->clear();
        foreach ($roles as $role) {
            $this->roles[] = $role;
        }
    }

    /**
     * Add one role to roles list
     * @param \Rbac\Role\RoleInterface $role
     */
    public function addRole(RoleInterface $role)
    {
        $this->roles[] = $role;
    }
}
```
For this example we will use a more complex situation by using `Rbac\Role\HierarchicalRoleInterface` so the second step is to create HierarchicalRole entity class

```php
class HierarchicalRole implements HierarchicalRoleInterface
{
    /**
     * @var HierarchicalRoleInterface[]|\Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="HierarchicalRole")
     */
    protected $children;

    /**
     * @var PermissionInterface[]|\Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="Permission", indexBy="name", fetch="EAGER", cascade={"persist"})
     */
    protected $permissions;

    /**
     * Init the Doctrine collection
     */
    public function __construct()
    {
        $this->children    = new ArrayCollection();
        $this->permissions = new ArrayCollection();
    }

    /**
     * {@inheritDoc}
     */
    public function addChild(HierarchicalRoleInterface $child)
    {
        $this->children[] = $child;
    }

    /*
     * Set the list of permission
     * @param Collection $permissions
     */
    public function setPermissions(Collection $permissions)
    {
        $this->permissions->clear();
        foreach ($permissions as $permission) {
            $this->permissions[] = $permission;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addPermission($permission)
    {
        if (is_string($permission)) {
            $permission = new Permission($permission);
        }

        $this->permissions[(string) $permission] = $permission;
    }

    /**
     * {@inheritDoc}
     */
    public function hasPermission($permission)
    {
        // This can be a performance problem if your role has a lot of permissions. Please refer
        // to the cookbook to an elegant way to solve this issue

        return isset($this->permissions[(string) $permission]);
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren()
    {
        return $this->children->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function hasChildren()
    {
        return !$this->children->isEmpty();
    }
}
```

And the last step is to create a Permission entity class which is a very simple entity class. You don't have to do specific things!

You can find all entity examples in this folder : [Example](https://github.com/LM-Commons/LmcRbacMvc/tree/master/data)

You need one more configuration step. Indeed, how can the RoleProvider retrieve your role and permissions? For this you need to configure `LmcRbacMvc\Role\ObjectRepositoryRoleProvider` in your `lmc_rbac.global.php` file :
```php
        /**
         * Configuration for role provider
         */
        'role_provider' => [
            'LmcRbacMvc\Role\ObjectRepositoryRoleProvider' => [
                'object_manager'     => 'doctrine.entitymanager.orm_default', // alias for doctrine ObjectManager
                'class_name'         => 'User\Entity\HierarchicalRole', // FQCN for your role entity class
                'role_name_property' => 'name', // Name to show
            ],
        ],
```

Using DoctrineORM with LmcRbacMvc is very simple. You need to be aware of performance where there is a lot of permissions for roles.

## How to deal with roles with lot of permissions?

In very complex applications, your roles may have dozens of permissions. In the [/data/FlatRole.php.dist] entity
we provide, we configure the permissions association so that whenever a role is loaded, all of its permissions are also
loaded in one query (notice the `fetch="EAGER"`):

```php
/**
  * @ORM\ManyToMany(targetEntity="Permission", indexBy="name", fetch="EAGER")
  */
protected $permissions;
```

The `hasPermission` method is therefore really simple:

```php
public function hasPermission($permission)
{
    return isset($this->permissions[(string) $permission]);
}
```

However, with a lot of permissions, this method will quickly kill your database. What you can do is modfiy the Doctrine
mapping so that the collection is not actually loaded:

```php
/**
  * @ORM\ManyToMany(targetEntity="Permission", indexBy="name", fetch="LAZY")
  */
protected $permissions;
```

Then, modify the `hasPermission` method to use the Criteria API. The Criteria API is a Doctrine 2.2+ API that allows
your application to efficiently filter a collection without loading the whole collection:

```php
use Doctrine\Common\Collections\Criteria;

public function hasPermission($permission)
{
    $criteria = Criteria::create()->where(Criteria::expr()->eq('name', (string) $permission));
    $result   = $this->permissions->matching($criteria);

    return count($result) > 0;
}
```

> NOTE: This is only supported starting from Doctrine ORM 2.5!


