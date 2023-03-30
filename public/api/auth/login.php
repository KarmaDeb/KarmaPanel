<?php
if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/autoload.php';

use KarmaDev\Panel\SQL\ClientData;
use KarmaDev\Panel\Utilities as Utils;
use KarmaDev\Panel\Validator\TextValidator as Validator;
use KarmaDev\Panel\Codes\ResultCodes as Result;

header('Content-Type: application/json');

$response = [];

$data = Utils::build(null, "email", "username", "paramNoE", "password", "token", "nosession", "remember", "fast-login", "close", "stay", "source_address");
$email = $data['email'];
$username = $data['username'];
$paramNoE = $data['paramNoE'];
$password = $data['password'];
$token = $data['token'];
$fastLogin = $data['fast-login'];
$remember = $data['remember'];

$nosession = boolval($data['nosession']);
if ($nosession != null) {
    $_POST['noSession'] = $nosession;
}
$remember = $data['remember'];
if ($remember == null) {
    $remember = boolval($remember);
}
$stay = boolval($data['stay']);
if ($stay == null) {
    $stay = false;
}
$close = boolval($data['close']);
if ($close == null) {
    $close = false;
}

$response['stay'] = $stay;
if ($close) {
    if (ClientData::isAuthenticated()) {
        $response['success'] = true;
        $response['message'] = "Successfully logged out";

        ClientData::close();
    } else {
        $response['success'] = false;
        $response['message'] = "You are not authenticated!";
    }
} else {
    $connection = new ClientData();
    if ($token != null) {
        if ($username != null || $email != null || $paramNoE != null) {
            $result = $connection->verify(Utils::findValid($username, $email, $paramNoE), $token);

            $response['success'] = $result == 0;
            $response['message'] = ($result == 0 ? "Successfully validated the account" : Result::parse($result));

            if (!$stay) {
                if ($result == 0) {
                    Utils::set('alert_message', 'Your account has been successfully activated.');
                    Utils::set('alert_type', 'success');
                    Utils::set('alert_header', 'Success');
                } else {
                    Utils::set('alert_message', 'There was a problem while activating your account. This code may help you: ' . $result);
                    Utils::set('alert_type', 'error');
                    Utils::set('alert_header', 'Failed');
                }
            }
        } else {
            $response['success'] = false;
            $response['message'] = "Validation requires at least the username or the email";
        }
    } else {
        if ($fastLogin == null) {
            if (ClientData::isAuthenticated()) {
                $response['success'] = true;
                $response['message'] = "You were already authenticated!";
            } else {
                if ($password != null) {
                    if ($username != null || $email != null || $paramNoE != null) {
                        $result = $connection->authenticate(Utils::findValid($username, $email, $paramNoE), $password, $remember);
                        $response['success'] = $result == 0;
                        $response['message'] = ($result == 0 ? "Authenticated successfully" : Result::parse($result));

                        if (!$stay) {
                            if ($result == 0) {
                                Utils::set('alert_message', 'You have been successfully logged in.');
                                Utils::set('alert_type', 'success');
                                Utils::set('alert_header', 'Success');
                            } else {
                                Utils::set('alert_message', 'There was a problem while logging you in. This code may help you: ' . $result . '. Please make sure email and password are correct.');
                                Utils::set('alert_type', 'error');
                                Utils::set('alert_header', 'Failed');
                            }
                        }
                    } else {
                        $response['success'] = false;
                        $response['message'] = "Can not authenticate without username or email";
                    }
                } else {
                    //TODO: Instead of authenticating, if not the same address send an email to confirm the address
                    $result = $connection->validateSession(Utils::findValid($username, $email, $paramNoE));

                    $response['success'] = $result == 0;
                    $response['message'] = ($result == 0 ? "Authenticate successfully" : Result::parse($result));

                    if (!$stay) {
                        if ($result == 0) {
                            Utils::set('alert_message', 'You have been successfully logged in.');
                            Utils::set('alert_type', 'success');
                            Utils::set('alert_header', 'Success');
                        } else {
                            Utils::set('alert_message', 'There was a problem while logging you in. This code may help you: ' . $result . '. You can try to log in using the password and checking the remember me checkbox.');
                            Utils::set('alert_type', 'error');
                            Utils::set('alert_header', 'Failed');
                        }
                    }
                }
            }
        } else {
            if (ClientData::isAuthenticated()) {
                $response['success'] = true;
                $response['message'] = "You were already authenticated!";
            } else {
                $response['fast-login'] = true;
                $email = $fastLogin;
                $username = $fastLogin;
                $password = $fastLogin;

                $result = $connection->validateRememberToken($fastLogin);

                $response['success'] = $result == 0;
                $response['message'] = ($result == 0 ? "Authenticated successfully" : Result::parse($result));

                if (!$stay) {
                    if ($result == 0) {
                        Utils::set('alert_message', 'You have been successfully logged in.');
                        Utils::set('alert_type', 'success');
                        Utils::set('alert_header', 'Success');
                    } else {
                        Utils::set('alert_message', 'There was a problem while logging you in. This code may help you: ' . $result . '. You can try to log in using the password and checking the remember me checkbox.');
                        Utils::set('alert_type', 'error');
                        Utils::set('alert_header', 'Failed');
                    }
                }
            }
        }
    }
}

$response['email'] = $email;
$response['username'] = $username;
$response['token'] = $token != null;
$response['password'] = $password != null;
$response['address'] = Utils::getUserIpAddr();

if (!$stay) {
    $previous = "javascript:history.go(-1)";
    if(isset($_SERVER['HTTP_REFERER'])) {
        $previous = $_SERVER['HTTP_REFERER'];
    }

    $host = 'https://' . $_SERVER['SERVER_NAME'] . $config->getHomePath();
    if (strpos($previous, $host)) {
        $response['redirect'] = $previous;
        header("Location: " . $previous);
    } else {
        $response['redirect'] = $host;
        header("Location: " . $host);
    }
}

echo json_encode($response, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);