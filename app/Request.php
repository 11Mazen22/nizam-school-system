<?php

declare(strict_types=1);

namespace App;

/**
 * Nizam -- request wrapper. Deliberately thin: a typed way to read the
 * current request's method/path/input without controllers touching PHP's
 * superglobals directly (§C: front controller / router foundation).
 */
final class Request
{
    private string $method;
    private string $path;
    /** @var array<string,mixed> */
    private array $body;
    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,string> */
    private array $routeParams = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $rawPath = (string) parse_url($uri, PHP_URL_PATH);
        // Routes are registered as plain literal strings (e.g. "/dashboard"),
        // so a percent-encoded request for the same resource (e.g.
        // "/dash%62oard") must be decoded before matching or it 404s despite
        // being a valid request for a route that exists.
        $this->path = rtrim(rawurldecode($rawPath), '/') ?: '/';
        $this->body = $_POST;
        $this->query = $_GET;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * POST body only -- deliberately does NOT fall back to the query string.
     * Every current caller (form fields, the CSRF token) is security- or
     * correctness-sensitive precisely because it must not be satisfiable via
     * a GET parameter (a CSRF token in a URL leaks through history/Referer/
     * server logs; a form field silently accepting `?field=` alongside a
     * missing POST value is a class of bug waiting to happen once Phase 6
     * adds more forms). A single merged accessor existed here originally and
     * was removed rather than kept "for convenience" once review showed
     * every real call site actually wanted one specific source, never both.
     */
    public function post(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Query string only -- e.g. the /lang switch, which is a GET-only concern by nature. */
    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /**
     * POST body only, for a multi-select field submitted as key[] (e.g.
     * teacher qualifications, §E Teachers module). Every element is coerced
     * to a plain string -- a malicious client sending a nested array per
     * element (key[][]=x) collapses to that element being dropped rather
     * than an unexpected array reaching a repository's bind parameters.
     *
     * @return string[]
     */
    public function postArray(string $key): array
    {
        $value = $this->body[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn (mixed $v): ?string => is_string($v) || is_numeric($v) ? (string) $v : null,
            $value
        ), static fn (?string $v): bool => $v !== null));
    }

    /**
     * POST body only, for a per-row field submitted as key[rowId] (e.g. the
     * Promotion Preview form's one action/class_id choice per enrollment
     * row). Every value is coerced to a plain string with the same
     * one-level-only discipline as postArray() -- a nested value collapses
     * to being dropped, never passed through as an array.
     *
     * @return array<string,string>
     */
    public function postMap(string $key): array
    {
        $value = $this->body[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $rowKey => $v) {
            if ((is_string($rowKey) || is_int($rowKey)) && (is_string($v) || is_numeric($v))) {
                $result[(string) $rowKey] = (string) $v;
            }
        }
        return $result;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * One $_FILES[...] entry, e.g. an uploaded photo/logo -- null if the
     * field wasn't part of this submission at all (a normal, non-error case:
     * an edit form's photo field is optional on every save). Callers that
     * need to distinguish "no field" from "field present but no file chosen"
     * check the array's own 'error' key (UPLOAD_ERR_NO_FILE) themselves --
     * both are legitimate "nothing to do" outcomes here, so this stays a
     * plain passthrough rather than collapsing them.
     *
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
     */
    public function file(string $key): ?array
    {
        $value = $_FILES[$key] ?? null;
        return is_array($value) ? $value : null;
    }

    /**
     * Set by Router after a pattern match (e.g. "/students/{id}/edit") --
     * Phase 6's per-record routes, anticipated but not built by Phase 4/5's
     * router (its own docblock said as much). Deliberately stored on Request
     * rather than passed as an extra controller-method argument: every
     * existing Phase 4/5 controller method already takes just (Request) and
     * this keeps all of them compiling unchanged instead of a mechanical
     * signature edit across files this phase has no other reason to touch.
     *
     * @param array<string,string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /** A route param is always a validated \d+ segment (see Router::match()) -- safe to cast. */
    public function paramInt(string $key): ?int
    {
        return isset($this->routeParams[$key]) ? (int) $this->routeParams[$key] : null;
    }

    /** Phase 7's non-numeric route params ("{key}", "{format}") -- already constrained to [a-z0-9_-]+ by Router::match(), never arbitrary. */
    public function paramString(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }
}
