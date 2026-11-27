<?php

namespace App\Services\Travel;

use App\Models\Employee;
use App\Models\TravelDocument;
use App\Models\User;
use App\Services\Scm\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TravelAccess
{
    public const MANAGER_LEVELS = ['manager', 'general-manager', 'director', 'cbo', 'coo-1', 'coo-2', 'coo-3', 'chairman', 'vice-chairman'];

    public static function manager(User $user): bool
    {
        $employee = $user->employee;
        if (filled($employee?->position_level)) {
            return in_array($employee->position_level, self::MANAGER_LEVELS, true);
        }

        return (bool) preg_match('/^(?:hrga|hr|human resources(?: and general affairs)?|general affairs)\s+manager$/i', trim($employee?->position ?? ''));
    }

    public static function has(?User $user, string $permission): bool
    {
        return Access::has($user, $permission);
    }

    public static function canArrangeDocument(?User $user): bool
    {
        return $user?->canChooseDocumentRequester() === true;
    }

    public static function canCreate(?User $user, string $kind): bool
    {
        return in_array($kind, ['spd', 'lpj'], true)
            && (self::has($user, "travel.{$kind}.create") || self::has($user, "travel.{$kind}.manage"));
    }

    public static function canOpen(?User $user, string $kind): bool
    {
        return self::canCreate($user, $kind) || self::has($user, "travel.{$kind}.view") || self::has($user, 'travel.approve')
            || ($user && $user->isActiveForAccess() && in_array($kind, ['spd', 'lpj'], true)
                && Schema::hasTable('travel_approvals') && self::assignedDocuments($user, $kind)->exists());
    }

    public static function query(User $user, ?string $kind = null): Builder
    {
        $query = TravelDocument::query()->when($kind, fn ($query) => $query->where('kind', $kind));
        if ($user->isSystemAdministrator()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereRaw('1 = 0');
            foreach (['spd', 'lpj'] as $type) {
                if (self::canCreate($user, $type)) {
                    $scope->orWhere(fn ($own) => $own->where('kind', $type)->where(fn ($author) => $author->where('created_by', $user->id)->orWhere('data->requester_id', $user->id)->orWhere('employee_id', $user->employee?->id)));
                }
                if (self::has($user, "travel.{$type}.view") || self::has($user, "travel.{$type}.manage")) {
                    $scope->orWhere(fn ($department) => $department->where('kind', $type)->whereIn('department_id', $user->accessibleDepartmentIds()));
                }
            }
            if (self::has($user, 'travel.approve')) {
                $scope->orWhereHas('approvals', fn ($assigned) => $assigned->where('user_id', $user->id));
            } elseif ($user->isActiveForAccess()) {
                $scope->orWhereIn('id', self::assignedDocuments($user)->select('travel_documents.id'));
            }
            if (self::canCreate($user, 'lpj')) {
                $scope->orWhere(fn ($parent) => $parent->where('kind', 'spd')->where('status', 'approved')
                    ->where(fn ($own) => $own->where('created_by', $user->id)->orWhere('data->requester_id', $user->id)->orWhere('employee_id', $user->employee?->id)));
            }
        });
    }

    public static function editable(User $user, TravelDocument $document): bool
    {
        if (! in_array($document->status, ['draft', 'returned'], true)) {
            return false;
        }

        return $user->isSystemAdministrator()
            || (self::canCreate($user, $document->kind) && ((int) $document->created_by === (int) $user->id || (int) ($document->data['requester_id'] ?? 0) === (int) $user->id || (int) $document->employee_id === (int) $user->employee?->id))
            || (self::has($user, "travel.{$document->kind}.manage") && $user->canAccessDepartment($document->department_id));
    }

    public static function employees(User $user, string $kind, ?TravelDocument $document = null): Builder
    {
        $query = Employee::query()->where('is_active', true)->whereNotNull('department_id')->whereHas('user')->with(['department', 'supervisor.user']);
        if ($user->isSystemAdministrator()) {
            return $query;
        }
        if (self::has($user, "travel.{$kind}.manage")) {
            return $query->whereIn('department_id', $user->accessibleDepartmentIds());
        }

        return $query->where(function (Builder $scope) use ($user, $document): void {
            $scope->where('user_id', $user->id);
            if ($document && self::editable($user, $document) && (int) ($document->data['requester_id'] ?? 0) === (int) $user->id) {
                $scope->orWhereKey($document->employee_id);
            }
        });
    }

    public static function approver(User $user): bool
    {
        return $user->isActiveForAccess() && self::has($user, 'travel.approve');
    }

    private static function assignedDocuments(User $user, ?string $kind = null): Builder
    {
        return TravelDocument::query()->when($kind, fn ($query) => $query->where('kind', $kind))
            ->where('data->approval_policy->assigned_access', true)
            ->whereIn('data->approval_policy->source', ['khafafi', 'khafafi-master'])
            ->whereHas('approvals', fn ($assigned) => $assigned->where('user_id', $user->id));
    }

    public static function documentApprover(User $user, TravelDocument $document): bool
    {
        return $user->isActiveForAccess() && (self::approver($user)
            || self::assignedDocuments($user, $document->kind)->whereKey($document->id)->exists());
    }
}
