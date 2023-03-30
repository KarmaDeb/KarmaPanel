<?php 
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/autoload.php';

use KarmaDev\Panel\Client\Mailer;

use KarmaDev\Panel\SQL\ClientData;

use KarmaDev\Panel\Utilities as Utils;

use KarmaDev\Panel\Validator\TextValidator as Validator;

use KarmaDev\Panel\Codes\ResultCodes as Result;

header('Content-Type: application/json');

$response = [];

$data = Utils::build(null, "email", "username", "password", "nosession", "remember", "stay", "resend");
$email = $data['email'];
$username = $data['username'];
$password = $data['password'];
$resend = $data['resend'];
$result = -1;
$token = null;
$nosession = boolval($data['nosession']);
if ($nosession != null) {
    $_POST['nosession'] = $nosession;
}
$remember = $data['remember'];
if ($remember == null) {
    $remember = boolval($remember);
}
$stay = boolval($data['stay']);
if ($stay == null) {
    $stay = boolval(false);
}

if ($email != null && $username != null && $password != null) {
    if (Validator::validEmail($email)) {
        if (Validator::validName($username)) {
            if (Validator::validPassword($password)) {
                $connection = new ClientData();
                $result = $connection->register($email, $username, $password, $remember);

                $response['success'] = $result == 0;
                $response['message'] = ($result == 0 ? "Registration completed. Validate your account" : Result::parse($result));
                if ($result == 0 || $resend) {
                    if ($nosession) {
                        $token = Utils::get('tmp_token');
                        Utils::set('tmp_token', null);
                    } else {
                        $token = Utils::getGlobal($email);

                        $mail = new Mailer($email);
                        $host = $_SERVER['SERVER_NAME'];
                        $activation = "https://{$host}{$config->getHomePath()}api/auth/login.php/?stay=0&email={$email}&token={$token}";

                        if (!$mail->send('Confirm your account', 'account_confirmation', array(
                            'username' => $username,
                            'activation_url' => $activation
                        ))) {
                            $response['error'] = "Failed to send email";
                        }
                    }

                    if (!$stay) {
                        Utils::set('alert_message', 'A confirmation email has been sent to confirm your account.');
                        Utils::set('alert_type', 'success');
                        Utils::set('alert_header', 'Success');
                    }
                } else {
                    if (!$stay) {
                        Utils::set('alert_message', 'There was a problem while registering your account. This code may help you: ' . $result . '.');
                        Utils::set('alert_type', 'error');
                        Utils::set('alert_header', 'Failed');
                    }
                }
            } else {
                $response['success'] = false;
                $response['message'] = "Invalid password. It must be at least 7 characters and cannot contain ';', '*', '?'";

                if (!$stay) {
                    Utils::set('alert_message', 'There was a problem while registering your account. The password must be at least 7 characters and can not contain ;, * and ?.');
                    Utils::set('alert_type', 'error');
                    Utils::set('alert_header', 'Failed');
                }
            }
        } else {
            $response['success'] = false;
            $response['message'] = "Invalid name. It must be at least 3 characters long and max 16 characters. Can only contain numbers, letters and underscores";
            
            if (!$stay) {
                Utils::set('alert_message', 'There was a problem while registering your account. The username must be at least 3 characters long and max 16 characters. And can only contain numbers, letters and underscores.');
                Utils::set('alert_type', 'error');
                Utils::set('alert_header', 'Failed');
            }
        }
    } else {
        $response['success'] = false;
        $response['message'] = "Invalid email address. Provide a valid email address";

        if (!$stay) {
            Utils::set('alert_message', 'There was a problem while registering your account. The email address is not a valid address.');
            Utils::set('alert_type', 'error');
            Utils::set('alert_header', 'Failed');
        }
    }
} else {
    $response['success'] = false;
    $response['message'] = "Missing fields. Required email, username and password";
}

$response['code'] = $result;
$response['email'] = $email;
$response['username'] = $username;
$response['token'] = $token;
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