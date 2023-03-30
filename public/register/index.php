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

    <title>KarmaDev Panel | Register</title>

    <style>
        form.register-form {
            position: absolute;

            top: 25%;
            right: 50%;
            
            transform: translate(50%, 25%);
        }
    </style>
</head>
<body data-set-preferred-mode-onload="true">
    <form action="<?php echo $config->getHomePath(); ?>api/auth/register.php?stay=0" method="post" class="register-form form-inline w-400 mw-full">
        <div class="form-group">
            <label class="required w-100" for="email">Email</label>
            <input type="email" class="form-control" placeholder="Email address" name="email" required="required">
        </div>
        <div class="form-group">
            <label class="required w-100" for="username">Username</label>
            <input type="text" class="form-control" placeholder="User name" name="username" required="required">
        </div>
        <div class="form-group">
            <label class="required w-100" for="password">Password</label>
            <input type="password" class="form-control" placeholder="Password" name="password" required="required">
        </div>
        <div class="form-group mb-0">
            <div class="custom-control">
                <div class="custom-checkbox">
                    <input type="checkbox" id="remember-me" name="remember" value="true">
                    <label for="remember-me">Remember me</label>
                </div>
            </div>
            <input type="submit" class="btn btn-primary ml-auto" value="Sign up">
        </div>
    </form>
</body>
</html>