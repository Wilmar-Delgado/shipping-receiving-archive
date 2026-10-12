<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->is('documents/*') && in_array($exception->getStatusCode(), [403, 404, 415], true)) {
                [$title, $message] = match ($exception->getStatusCode()) {
                    403 => ['Document access unavailable', 'You do not have access to this document or this action. It may have been discarded.'],
                    415 => ['Unsupported document file', 'This file cannot be previewed. Contact an administrator.'],
                    default => ['Document unavailable', 'This document or its original file is unavailable. Contact an administrator.'],
                };

                return response()->view('errors.archive', compact('title', 'message'), $exception->getStatusCode(), ['Cache-Control' => 'private, no-store']);
            }
        });
        $exceptions->render(function (PostTooLargeException $exception) {
            return response()->view('errors.archive', ['title' => 'This upload batch is too large', 'message' => 'The request exceeds the server upload limit. Select fewer files and try again.'], 413);
        });
    })->create();
