<?php

namespace App\Domain\Auth\Enums;

class RoleName
{
    public const SUPER_ADMIN = 'Super Administrator';
    public const SUPER_ADMIN_ALT = 'Super Admin';
    public const SUPER_ADMIN_SLUG = 'super_admin';

    public const IT_ADMIN = 'IT Administrator';
    public const IT_ADMIN_ALT = 'Administrator';
    public const IT_ADMIN_SLUG = 'it_admin';

    public const DEPT_ADMIN = 'Department Administrator';
    public const DEPT_ADMIN_SLUG = 'dept_admin';

    public const APPROVAL = 'Approval';
    public const USER = 'User';
    public const FACULTY = 'faculty';
    public const STUDENT = 'student';

    /**
     * All system-level administrative role identifiers.
     *
     * @return array<int, string>
     */
    public static function adminRoles(): array
    {
        return [
            self::SUPER_ADMIN,
            self::SUPER_ADMIN_ALT,
            self::SUPER_ADMIN_SLUG,
            self::IT_ADMIN,
            self::IT_ADMIN_ALT,
            self::IT_ADMIN_SLUG,
        ];
    }

    /**
     * All escalation recipient role identifiers.
     *
     * @return array<int, string>
     */
    public static function escalationRoles(): array
    {
        return self::adminRoles();
    }
}
