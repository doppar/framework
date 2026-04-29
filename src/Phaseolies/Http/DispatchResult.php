<?php

namespace Phaseolies\Http;

use Phaseolies\Application;

class DispatchResult
{
    /**
     * Indicates whether termination has already been performed.
     *
     * @var bool
     */
    protected bool $terminated = false;

    /**
     * @param Application $app
     * @param Request $request
     * @param Response|null $response
     * @param \Throwable|null $exception
     */
    public function __construct(
        protected Application $app,
        protected Request $request,
        protected ?Response $response = null,
        protected ?\Throwable $exception = null
    ) {}

    /**
     * Run the application termination lifecycle once.
     *
     * @return $this
     */
    public function terminate(): static
    {
        if ($this->terminated) {
            return $this;
        }

        $this->terminated = true;
        $this->app->terminate($this->request, $this->response, $this->exception);

        return $this;
    }

    /**
     * Get the resolved response instance, if one exists.
     *
     * @return Response|null
     */
    public function response(): ?Response
    {
        return $this->response;
    }

    /**
     * Get the captured exception for the request, if one exists.
     *
     * @return \Throwable|null
     */
    public function exception(): ?\Throwable
    {
        return $this->exception;
    }

    /**
     * Ensure termination still runs when the dispatch result is ignored.
     */
    public function __destruct()
    {
        $this->terminate();
    }
}
