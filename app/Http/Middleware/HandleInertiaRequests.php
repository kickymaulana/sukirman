<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $notifications = [];
        $unreadCount = 0;

        if ($user) {
            $unreadCount = $user->unreadNotifications()->count();
            $notifications = $user->unreadNotifications()->latest()->take(10)->get()->map(function ($n) {
                $data = $n->data;
                return [
                    'id' => $n->id,
                    'message' => $data['message'] ?? '',
                    'mr_id' => $data['mr_id'] ?? null,
                    'mr_number' => $data['mr_number'] ?? '',
                    'time' => $n->created_at->diffForHumans(),
                ];
            });
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'app_url' => config('app.url'),
            'csrf_token' => csrf_token(),
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ];
    }
}
