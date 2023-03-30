<?php
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>KarmaDev Panel | Login</title>

    <style>
        form.login-form {
            position: absolute;

            top: 25%;
            right: 50%;
            
            transform: translate(50%, 25%);
        }

        a:hover {
            cursor: pointer;
        }
    </style>
</head>
<body data-set-preferred-mode-onload="true">
    <form action="<?php echo $config->getHomePath(); ?>api/auth/login.php" method="post" class="login-form form-inline w-400 mw-full">
        <div class="form-group">
            <label class="required w-100" for="paramNoE">Name/Email</label>
            <input type="text" class="form-control" placeholder="Email address/Username" id="paramNoE" name="paramNoE" required="required">
        </div>
        <div class="form-group">
            <label class="required w-100" for="password">Password</label>
            <input type="password" class="form-control" placeholder="Password" name="password" required="required">
        </div>
        <div class="form-group">
            <a onclick="forgotPassword()">Forgot password?</a>
        </div>
        <div class="form-group mb-0">
            <div class="custom-control">
                <div class="custom-checkbox">
                    <input type="checkbox" id="remember-me" name="remember" value="true">
                    <label for="remember-me">Remember me</label>
                </div>

            </div>
            <input type="submit" class="btn btn-primary ml-auto" value="Sign in">
        </div>
    </form>

    <script type="text/javascript">
        function forgotPassword() {
            var paramNoE = $('#paramNoE').val();
            data = new FormData()
            data.set('email', paramNoE);
            data.set('username', paramNoE);
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