<?php

namespace KarmaDev\Panel\Codes;

use KarmaDev\Panel\Codes\ResultCodes as Code;

class PostStatus {

    public static function post_pending_approval() {
        return 0;
    }

    public static function post_active() {
        return 1;
    }

    public static function post_private() {
        return 2;
    }

    public static function post_removed() {
        return 3;
    }

    public static function parse(int $code) {
        switch ($code) {
            case 0:
                return "<strong class='badge badge-secondary badge-pill float-right0>Pending approval</strong>";
            case 1:
                return "<strong class='badge badge-success badge-pill float-right0>Public</strong>";
            case 2:
                return "<strong class='badge badge-danger badge-pill float-right0>Private</strong>";
            case 3:
                return "<strong class='badge badge-primary badge-pill float-right0>Removed by administrator</strong>";
            default:
                return Code::parse($code);
        }
    }

    public static function text(int $code) {
        switch ($code) {
            case 0:
                return "Pending of approval";
            case 1:
                return "Public";
            case 2:
                return "Private";
            case 3:
                return "Removed by administrator";
            default:
                return Code::parse($code);
        }
    }

    public static function getPermission(int $code) {
        switch ($code) {
            case 0:
                return 'manage_post';
            case 1:
                return 'approve_post';
            case 2:
                return 'disable_post';
            case 3:
                return 'remove_other_post';
            default:
                return 'manage_post';
        }
    }
}