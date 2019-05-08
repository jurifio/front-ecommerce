<?php

namespace bamboo\ecommerce\business;

/**
 * Class CForm
 * @package bamboo\app\business
 */
class CForm
{
    protected $name;
    protected $values = [];
    protected $errors = [];
    protected $outcome = [];
    protected $csrfToken; //TODO: Implementare la protezione CSRF
    protected $userDefinedToken;

    const FORM_EMAIL_INVALID = 1000;
    const FORM_FIELDS_DO_NOT_MATCH = 1050;
    const FORM_STRING_INVALID = 1100;
    const FORM_PASSWORD_TOO_LONG = 1150;
    const FORM_PHONE_INVALID = 1200;
    const FORM_DATE_OUT_OF_BOUNDS = 1250;
    const FORM_FIELD_TOO_LONG = 1300;
    const FORM_EMAIL_EXISTS_IN_DATABASE = 1350;
    const FORM_EMAIL_EXISTS_IN_NEWSLETTER_DATABASE = 1351;
    const FORM_POSTCODE_INVALID = 1400;
    const FORM_TEXT_INVALID = 1500;
    const FORM_DATE_INVALID = 1600;
    const FORM_VALUE_OUT_OF_RANGE = 1700;
    const FORM_PASSWORD_TOO_SHORT = 1800;
    const FORM_PASSWORD_NOT_COMPLEX_ENOUGH = 1900;
    const FORM_DATABASE_FAIL = 2000;
    const FORM_TOKEN_ERROR = 2100;
    const FORM_RECAPTCHA_ERROR = 2200;
    const FORM_MANDATORY_FIELD_MISSING = 2300;
    const FORM_MANDATORY_LEGAL_CHECK_MISSING = 2400;

    /**
     * @param $formName
     * @param array $values
     */
    public function __construct($formName, array $values)
    {
        $this->name = $formName;
        $this->values = $values;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param $key
     * @return null
     */
    public function getValue($key)
    {
        if(isset($this->values[$key])) return $this->values[$key];
        return null;
    }

    /**
     * @param array $errors
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return (bool) count($this->errors);
    }

    /**
     * @param $field
     */
    public function deleteField($field)
    {
        unset($this->values[$field]);
    }

    /**
     * @param $field
     * @param $value
     */
    public function setValue($field, $value)
    {
        $this->values[$field] = $value;
    }

    /**
     * @return array
     */
    public function getOutcome()
    {
        return $this->outcome;
    }

    /**
     * @param $key
     * @param $outcome
     */
    public function setOutcome($key, $outcome)
    {
        $this->outcome[$key] = $outcome;
    }
}