<?php

class Validator {

    private $rules;
	private $defaultRules = [
		[
			"type" => "alphanumeric",
			"field" => "username",
			"pretty" => "Přihlašovací jméno",
            "required" => true,
			"minLength" => 5,
			"maxLength" => 30,
		],
        [
            "type" => "string",
            "field" => "display_name",
            "pretty" => "Zobrazované jméno",
            "required" => true,
            "minLength" => 3,
            "maxLength" => 30,
        ],
		[
			"type" => "email",
			"field" => "email",
			"pretty" => "E-mailová adresa",
            "required" => true,
			"minLength" => 8,
			"maxLength" => 30,
		],
		[
			"type" => "string",
			"field" => "password",
			"pretty" => "Heslo",
            "required" => true,
			"minLength" => 6,
			"maxLength" => 127,
		],
	];

    /*

  public 'email_current' => string 'jmrkva@ukf.sk' (length=13)
  public 'email_new' => string '' (length=0)
  public 'email_check' => string '' (length=0)
  public 'password_current' => string 'testing' (length=7)
  public 'password_new' => string '' (length=0)
  public 'password_check' => string '' (length=0)
  public 'reply_notif' => string '0' (length=1)
    */

	private $isValid = true;
	private $request = null;
	private $log = null;

	public function __construct($request, $log) {
		$this->request = $request;
		$this->log = $log;

        $this->rules = $this->defaultRules;
	}

	public function isValid() {
		return $this->isValid;
	}

	public function validate($rules = null, $merge = false) {

        if ($rules != null) {
            if ($merge == false) {
                $this->rules = $rules;
            } else {
                $this->rules = array_merge($this->rules, $rules);
            }
        }

		foreach ($this->rules as $rule) {

			$field = $this->request->get($rule["field"]);

			if (!$this->validateRequired($rule, $field) || (empty($field) && empty($rule["required"]))) {
				continue;
			}

			switch ($rule['type']) {
				case 'string':
					$this->validateString($rule, $field);
					break;
				case 'email':
					$this->validateEmail($rule, $field);
					break;
                case 'alphanumeric':
                    $this->validataAlphanumeric($rule, $field);
                    break;
                case "number":
                    $this->validateNumber($rule, $field);
			}
		}

		return $this->isValid();
	}

	public static function validateCaptcha() {
		global $config;
		global $_DEBUG;

		if ($_DEBUG == false) {

			if (!isset($_POST["g-recaptcha-response"])) {
				return false;
			}

			$secretKey = $config["googleCaptchaKey"];
			$responseKey = $_POST["g-recaptcha-response"];
			$api = "https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$responseKey";
			$response = json_decode(file_get_contents($api));
	
			if(!$response->success) {
				return false;
			}
		}

		return true;
	}

	public function validateRequired($rule, $field) {
		if (((isset($rule["required"]) && $rule["required"] == true) || $rule["type"] == "required") && empty($field)) {
			$this->isValid = false;
			$this->log->error($rule["pretty"] . " je požadovaný údaj");
			return false;
		}

		return true;
	}

	public function validataAlphanumeric($rule, $field) {
		if (isset($field) && !preg_match("/^[a-zA-Z0-9_\\-]+$/", $field)) {
			$this->isValid = false;
			$this->log->error($rule["pretty"] . " obsahuje nepovolené znaky");

			return false;
		}

		return $this->validateString($rule, $field);
	}

    public function validateString($rule, $field) {
        
        //$status = (isset($rule["required"]) && $rule["required"] == true && ($field === null || empty($field)));

        if (isset($rule["minLength"]) && $rule["minLength"] != 0 && $rule["minLength"] > strlen($field)) {
            $this->isValid = false;
            $this->log->error($rule["pretty"] . " musí mít alespoň " . $rule["minLength"] . " nebo více znaků");
            return false;
        }

        if (isset($rule["maxLength"]) && $rule["maxLength"] != 0 && $rule["maxLength"] < strlen($field)) {
            $this->isValid = false;
            $this->log->error($rule["pretty"] . " nesmí být delší než " . $rule["maxLength"] . " znaků");
            return false;
        }

        return true;
    }

    public function validateNumber($rule, $field) {
        if (!empty($field) && !is_numeric($field) && !is_int($field)) {
            $this->isValid = false;
            $this->log->error($rule["pretty"] . " není platné číslo");
            
            return false;
        } else if (isset($rule["minLength"]) && $rule["minLength"] != 0 && !empty($field) && $rule["minLength"] > ($field)) {
            $this->isValid = false;
            $this->log->error($rule["pretty"] . " nesmí být menší než " . $rule["minLength"]);
            
            return false;
        } else if (isset($rule["maxLength"]) && $rule["maxLength"] != 0 && $rule["maxLength"] < ($field)) {
            $this->isValid = false;
            $this->log->error($rule["pretty"] . " nesmí být větší než " . $rule["maxLength"]);
            return false;
        }

        return true;
    }

	public function validateEmail($rule, $field) {
		if (!empty($field) && filter_var($field, FILTER_VALIDATE_EMAIL) === false) {
			$this->isValid = false;
			$this->log->error($rule["pretty"] . " není platná");
			return false;
		}

		return $this->validateString($rule, $field);
	}

	public function validateMatch($field1, $field2, $name) {
		if ((!empty($field1) && empty($field2)) || empty($field1) && !empty($field2) || (!empty($field1) && !empty($field2) && strcmp($field1, $field2) != 0)) {
			$this->isValid = false;
			$this->log->error($name . " se neshodují");
			return false;
		}
		return true;
	}
}