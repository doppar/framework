<?php

namespace Phaseolies\Http\Validation;

use Phaseolies\Error\JsonErrorRenderer;
use Phaseolies\Session\MessageBag;
use Phaseolies\Http\Support\ValidationRules;
use Phaseolies\Http\Response;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Validation\Bind;

trait Rule
{
    use ValidationRules;

    /**
     * Validate the input data against the given rules.
     *
     * @access public
     * @param array $rules
     * @return null|array|\Phaseolies\Http\Response
     */
    public function sanitize(array $rules): array|Response
    {
        $errors = [];
        $input = $this->all();
        MessageBag::flashInput();

        if (is_array($input)) {
            foreach ($rules as $fieldName => $value) {
                // Custom rule class bound via Bind::to(new Rule())->context([...])
                if ($value instanceof Bind) {
                    $errorMessage = $value->evaluate($fieldName, $input);
                    if ($errorMessage) {
                        $errors[$fieldName][] = $errorMessage;
                    }
                    continue;
                }

                $fieldRules = explode("|", $value);
                foreach ($fieldRules as $rule) {
                    $ruleValue = $this->_getRuleSuffix($rule);
                    $rule = $this->_removeRuleSuffix($rule);
                    $errorMessage = $this->sanitizeUserRequest($input, $fieldName, $rule, $ruleValue);
                    if ($errorMessage) {
                        $errors[$fieldName][] = $errorMessage;
                    }
                }
            }
        }

        if (!empty($errors)) {
            if (request()->isAjax() || request()->isApiRequest()) {
                $exception = new HttpResponseException(
                    $errors,
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );

                $exception->setResponse(
                    (new JsonErrorRenderer())->render(
                        $exception,
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        $errors
                    )
                );

                throw $exception;
            }

            $this->setErrors($errors);
            foreach ($errors as $key => $error) {
                session()->putPeek($key, implode(' ', (array)$error));
            }

            $response = redirect()->back()->withInput()->withErrors($errors);

            throw (new HttpResponseException($errors, $response->getStatusCode()))
                ->setResponse($response);
        }

        $this->setPassedData($input);
        MessageBag::clear();

        return $input;
    }
}
