<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteRequest;
use App\Services\Auth\InvitationService;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function store(InviteRequest $request): JsonResponse
    {
        return $this->invitationService->create(
            $request->validated(),
            $request->user(),
        );
    }
}
