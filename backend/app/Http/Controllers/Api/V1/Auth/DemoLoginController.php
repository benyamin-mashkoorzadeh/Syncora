<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Demo\ProvisionDemoEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        ProvisionDemoEnvironment $provisionDemoEnvironment,
    ): JsonResponse {
        if (! config('demo.enabled')) {
            return $this->unavailable();
        }

        $workspace = $provisionDemoEnvironment->handle();
        $user = User::query()->where('email', ProvisionDemoEnvironment::USER_EMAIL)->sole();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new UserResource($user))
            ->additional([
                'message' => 'Welcome to the Syncora demo.',
                'demo' => ['workspace_slug' => $workspace->slug],
            ])
            ->response();
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'The Syncora demo is currently unavailable. Please try again later.',
        ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}
