<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phaseolies\Http\Exceptions;

/**
 * @author Peter Dietrich <xosofox@gmail.com>
 */
class LockedHttpException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        parent::__construct(423, $message, $previous, $headers, $code);
    }
}
