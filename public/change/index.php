<?php
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

use KarmaDev\Panel\Utilities as Utils;

include $config->getWorkingDirectory() . 'vendor/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>KarmaDev Panel | Change password</title>

    <style>
        form.login-form {
            position: absolute;

            top: 25%;
            right: 50%;
            
            transform: translate(50%, 25%);
        }
    </style>
</head>
<body data-set-preferred-mode-onload="true">
    <form onsubmit="return false" class="login-form form-inline w-400 mw-full">
        <div class="form-group">
            <label class="required w-100" for="paramNoE">Name/Email</label>
            <?php
                $connected = unserialize(Utils::get('client'));
                if ($connected == null) {
                    if (isset($_GET['token'])) {
                        $data = urldecode($_GET['token']);
                        $data = unserialize(base64_decode($data));

                        ?>
                            <input type="text" class="form-control" placeholder="Email address/Username" id="email" name="email" required="required" value="<?php echo $data['email']; ?>" disabled>
                        <?php
                    } else {
                        ?>
                            <input type="text" class="form-control" placeholder="Email address/Username" id="email" name="email" required="required">
                        <?php
                    }
                } else {
                    ?>
                        <input type="text" class="form-control" placeholder="Email address/Username" id="email" name="email" required="required" value="<?php echo $connected->getEmail(); ?>" disabled>
                    <?php
                }
            ?>
        </div>
        <div class="form-group">
            <label class="required w-100" for="password">Old password</label>
            <?php 
                if (isset($_GET['token'])) {
                    $data = urldecode($_GET['token']);
                    ?>
                        <input type="password" class="form-control" placeholder="Password" id="old" name="old" required="required" value="<?php echo $data; ?>" disabled>
                    <?php
                } else {
                    ?>
                        <input type="password" class="form-control" placeholder="Password" id="old" name="old" required="required">
                    <?php
                }
            ?>
        </div>
        <div class="form-group">
            <label class="required w-100" for="password">Password</label>
            <input type="password" class="form-control" placeholder="Password" id="password" name="password" required="required">
        </div>
        <div class="form-group mb-0">
            <input type="submit" onclick="submitChange()" class="btn btn-primary ml-auto" value="Change password">
        </div>
    </form>

    <script type="text/javascript">
        function submitChange() {
            console.info('Submiting');

            var paramNoE = $('#email').val();
            data = new FormData()
            data.set('email', paramNoE);
            data.set('username', paramNoE);
            data.set('old', $('#old').val())
            data.set('password', $('#password').val())
            data.set('stay', true)

            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

            let request = new XMLHttpRequest();
            request.onreadystatechange = (e) => {
                if (request.readyState !== 4) {
                    return;
                }

                if (request.status == 200) {
                    console.info(request.responseText);
                    var json = JSON.parse(request.responseText);
                                            
                    if (json['success']) {
                        Swal.fire({
                                'title': 'Account recovery',
                                'text': json['message'],
                                'icon': 'success',
                                'showCancelButton': false,
                                'confirmButtonText': 'Ok',
                        }).then((result) => {
                            document.location.href = host;
                        });
                    } else {
                        Swal.fire({
                            'title': 'Error',
                            'text': json['message'],
                            'icon': 'error',
                            'showCancelButton': false,
                            'confirmButtonText': 'Ok',
                        });
                    }
                }
            }

            request.open("POST", host + 'api/auth/forgot.php', false);
            request.send(data)
        }
    </script>
</body>
</html>