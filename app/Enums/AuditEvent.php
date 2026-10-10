<?php

namespace App\Enums;

enum AuditEvent: string
{
    case SuperImposeStarted = 'super_impose.started';
    case SuperImposeExited = 'super_impose.exited';
    case PlatformUserSignedIn = 'platform_user.signed_in';
    case UserRolesChanged = 'user.roles_changed';
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionSuspended = 'subscription.suspended';
    case SubscriptionResumed = 'subscription.resumed';
    case SubscriptionExtended = 'subscription.extended';
    case InvitationCreated = 'invitation.created';
    case InvitationRevoked = 'invitation.revoked';
    case TenantRegistered = 'tenant.registered';
    case MigrationsRun = 'migrations.run';

    public function label(): string
    {
        return match ($this) {
            self::SuperImposeStarted => 'Super Impose started',
            self::SuperImposeExited => 'Super Impose exited',
            self::PlatformUserSignedIn => 'Platform user signed in',
            self::UserRolesChanged => 'User roles changed',
            self::SubscriptionActivated => 'Subscription activated',
            self::SubscriptionUpdated => 'Subscription updated',
            self::SubscriptionSuspended => 'Subscription suspended',
            self::SubscriptionResumed => 'Subscription resumed',
            self::SubscriptionExtended => 'Subscription extended',
            self::InvitationCreated => 'Invitation created',
            self::InvitationRevoked => 'Invitation revoked',
            self::TenantRegistered => 'Tenant registered',
            self::MigrationsRun => 'Migrations run',
        };
    }
}
