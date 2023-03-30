<?php 

namespace KarmaDev\Panel\Validator;

class TextValidator {

    public static function validEmail(string $email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validName(string $name) {
        $value = preg_match("/^\w{3,16}$/", $name);
        if (is_bool($value)) {
            return false;
        }
        
        return $value == 1;
    }

    public static function validPassword(string $password) {
        $value = preg_match("/^[^;*?]{7,}$/", $password);
        if (is_bool($value)) {
            return false;
        }

        return $value == 1;
    }
}