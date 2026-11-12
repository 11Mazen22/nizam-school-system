<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Middleware\CsrfMiddleware;
use App\Request;

/**
 * GET /csrf-token
 *
 * A queued submission is replayed long after its form was rendered. The CSRF
 * token is stable for the life of the session (CsrfMiddleware does not rotate
 * it), but a session can expire while the device is offline -- so the queue
 * asks for the current token immediately before replaying instead of trusting
 * the one captured with the form. Authenticated only: this is session state.
 */
final class CsrfController extends Controller
{
    public function token(Request $request): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode(['token' => CsrfMiddleware::token()], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
