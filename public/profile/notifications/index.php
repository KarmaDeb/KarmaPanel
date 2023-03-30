<?php
include '/var/www/panel/vendor/header.php';

use KarmaDev\Panel\SQL\ClientData;

use KarmaDev\Panel\Utilities as Utils;
use KarmaDev\Panel\Configuration;

$config = new Configuration();

$client = null;
if (ClientData::isAuthenticated()) {
    ClientData::performAction('reading notifications');

    $client = unserialize(Utils::get('client'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>KarmaDev Panel | Notifications</title>
</head>
<body data-set-preferred-mode-onload="true">
    <?php
    if ($client != null) {
        $non_read = $client->getUnread();
        $read = $client->getRead();
        if (count($non_read) >= 1) {
            echo '<div class="mw-full">';

            foreach($non_read as $nId => $data) {
                echo '
                    <div class="card">
                        <h2 class="card-title">
                            '. $data['title'] .' - '. date("d/m/y H:i:s", $data['date']) .'
                        </h2>
                        <p class="text-muted">
                            '. $data['info'] .'
                        </p>
                        <div class="text-right">
                            <a onclick="readNotification(\''. str_replace("'", "\'", $nId) .'\')" class="btn">Ok!</a>
                        </div>
                    </div>';
            }

            echo '<hr />';

            foreach ($read as $nId => $data) {
                echo '
                    <div class="card">
                        <h2 class="card-title">
                            '. $data['title'] .' - '. date("d/m/y H:i:s", $data['date']) .'
                        </h2>
                        <p class="text-muted">
                            '. $data['info'] .'
                        </p>
                        <div class="text-right">
                            <a onclick="unreadNotification(\''. str_replace("'", "\'", $nId) .'\')" class="btn">Unread</a>
                            <a onclick="removeNotification(\''. str_replace("'", "\'", $nId) .'\')" class="btn">Remove</a>
                        </div>
                    </div>';
            }
        } else {
            ?>
            <div id="no_notification_info" class="alert alert-secondary" role="alert">
                <h4 class="alert-heading">Congratulations!</h4>
                You do not have any notification to read
            </div>

            <script type="text/javascript">
                setTimeout(() => {
                    document.getElementById('no_notification_info').remove();
                }, 5000);
            </script>
            <?php

            foreach ($read as $nId => $data) {
                echo '
                    <div class="card">
                        <h2 class="card-title">
                            '. $data['title'] .' - '. date("d/m/y H:i:s", $data['date']) .'
                        </h2>
                        <p class="text-muted">
                            '. $data['info'] .'
                        </p>
                        <div class="text-right">
                            <a onclick="unreadNotification(\''. str_replace("'", "\'", $nId) .'\')" class="btn">Unread</a>
                            <a onclick="removeNotification(\''. str_replace("'", "\'", $nId) .'\')" class="btn">Remove</a>
                        </div>
                    </div>';
            }
        }
    } else {
        ?>
        <script type="text/javascript">
            Swal.fire({
                'title': 'Oh no!',
                'text': 'You must be logged in in order to view notifications',
                'icon': 'warning',
                'showCancelButton': true,
                'cancelButtonText': 'Take me back',
                'confirmButtonText': 'Log me in',
            }).then((result) => {
                if (result.isConfirmed) {
                    <?php 
                        if ($file != $config->getWorkingDirectory() . 'public') {
                            ?>
                                document.location.href = '<?php echo $config->getHomePath() . 'login'; ?>';
                            <?php
                        }
                    ?>
                } else {
                    window.history.back();
                }
            })
        </script>
        <?php
    }
    ?>

    <script type="text/javascript">
        function readNotification(notification) {
            requestTemporalAPIKey(function(api_key) {
                data = new FormData()
                data.set('method', 'notification');
                data.set('action', 'read');
                data.set('api', api_key);
                data.set('notification', notification);

                let request = new XMLHttpRequest();
                request.onreadystatechange = (e) => {
                    if (request.readyState !== 4) {
                        return;
                    }

                    if (request.status == 200) {
                        var json = JSON.parse(request.responseText);
                                                                    
                        if (json['success']) {
                            window.location.reload(true);
                            console.info(json['message']);
                        } else {
                            console.warn('Failed to read notification: ' + json['message']);
                        }
                    }
                }

                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                request.open("POST", host + 'api/', true);
                request.send(data)
            });
        }

        function unreadNotification(notification) {
            requestTemporalAPIKey(function(api_key) {
                data = new FormData()
                data.set('method', 'notification');
                data.set('action', 'unread');
                data.set('api', api_key);
                data.set('notification', notification);

                let request = new XMLHttpRequest();
                request.onreadystatechange = (e) => {
                    if (request.readyState !== 4) {
                        return;
                    }

                    if (request.status == 200) {
                        var json = JSON.parse(request.responseText);
                                                                    
                        if (json['success']) {
                            window.location.reload(true);
                            console.info(json['message']);
                        } else {
                            console.warn('Failed to read notification: ' + json['message']);
                        }
                    }
                }

                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                request.open("POST", host + 'api/', true);
                request.send(data)
            });
        }

        function removeNotification(notification) {
            requestTemporalAPIKey(function(api_key) {
                data = new FormData()
                data.set('method', 'notification');
                data.set('action', 'delete');
                data.set('api', api_key)
                data.set('notification', notification);

                let request = new XMLHttpRequest();
                request.onreadystatechange = (e) => {
                    if (request.readyState !== 4) {
                        return;
                    }

                    if (request.status == 200) {
                        var json = JSON.parse(request.responseText);
                                                                    
                        if (json['success']) {
                            window.location.reload(true);
                            console.info(json['message']);
                        } else {
                            console.warn('Failed to read notification: ' + json['message']);
                        }
                    }
                }

                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                request.open("POST", host + 'api/', true);
                request.send(data)
            });
        }
    </script>
</body>
</html>