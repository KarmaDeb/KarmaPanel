<?php 
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/autoload.php';

use KarmaDev\Panel\Client\Mailer;

use KarmaDev\Panel\SQL\ClientData;

use KarmaDev\Panel\Utilities as Utils;

use KarmaDev\Panel\Validator\TextValidator as Validator;

header('Content-Type: application/json');

$response = [];

$data = Utils::build(null, "email", "username", "old", "password", "nosession", "stay");
$email = $data['email'];
$username = $data['username'];
$old = $data['old'];
$password = $data['password'];
$stay = boolval($data['stay']);
if ($stay == null) {
    $stay = boolval(false);
}

if ($email != null || $username != null) {
    $connection = new ClientData();
    $userInfo = $connection->loadProfileData(Utils::findValid($email, $username));

    if (sizeof($userInfo) >= 10) {
        if ($old == null) {
            $token = Utils::generate();
            $encoded = Utils::encode($token);

            Utils::setGlobal($userInfo['email'] . '_forgot', $encoded);

            $mail = new Mailer($userInfo['email']);
            $host = $_SERVER['SERVER_NAME'];
            $serialized = base64_encode(serialize([
                'email' => $userInfo['email'],
                'token' => $token
            ]));
            $serialized = urlencode($serialized); //validate for URL

            $activation = "https://{$host}{$config->getHomePath()}change/?token={$serialized}";

            $response['success'] = true;
            $response['message'] = "A recovery email has been sent to " . Utils::findValid($email, $username);

            if (!$mail->send('Recover your account', 'account_recovery', array(
                'username' => $userInfo['name'],
                'recovery_url' => $activation
            ))) {
                $response['success'] = false;
                $response['message'] = "Failed to send email";
            }
        } else {
            if (Validator::validPassword($password)) {
                $oldData = base64_decode($old);

                try {
                    $oldData = unserialize($oldData);
                } catch (Error $ignored) {}

                if (gettype($oldData) == 'array') {
                    $storedToken = Utils::getGlobal($oldData['email'] . '_forgot');

                    if (Utils::auth($oldData['token'], base64_decode($storedToken))) {
                        Utils::setGlobal($oldData['email'] . '_forgot', null); //invalidate token if any

                        $connection->ch4ng3acc3ske7($userInfo['id'], $password);

                        $response['success'] = true;
                        $response['message'] = "Password updated successfully";

                        if (!$stay) {
                            Utils::set('alert_message', 'Your password has been updated.');
                            Utils::set('alert_type', 'success');
                            Utils::set('alert_header', 'Done');
                        }
                    } else {
                        $response['success'] = false;
                        $response['message'] = "Invalid recovery token provided";

                        if (!$stay) {
                            Utils::set('alert_message', 'There was a problem while changing your account. Recovery token is invalid.');
                            Utils::set('alert_type', 'error');
                            Utils::set('alert_header', 'Failed');
                        }
                    }
                } else {
                    $storedPassword = $userInfo['password'];

                    if (Utils::auth($old, $storedPassword)) {
                        Utils::setGlobal($userInfo['email'] . '_forgot', null); //invalidate token if any

                        $connection->ch4ng3acc3ske7($userInfo['id'], $password);

                        $response['success'] = true;
                        $response['message'] = "Password updated successfully";

                        if (!$stay) {
                            Utils::set('alert_message', 'Your password has been updated.');
                            Utils::set('alert_type', 'success');
                            Utils::set('alert_header', 'Done');
                        }
                    } else {
                        $response['success'] = false;
                        $response['message'] = "Incorrect old password";

                        if (!$stay) {
                            Utils::set('alert_message', 'There was a problem while changing your account. Passwords does not match.');
                            Utils::set('alert_type', 'error');
                            Utils::set('alert_header', 'Failed');
                        }
                    }
                }
            } else {
                $response['success'] = false;
                $response['message'] = "Invalid password. It must be at least 7 characters and cannot contain ';', '*', '?'";

                if (!$stay) {
                    Utils::set('alert_message', 'There was a problem while updating your account password. The password must be at least 7 characters and can not contain ;, * and ?.');
                    Utils::set('alert_type', 'error');
                    Utils::set('alert_header', 'Failed');
                }
            }
        }
    } else {
        $response['success'] = false;
        $response['message'] = "No matching user found for email/username: " . Utils::findValid($email, $username);
        $response['size'] = sizeof($userInfo);
    }
} else {
    $response['success'] = false;
    $response['message'] = "Missing fields. Required email or username";
}

$response['email'] = $email;
$response['username'] = $username;
$response['address'] = Utils::getUserIpAddr();

/*
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
}*/

echo json_encode($response, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);