<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;

class VerifyEmailController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(EmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();

        return (new UserResource($request->user()->fresh()))
            ->additional(['message' => 'Your email address is verified.'])
            ->response();
    }
}
