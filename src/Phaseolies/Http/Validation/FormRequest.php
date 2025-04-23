<?php

namespace Phaseolies\Http\Validation;

use Phaseolies\Http\Request;
use Phaseolies\Http\Validation\Contracts\ValidatesWhenResolved;

abstract class FormRequest implements ValidatesWhenResolved
{
    /**
     * @var Request
     */
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Automatically validate the request when it is resolved.
     */
    public function resolvedFormRequestValidation(): void
    {
        if ($this->authorize()) {
            $this->validate();
        }
    }

    /**
     * Validate the request.
     *
     * @return array
     * @throws HttpResponseException
     */
    public function validate(): array
    {
        return $this->sanitize($this->rules());
    }

    /**
     * Delegate method calls to the underlying request instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->request->$method(...$parameters);
    }

    /**
     * Delegate property access to the underlying request instance.
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        return $this->request->$property;
    }
}
