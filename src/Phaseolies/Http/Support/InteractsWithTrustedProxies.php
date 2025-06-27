<?php

namespace Phaseolies\Http\Support;

trait InteractsWithTrustedProxies
{
    /**
     * Get the trusted proxies
     *
     * @return array
     */
    protected function getTrustedProxies(): array
    {
        if (is_string($this->proxies)) {
            return $this->resolveSpecialProxies($this->proxies);
        }

        return $this->proxies;
    }

    /**
     * Resolve special proxy values
     *
     * @param string $proxies
     * @return array
     */
    protected function resolveSpecialProxies(string $proxies): array
    {
        switch ($proxies) {
            case '*':
                return ['*'];

            case '**':
                return $this->privateSubnets;

            default:
                return [$proxies];
        }
    }

    /**
     * Get the proxy header configuration
     *
     * @return int
     */
    protected function getTrustedHeaders(): int
    {
        return $this->headers;
    }

    /**
     * Set the trusted proxies
     *
     * @param array|string $proxies
     * @return self
     */
    public function setProxies($proxies): self
    {
        $this->proxies = $proxies;

        return $this;
    }

    /**
     * Set the trusted headers
     *
     * @param int $headers
     * @return self
     */
    public function setHeaders(int $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Set private subnets
     *
     * @param array $subnets
     * @return self
     */
    public function setPrivateSubnets(array $subnets): self
    {
        $this->privateSubnets = $subnets;

        return $this;
    }
}