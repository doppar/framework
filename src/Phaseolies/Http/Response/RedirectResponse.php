<?php

namespace Phaseolies\Http\Response;

use Phaseolies\Support\Router;
use Phaseolies\Session\MessageBag;
use Phaseolies\Http\Response;

class RedirectResponse extends Response
{
    /**
     * Sets the redirect target of this response.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setTargetUrl(string $url): static
    {
        if ('' === $url) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $this->setBody(
            sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, \ENT_QUOTES, 'UTF-8'))
        );

        $this->headers->set('Location', $url);
        $this->headers->set('Content-Type', 'text/html; charset=utf-8');
        if ($this->isSuccessful()) {
            MessageBag::clear();
        }

        return $this;
    }

    /**
     * Stores the current full URL in the session to allow previously intended location.
     *
     * @param string $default
     * @param int $status
     * @param array $headers
     * @param bool $secure
     * @return RedirectResponse
     */
    public function intended($default = '/', $status = 302, array $headers = [], ?bool $secure = null): RedirectResponse
    {
        $intended = session()->get('url.intended');

        if ($intended) {
            session()->forget('url.intended');
            return $this->to($intended, $status, $headers, $secure);
        }

        return $this->to($default, $status, $headers, $secure);
    }

    /**
     * Redirect to a specified URL.
     *
     * @param string $url
     * @param int $statusCode
     * @param array $headers
     * @param bool $secure
     * @return RedirectResponse
     */
    public function to(string $url, int $statusCode = 302, array $headers = [], ?bool $secure = null): RedirectResponse
    {
        $this->setStatusCode($statusCode);
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }

        if ($secure !== null) {
            $url = $this->ensureScheme($url, $secure);
        }

        $this->setTargetUrl($url);

        return $this;
    }

    /**
     * Redirect back to the previous page.
     * @param int $status
     * @param array $headers
     * @param bool $fallback
     * @return RedirectResponse
     */
    public function back($status = 302, $headers = [], ?bool $fallback = false): RedirectResponse
    {
        $url = request()->headers->get('referer') ?? '/';
        $this->setStatusCode($status);
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }

        $this->setTargetUrl($url);

        return $this;
    }

    /**
     * Ensure the URL uses the correct scheme (http or https).
     *
     * @param string $url The URL to modify.
     * @param bool $secure Whether to force HTTPS (true) or HTTP (false).
     * @return string The URL with the correct scheme.
     */
    protected function ensureScheme(string $url, bool $secure): string
    {
        // If the URL is already absolute (contains ://), parse it
        if (strpos($url, '://') !== false) {
            $parsedUrl = parse_url($url);

            // Rebuild the URL with the new scheme
            $scheme = $secure ? 'https' : 'http';
            $url = $scheme . '://';

            // Add the host if it exists
            if (isset($parsedUrl['host'])) {
                $url .= $parsedUrl['host'];
            }

            // Add the port if it exists
            if (isset($parsedUrl['port'])) {
                $url .= ':' . $parsedUrl['port'];
            }

            // Add the path if it exists
            if (isset($parsedUrl['path'])) {
                $url .= $parsedUrl['path'];
            }

            // Add the query string if it exists
            if (isset($parsedUrl['query'])) {
                $url .= '?' . $parsedUrl['query'];
            }

            // Add the fragment if it exists
            if (isset($parsedUrl['fragment'])) {
                $url .= '#' . $parsedUrl['fragment'];
            }
        } else {
            // For relative URLs, prepend the current host and scheme
            $scheme = $secure ? 'https' : 'http';
            $host = request()->headers->get('host') ?? $_SERVER['HTTP_HOST'];
            $url = $scheme . '://' . $host . $url;
        }

        return $url;
    }

    /**
     * Generates a URL for a named route and redirects to it.
     *
     * @param string $name The route name.
     * @param array $params The parameters for the route.
     * @return Response The response object with the redirect header.
     */
    public function route(string $name, array $params = []): RedirectResponse
    {
        $url = $this->resolveRouteUrl($name, $params);

        if (!$url) {
            throw new \InvalidArgumentException("Route [{$name}] not found.");
        }

        return $this->to($url);
    }

    /**
     * Resolve the URL for a named route.
     *
     * @param string $name The route name.
     * @param array $params The parameters for the route.
     * @return string|null The resolved URL or null if the route doesn't exist.
     */
    protected function resolveRouteUrl(string $name, array $params = []): ?string
    {
        if (!isset(Router::$namedRoutes[$name])) {
            return null;
        }

        $route = Router::$namedRoutes[$name];

        // Replace route parameters with actual values
        foreach ($params as $key => $value) {
            $route = preg_replace('/\{' . $key . '(:[^}]+)?}/', $value, $route, 1);
        }

        return $route;
    }

    /**
     * Redirect to an external URL.
     *
     * @param string $url The external URL to redirect to.
     * @param int $statusCode The HTTP status code for the redirect (default is 302).
     * @return self The response object with the redirect header.
     */
    public function away(string $url, int $statusCode = 302): RedirectResponse
    {
        return $this->to($url, $statusCode);
    }
    /**
     * Flash errors to the session.
     *
     * @param array $errors
     * @return $this
     */
    public function withErrors($errors): RedirectResponse
    {
        MessageBag::set('errors', $errors);

        return $this;
    }

    /**
     * Flash input data to the session.
     *
     * @return $this
     */
    public function withInput(): RedirectResponse
    {
        MessageBag::flashInput();

        return $this;
    }

    /**
     * Set the flash message
     *
     * @param string $type
     * @param string $message
     * @return RedirectResponse
     */
    public function with(string $type, string $message): RedirectResponse
    {
        session()->put($type, $message);

        return $this;
    }
}
