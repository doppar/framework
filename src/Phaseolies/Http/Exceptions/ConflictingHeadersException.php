<?php

namespace Phaseolies\Http\Exceptions;

/**
 * Thrown when a trusted proxy sends forwarding headers that contradict each
 * other (for example "Forwarded" and "X-Forwarded-For" naming different
 * clients). The request is rejected as a 400 Bad Request.
 */
class ConflictingHeadersException extends BadRequestHttpException
{
}
