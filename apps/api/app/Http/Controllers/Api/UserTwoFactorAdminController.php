<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserTwoFactorAdminController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function reset(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('users.manage_security')) {
            abort(403);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('No user was found with the provided email address.'),
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->tokens()->delete();

        if ($user->email) {
            $this->notificationService->queue([
                'tenant_id' => $user->tenant_id,
                'channel' => 'email',
                'recipient' => $user->email,
                'subject' => __('Your two-factor authentication was reset'),
                'body' => __(
                    'Dear :name, your two-factor authentication was reset by an administrator. The next time you log in you will be asked to configure a new authenticator.',
                    ['name' => $user->name ?? 'team member']
                ),
                'payload' => [
                    'reset_by' => Auth::id(),
                ],
            ]);
        }

        $this->auditLogService->record([
            'tenant_id' => $user->tenant_id,
            'user_id' => Auth::id(),
            'action' => 'user.two_factor_reset',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'payload' => [
                'email' => $user->email,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => __('Two-factor authentication has been reset and the user was notified.'),
        ]);
    }
}
