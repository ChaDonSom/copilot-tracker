<?php

namespace App\Http\Middleware;

use App\Services\GitHubCopilotService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GitHubTokenAuth
{
    public function __construct(
        private GitHubCopilotService $githubService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Missing authorization token',
                'message' => 'Provide your GitHub token as a Bearer token',
            ], 401);
        }

        try {
            $user = $this->githubService->findOrCreateUser($token);

            if (!$user) {
                return response()->json([
                    'error' => 'Invalid GitHub token',
                    'message' => 'Could not authenticate with the provided token',
                ], 401);
            }

            // Attach user to request for controllers
            $request->setUserResolver(fn() => $user);

            return $next($request);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Authentication failed',
                'message' => 'An error occurred during authentication: ' . $e->getMessage(),
            ], 500);
        }
    }
}
