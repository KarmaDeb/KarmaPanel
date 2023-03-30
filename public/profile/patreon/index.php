<?php
include '/var/www/panel/vendor/header.php';

use Patreon\OAuth;
use Patreon\API;

use KarmaDev\Panel\Configuration;
use KarmaDev\Panel\Utilities as Utils;

use KarmaDev\Panel\SQL\ClientData;

if (ClientData::isAuthenticated()) {
    $user = unserialize(Utils::get('client'));
    $userConnection = new ClientData();

    $patreon_client = $userConnection->loadPatreon($user->getEmail());
    if ($patreon_client == null || empty($patreon_client) || gettype($patreon_client) != 'array') {
        $config = new Configuration();

        $host = $_SERVER['SERVER_NAME'];

        $redirect_uri = "https://{$host}{$config->getHomePath()}profile/patreon";
        $href = "https://www.patreon.com/oauth2/authorize?response_type=code&client_id={$config->getPatreonId()}&redirect_uri=" . urlencode($redirect_uri);

        $state = array();
        $state['final_page'] = "https://{$host}{$config->getHomePath()}";

        $state_parameters = "&state=" . urlencode(base64_encode(json_encode($state)));
        $href .= $state_parameters;

        $scope_parameters = "&score=identity%20identity" . urlencode($user->getEmail());
        $href .= $scope_parameters;
    } else{
        $href = null;
    }
}

ClientData::performAction('doing stonks ( syncing with patreon )')
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>KarmaDev Panel | Patreon</title>
</head>
<body data-set-preferred-mode-onload="true">
    <?php
    if (ClientData::isAuthenticated()) {
        ?>
        <div class="card">
            <h2 class="card-title">
                Patreon synchronization
            </h2>
            <p>
                Please <a href="<?php echo $href; ?>">click here</a> to synchronize your account with Patreon
            </p>
        </div>
        <?php

        if (isset($_GET['code']) && $_GET['code'] != '') {
            $oauth_client = new OAuth($config->getPatreonId(), $config->getPatreonSecret());

            $tokens = $oauth_client->get_tokens($_GET['code'], $redirect_uri);

            if (isset($tokens['access_token']) && isset($tokens['refresh_token'])) {
                $access_token = $tokens['access_token'];
                $refresh_token = $tokens['refresh_token'];
    
                //Before storing it, we want to know if the user actually exists
                $client = new API($access_token);
    
                $client = $client->fetch_user();
                if ($client != null && !empty($client) && gettype($client) == 'array') {
                    $user = unserialize(Utils::get('client'));
                    $userConnection = new ClientData();
    
                    $result = $userConnection->assignPatreon($user->getEmail(), $access_token, $refresh_token);
                    if ($result == 0) {
                        ?>
                            <script type="text/javascript">
                                Swal.fire({
                                    'title': 'Synchronized!',
                                    'text': 'Your account is now synchronized with patreon',
                                    'icon': 'success',
                                    'confirmButtonText': 'Ok',
                                }).then((result) => {
                                    document.location.href = <?php echo $config->getHomePath(); ?>;
                                })
                            </script>
                        <?php
                    } else {
                        ?>
                            <script type="text/javascript">
                                Swal.fire({
                                    'title': 'Yes but... no?',
                                    'text': 'An error occurred while storing your patreon access token. This code may help you: ' + <?php echo $result; ?>,
                                    'icon': 'warning',
                                    'confirmButtonText': 'Ok',
                                });
                            </script>
                        <?php
                    }
                    //Success
                } else {
                    //Failed
                    ?>
                    <script type="text/javascript">
                        Swal.fire({
                            'title': 'Oh no!',
                            'text': 'Something went wrong while trying to synchronize your Patreon account',
                            'icon': 'error',
                            'confirmButtonText': 'Ok',
                        });
                    </script>
                    <?php
                }
            } else {
                ?>
                    <script type="text/javascript">
                        Swal.fire({
                            'title': 'Oh no!',
                            'text': 'The code does not match any existing code. Try refreshing the page!',
                            'icon': 'error',
                            'confirmButtonText': 'Ok',
                        });
                    </script>
                <?php
            }
        }
        if ($href == null) {
            ?>
            <script type="text/javascript">
                Swal.fire({
                    'title': 'Oh no!',
                    'text': 'You are already synchronized with patreon',
                    'icon': 'error',
                    'confirmButtonText': 'Ok',
                }).then((result) => {
                    document.location.href = <?php echo $config->getHomePath(); ?>;
                });
            </script>
            <?php
        }
    } else {
        ?>
        <div class="card">
            <h2 class="card-title">
                Patreon synchronization
            </h2>
            <p>
                You must be <a href="<?php echo $config->getHomePath(); ?>login">logged in</a> in order to sync with Patreon
            </p>
        </div>
        <?php
    }
    ?>
</body>
</html>