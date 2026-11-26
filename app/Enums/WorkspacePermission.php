<?php

namespace App\Enums;

enum WorkspacePermission: string
{
    case AdministrationAccess = 'administration.access';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case TeamsManage = 'teams.manage';
    case WorkQueuesManage = 'work_queues.manage';
    case P18View = 'p18.view';
    case P18Manage = 'p18.manage';
    case RecordsViewAll = 'records.view_all';
    case RecordsManageAll = 'records.manage_all';
    case RecordsViewShared = 'records.view_shared';
    case QueuesViewAll = 'queues.view_all';
    case QueuesViewShared = 'queues.view_shared';

    /**
     * Human-readable permission label shown in the role matrix.
     */
    public function label(): string
    {
        return match ($this) {
            self::AdministrationAccess => 'Open administration',
            self::UsersManage => 'Manage users',
            self::RolesManage => 'Manage roles',
            self::TeamsManage => 'Manage teams',
            self::WorkQueuesManage => 'Manage work queues',
            self::P18View => 'View access & upgrade states',
            self::P18Manage => 'Change upgrade states',
            self::RecordsViewAll => 'View all records',
            self::RecordsManageAll => 'Manage all records',
            self::RecordsViewShared => 'View shared records',
            self::QueuesViewAll => 'View all queues',
            self::QueuesViewShared => 'View assigned queues',
        };
    }
}
