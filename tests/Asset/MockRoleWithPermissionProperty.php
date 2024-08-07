<?php

namespace LmcRbacMvcTest\Asset;

use Rbac\Role\RoleInterface;

class MockRoleWithPermissionProperty implements RoleInterface
{
    private $permissions = ['permission-property-a', 'permission-property-b'];

    public function getName(): string
    {
        return 'role-with-permission-property';
    }
    public function hasPermission($permission): bool
    {
        return false;
    }
}
