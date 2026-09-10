<?php

declare(strict_types=1);

namespace App;

/**
 * Nizam -- base controller (§C: "Controllers validate input shape, call a
 * service, hand data to a view" -- no business logic, no SQL, ever). Thin on
 * purpose: two helpers every controller needs, nothing else.
 */
abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $name, array $data = []): void
    {
        Response::view($name, $data);
    }

    protected function redirect(string $path): void
    {
        Response::redirect($path);
    }
}
