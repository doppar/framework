<?php

namespace Phaseolies\Http\Response;

class JsonResponse extends BaseJsonResponse
{
    /**
     * Create a new JSON response instance
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @param bool $json
     * @return void
     */
    public function __construct($data = null, $status = 200, $headers = [], $options = 0, $json = false)
    {
        $this->encodingOptions = $options;

        parent::__construct($data, $status, $headers, $json);
    }
}
