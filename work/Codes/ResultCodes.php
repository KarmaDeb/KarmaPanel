<?php 

namespace KarmaDev\Panel\Codes;

class ResultCodes {

    public static function success() {
        return 0;
    }

    public static function err_register_unknown() {
        return 101;
    }

    public static function err_register_exists() {
        return 102;
    }

    public static function err_register_sql() {
        return 103;
    }

    public static function err_login_unknown() {
        return 201;
    }

    public static function err_login_exists() {
        return 202;
    }

    public static function err_login_sql() {
        return 203;
    }

    public static function err_login_invalid() {
        return 204;
    }

    public static function err_login_unverified() {
        return 205;
    }

    public static function err_verify_invalid() {
        return 301;
    }

    public static function err_verify_already() {
        return 302;
    }

    public static function err_verify_exists() {
        return 303;
    }

    public static function err_verify_sql() {
        return 304;
    }

    public static function err_verify_unknown() {
        return 305;
    }

    public static function err_remember_invalid() {
        return 401;
    }

    public static function err_remember_exists() {
        return 402;
    }

    public static function err_remember_sql() {
        return 403;
    }

    public static function err_remember_unknown() {
        return 404;
    }

    public static function err_post_auth() {
        return 501;
    }

    public static function err_post_sql() {
        return 502;
    }

    public static function err_post_unknown() {
        return 503;
    }

    public static function err_post_unknown_topic() {
        return 503.1;
    }

    public static function err_patreon_store() {
        return 601;
    }

    public static function err_patreon_exists() {
        return 602;
    }

    public static function err_patreon_sql() {
        return 603;
    }

    public static function err_patreon_unknown() {
        return 604;
    }

    public static function parse(int $code) {
        switch ($code) {
            case 0:
                return "Success";
            case 101:
                return "An unknown error occurred";
            case 102:
                return "This account already exists!";
            case 103:
                return "A SQL error occurred";
            case 201:
                return "An unknown error occurred";
            case 202:
                return "This account does not exist";
            case 203:
                return "A SQL error occurred";
            case 204:
                return "Incorrect password!";
            case 205:
                return "This account is not verified!";
            case 301:
                return "Invalid verification token";
            case 302:
                return "This account is already verified!";
            case 303:
                return "This account does not exist!";
            case 304:
                return "A SQL error occurred";
            case 305:
                return "An unknown error occurred";
            case 401:
                return "Invalid remember token provided or expired";
            case 402:
                return "No member with that email or username exists";
            case 403:
                return "A SQL error occurred";
            case 404:
                return "An unknown error occurred";
            case 501:
                return "The user must be authenticated to create a post";
            case 502:
                return "A SQL error occurred";
            case 503:
                return "An unknown error occurred";
            case 503.1:
                return "An unknown topic has been provided";
            case 601:
                return "An error occurred storing your client token";
            case 602:
                return "No user with that email address or name exists";
            case 603:
                return "A SQL error occurred";
            case 604:
                return "An unknown error occurred";
            default:
                return "Unknown";
        }
    }
}