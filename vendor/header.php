<?php 
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

require_once $config->getWorkingDirectory() . 'vendor/autoload.php';

use KarmaDev\Panel\SQL\ClientData;

use KarmaDev\Panel\Utilities as Utils;

use KarmaDev\Panel\Client\User;
use KarmaDev\Panel\Client\UserLess;

$theme_class = 'light-mode';
if (isset($_COOKIE['halfmoon_preferredMode'])) {
    $current_theme = $_COOKIE['halfmoon_preferredMode'];

    if ($current_theme == 'dark-mode') {
        $theme_class = 'dark-mode';
    }
}

$tl = 'KarmaPanel';
if (ClientData::isAuthenticated()) {
    $cl = unserialize(Utils::get('client'));

    $tl = 'KarmaPanel - ' . $cl->getName();
    $view = 'user';
    if (isset($_COOKIE['view'])) {
        $view = $_COOKIE['view'];
        if ($view == 'settings') {
            $view = 'edit';
        }
    }
    
    $cl = unserialize(Utils::get('client'));
    $nm = $cl->getName();

    $clientConnection = new ClientData();
    $patreon_client = $clientConnection->loadPatreon($cl->getEmail());

    if ($patreon_client == null || empty($patreon_client) || gettype($patreon_client) != 'array') {
        $name = '<li class="nav-item dropdown with-arrow">
                    <a class="nav-link" data-toggle="dropdown" id="nav-link-dropdown-toggle">
                        <i class="fa fa-user" aria-hidden="true"></i>
                        <i class="fa fa-angle-down ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                    </a>
                    
                    <div class="dropdown-menu dropdown-menu-left" aria-labelledby="nav-link-dropdown-toggle">
                        <a href="'. $config->getHomePath() .'profile/?view=settings" class="dropdown-item">Settings</a>
                        <a href="'. $config->getHomePath() .'profile/?view='. $view .'" class="dropdown-item">Profile</a>
                        <a href="'. $config->getHomePath() .'api/auth/login.php?close=yes" class="dropdown-item">Log Out</a>
                        <a href="'. $config->getHomePath() .'profile/patreon" class="dropdown-item"><span class="badge badge-secondary">Sync patreon</span></a>
                    </div>
                </li>';
    } else {
        $name = '<li class="nav-item dropdown with-arrow">
                    <a class="nav-link" data-toggle="dropdown" id="nav-link-dropdown-toggle">
                        <i class="fa fa-user" aria-hidden="true"></i>
                        <i class="fa fa-angle-down ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                    </a>
                    
                    <div class="dropdown-menu dropdown-menu-left" aria-labelledby="nav-link-dropdown-toggle">
                        <a href="'. $config->getHomePath() .'profile/?view=settings" class="dropdown-item">Settings</a>
                        <a href="'. $config->getHomePath() .'profile/?view='. $view .'" class="dropdown-item">Profile</a>
                        <a href="'. $config->getHomePath() .'api/auth/login.php?close=yes" class="dropdown-item">Log Out</a>
                    </div>
                </li>';
    }
} else {
    $name = '<li class="nav-item dropdown with-arrow">
                <a class="nav-link" data-toggle="dropdown" id="nav-link-dropdown-toggle">
                    Account
                    <i class="fa fa-angle-down ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                </a>
                
                <div class="dropdown-menu dropdown-menu-left" aria-labelledby="nav-link-dropdown-toggle">
                    <a href="'. $config->getHomePath() .'login/" class="dropdown-item">Login</a>
                    <a href="'. $config->getHomePath() .'register/" class="dropdown-item">Register</a>
                </div>
            </li>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/halfmoon@1.1.1/css/halfmoon-variables.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-borderless@5/borderless.css" />

    <link rel="icon" href="<?php echo $config->getHomePath(); ?>logo_no_bg.png" type="image/png">

    <script src="https://cdn.jsdelivr.net/npm/halfmoon@1.1.1/js/halfmoon.min.js"></script>
    <script src="https://use.fontawesome.com/98b5571414.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <script type="text/javascript">
        function requestTemporalAPIKey(cb) {
            data = new FormData()
            data.set('method', 'api');
            data.set('action', 'request');
            data.set('nosession', true);

            let request = new XMLHttpRequest();
            request.onreadystatechange = (e) => {
                if (request.readyState !== 4) {
                    return;
                }

                if (request.status == 200) {
                    let json = JSON.parse(request.responseText);
                    cb(json['message']);
                }
            }

            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

            request.open("POST", host + 'api/', true);
            request.send(data)
        }

        $(document).ready(function(){
            window.history.replaceState('','',window.location.href)
        });

        requestTemporalAPIKey(function(api_key) {
            var timezone_offset_minutes = new Date().getTimezoneOffset();
            timezone_offset_minutes = timezone_offset_minutes == 0 ? 0 : -timezone_offset_minutes;

            console.log('Telling the server about your timezone... ( ' + timezone_offset_minutes + ' )')
            data = new FormData()
            data.set('method', 'timezone');
            data.set('api', api_key);
            data.set('query', timezone_offset_minutes);

            let request = new XMLHttpRequest();
            request.onreadystatechange = (e) => {
                if (request.readyState !== 4) {
                    return;
                }

                if (request.status == 200) {
                    try {
                        let json = JSON.parse(request.responseText);

                        if (json['success']) {
                            console.info(json['message']);                       
                        } else {
                            console.error(json['message']);
                        }
                    } catch (error) {
                        console.info(request.responseText);
                        console.error(error);
                    }
                }
            }

            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

            request.open("POST", host + 'api/', true);
            request.send(data)
        })
    </script>

    <style>
        .notification {
            width: 50px;
            height: inherit;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .notification::after {
            min-width: 20px;
            height: 20px;
            content: attr(data-count);
            background-color: #ed657d;
            font-family: monospace;
            font-weight: bolt;
            font-size: 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            position: absolute;
            top: 5px;
            right: 5px;
            transition: .3s;
            opacity: 0;
            transform: scale(.5);
            will-change: opacity, transform;
        }

        .notification.show-count::after {
            opacity: 1;
            transform: scale(1);
        }

        .notification::before {
            content: "\f0f3";
            font-family: "FontAwesome";
            display: block;
        }

        .notification.notify::before {
            animation: bell 1s ease-out;
            transform-origin: center top;
        }

        @keyframes bell {
            0% {transform: rotate(35deg);}
            12.5% {transform: rotate(-30deg);}
            25% {transform: rotate(25deg);}
            37.5% {transform: rotate(-20deg);}
            50% {transform: rotate(15deg);}
            62.5% {transform: rotate(-10deg)}
            75% {transform: rotate(5deg)}
            100% {transform: rotate(0);}  
        }
    </style>

    <script type="text/javascript">
        function toggleMode() {
            let cookie = halfmoon.getPreferredMode();

            switch (cookie) {
                case 'dark-mode':
                    document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-sun"></i>';
                    break;
                case 'light-mode':
                    document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-moon"></i>';
                    break;
                case 'not-set':
                default:
                    Swal.fire({
                        'title': 'Color scheme',
                        'text': 'This site has dark mode and light mode. You can choose whenever you want the scheme you want, but as this is your first time here, we will ask you',
                        'icon': 'info',
                        'showDenyButton': true,
                        'denyButtonText': 'Dark',
                        'confirmButtonText': 'Light',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-moon"></i>';
                        } else {
                            document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-sun"></i>';
                        }
                    });
                    break;
            }

            halfmoon.toggleDarkMode();
        }
    </script>
</head>
<body class="<?php echo $theme_class ?>" data-set-preferred-mode-onload="true">
    <div class="custom-control custom-switch">
        <div class="with-navbar with-sidebar with-navbar-fixed-bottom">
            <nav class="navbar">
                <!-- Navbar text -->
                <span class="navbar-text text-monospace"><?php echo $name; ?></span> <!-- text-monospace = font-family shifted to monospace -->
                <!-- Navbar nav -->
                <ul class="navbar-nav d-none d-md-flex"> <!-- d-none = display: none, d-md-flex = display: flex on medium screens and up (width > 768px) -->
                    <li class="nav-item">
                        <a href="<?php echo $config->getHomePath(); ?>" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item dropdown with-arrow">
                        <a class="nav-link" data-toggle="dropdown" id="nav-link-dropdown-toggle">
                            LockLogin 
                            <i class="fa fa-angle-down ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                        </a>
                        
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="nav-link-dropdown-toggle">
                            <a href="<?php echo $config->getHomePath() ?>locklogin" class="dropdown-item">Panel</a>
                            <a href="https://backup.karmadev.es/locklogin/wiki" target="_blank" class="dropdown-item">Wiki</a>
                            <a href="#" class="dropdown-item">
                            Backups
                            ( <strong class="badge badge-danger badge-pill float-center">Alpha</strong> )<!-- float-right = float: right -->
                            </a>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-content">
                            <a href="<?php echo $config->getHomePath() ?>locklogin/products" class="btn btn-block" role="button">
                                See all products
                                <i class="fa fa-angle-right ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                            </a>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown with-arrow">
                        <a class="nav-link" data-toggle="dropdown" id="nav-link-dropdown-toggle">
                            Panel 
                            <i class="fa fa-angle-down ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                        </a>
                        
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="nav-link-dropdown-toggle">
                            <a href="https://discord.com/invite/jRFfsdxnJR" target="_blank" class="nav-link">Support</a>
                            <a href="https://github.com/KarmaConfigs/" target="_blank" class="nav-link">GitHub</a>
                            <a href="https://www.patreon.com/karmaconfigs" target="_blank" class="nav-link">Patreon</a>
                        </div>
                    </li>

                    <style>
                        .notification-card {
                            margin: 0px 0px 0px 0px !important;
                            padding: 0px 0px 0px 0px !important;
                        }

                        .notification-title {
                            font-size: 14px;
                            margin-top: 5%;
                        }

                        .notification-content {
                            font-size: 12px;
                        }
                    </style>
                    <?php
                        if (ClientData::isAuthenticated()) {
                            $non_read = $cl->getUnread();
                            $read = $cl->getRead();

                            if (count($non_read) >= 1) {
                                ?>
                                <li class="nav-item dropdown with-arrow">
                                    <!--<a href="#" class="nav-link" id="n-show"><i class="fa fa-bell" aria-hidden="true"></i></a>-->
                                    <a class="nav-link notification" data-toggle="dropdown" id="notification"></a>
                                    <div class="dropdown-menu dropdown-menu-right" id='notification_container' aria-labelledby="nav-link-dropdown-toggle">
                                        <?php
                                            foreach($non_read as $nId => $data) {
                                                ?>
                                                    <div class="dropdown-divider"></div>
                                                    <div class="card notification-card" id="notification_<?php echo $nId ?>">
                                                        <h2 class="card-title text-center notification-title">
                                                            <?php echo $data['title']; ?> - <?php echo date("d/m/y H:i:s", $data['date']); ?>
                                                        </h2>
                                                        <p class="text-muted text-center notification-content">
                                                            <?php echo $data['info']; ?>
                                                        </p>
                                                    </div>
                                                <?php
                                            }
                                        ?>
                                    
                                        <div class="dropdown-divider"></div>
                                        <a href="<?php echo $config->getHomePath() . 'profile/notifications'; ?>" class="btn btn-link" role="button">
                                            View all notifications
                                        </a>

                                    </div>
                                </li>
                                <?php
                            } else {
                                ?>
                                <li class="nav-item dropdown with-arrow">
                                    <!--<a href="#" class="nav-link" id="n-show"><i class="fa fa-bell" aria-hidden="true"></i></a>-->
                                    <a class="nav-link notification" data-toggle="dropdown" id="notification"></a>
                                    <div class="dropdown-menu dropdown-menu-right" id='notification_container' aria-labelledby="nav-link-dropdown-toggle">
                                        <a href="<?php echo $config->getHomePath() . 'profile/notifications'; ?>" class="btn btn-link" role="button">
                                            View all notifications
                                        </a>
                                    </div>
                                </li>
                                <?php
                            }

                            ?>
                                <script>
                                    const $bell = document.getElementById('notification');

                                    $bell.setAttribute('data-count', <?php echo count($non_read) ?>);
                                    if ($bell.getAttribute('data-count') > 0) {
                                        $bell.classList.add('show-count');
                                    }
                                    $bell.classList.add('notify');

                                    $bell.addEventListener("animationend", function(event){
                                        $bell.classList.remove('notify');
                                    });

                                    const not_container = document.getElementById('notification_container');

                                    setInterval(function() {
                                        requestTemporalAPIKey(function(api_key) {
                                            data = new FormData()
                                            data.set('method', 'notification');
                                            data.set('action', 'fetch');
                                            data.set('notification', 'unread');
                                            data.set('api', api_key);

                                            let request = new XMLHttpRequest();
                                            request.onreadystatechange = (e) => {
                                                if (request.readyState !== 4) {
                                                    return;
                                                }

                                                if (request.status == 200) {
                                                    console.info(request.responseText);
                                                    let json = JSON.parse(request.responseText);

                                                    if (json['success']) {
                                                        let notifications = Object.keys(json['notifications']);

                                                        if (notifications.length > 0) {
                                                            notifications.forEach(key => {
                                                                let info = json['notifications'][key];
                                                                var element = document.getElementById('notification_' + key);

                                                                if (element == null) {
                                                                    var new_notification = document.createElement('div');
                                                                    new_notification.classList.add('card');
                                                                    new_notification.classList.add('notification-card');
                                                                    new_notification.setAttribute('id', 'notification_' + key);

                                                                    var notification_title = document.createElement('h2');
                                                                    notification_title.classList.add('card-title');
                                                                    notification_title.classList.add('text-center');
                                                                    notification_title.classList.add('notification-title');

                                                                    date = new Date(info['date'] * 1000);
                                                                    var dateString = date.toLocaleDateString(undefined, {
                                                                        year: "2-digit",
                                                                        month: "2-digit",
                                                                        day: "2-digit",
                                                                        hour: "2-digit",
                                                                        minute: "2-digit",
                                                                        second: "2-digit"
                                                                    });

                                                                    notification_title.innerHTML = info['title'] + ' - ' + date.getDate() + "/" + (date.getMonth() + 1) + "/" + date.getFullYear() + " " + date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds();
                                                                    
                                                                    
                                                                    var notification_content = document.createElement('p');
                                                                    notification_content.classList.add('text-muted');
                                                                    notification_content.classList.add('text-center');
                                                                    notification_content.classList.add('notification-content');
                                                                    notification_content.innerHTML = info['info'];

                                                                    new_notification.appendChild(notification_title);
                                                                    new_notification.appendChild(notification_content);

                                                                    if (not_container.firstChild) {
                                                                        not_container.insertBefore(new_notification, not_container.firstChild);
                                                                    } else {
                                                                        not_container.appendChild(new_notification);
                                                                    }

                                                                    var count = $bell.getAttribute('data-count')
                                                                    $bell.setAttribute('data-count', parseInt(count) + 1);
                                                                    if ($bell.getAttribute('data-count') > 0) {
                                                                        $bell.classList.add('show-count');
                                                                    }
                                                                    $bell.classList.add('notify');

                                                                    $bell.addEventListener("animationend", function(event){
                                                                        $bell.classList.remove('notify');
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    }
                                                }
                                            }

                                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                            request.open("POST", host + 'api/', true);
                                            request.send(data)
                                        })
                                    }, 2500);

                                    function read(notification) {
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
                                                        console.info(json['message']);
                                                    } else {
                                                        console.warn('Failed to read notification: ' + json['message']);
                                                    }
                                                }
                                            }

                                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                            request.open("POST", host + 'api/', true);
                                            request.send(data)
                                        })
                                    }

                                    var active = false;

                                    $bell.addEventListener("click", function(event) {
                                        $bell.setAttribute('data-count', 0);
                                        $bell.classList.remove('show-count');
                                        $bell.classList.remove('notify');

                                        if (not_container.children) {
                                            let allChild = not_container.children;
                                            for(i=0; i < allChild.length ; i++) {
                                                var tmp = allChild[i];

                                                var id = tmp.getAttribute('id');
                                                if (id != null && id.startsWith('notification_')) {
                                                    var id = id.replace('notification_', '');

                                                    if (!active) {
                                                        read(id);
                                                    }

                                                    if (active) {
                                                        tmp.remove();
                                                    }
                                                }
                                            }
                                        }

                                        active = !active;
                                    });
                                </script>
                            <?php
                        }
                    ?>
                </ul>
                <a href="#" class="nav-link" id="d-switch" onclick="toggleMode()"></a>
                
                <!-- Navbar form (inline form) -->
                <?php 
                    if (!isset($nm)) {
                        ?>
                            <form class="form-inline d-none d-md-flex ml-auto" action="<?php echo $config->getHomePath(); ?>api/auth/login.php" method="POST">
                                <input type="email" class="form-control" placeholder="Email address" name="fast-login" required="required" id="token-email">
                                <button class="btn btn-primary" type="submit">Fast sign in</button>
                            </form>
                        <?php
                    } else {
                        ?>
                            <form class="form-inline d-none d-md-flex ml-auto" action="<?php echo $config->getHomePath(); ?>?post=new-post" method="POST">
                                <button class="btn btn-link" type="submit">Create post</button>
                            </form>
                        <?php 
                    }
                ?>
                
                <!-- Navbar content (with the dropdown menu) -->
                <div class="navbar-content d-md-none ml-auto"> <!-- d-md-none = display: none on medium screens and up (width > 768px), ml-auto = margin-left: auto -->
                    <a href="<?php echo $config->getHomePath(); ?>" class="nav-link">Home</a>
                    <li class="nav-item dropdown with-arrow">
                        <a class="nav-link" data-toggle="dropdown" id="nav-link-dropdown-toggle">
                            LockLogin 
                            <i class="fa fa-angle-down ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                        </a>
                        
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="nav-link-dropdown-toggle">
                            <a href="<?php echo $config->getHomePath() ?>locklogin" class="dropdown-item">Panel</a>
                            <a href="https://github.com/KarmaConfigs/LockLoginReborn/wiki" target="_blank" class="dropdown-item">Wiki</a>
                            <a href="#" class="dropdown-item">
                                Backups
                            <strong class="badge badge-danger badge-pill float-right">Closed alpha</strong> <!-- float-right = float: right -->
                            </a>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-content">
                            <a href="<?php echo $config->getHomePath() ?>locklogin/products" class="btn btn-block" role="button">
                                See all products
                                <i class="fa fa-angle-right ml-5" aria-hidden="true"></i> <!-- ml-5= margin-left: 0.5rem (5px) -->
                            </a>
                            </div>
                        </div>
                    </li>
                    <div class="dropdown with-arrow">
                    <button class="btn" data-toggle="dropdown" type="button" id="navbar-dropdown-toggle-btn-1">
                        <i class="fa fa-angle-down" aria-hidden="true"></i>
                    </button>
                    <a href="#" class="nav-link" id="d-switch" onclick="toggleMode()"></a>
                    <div class="dropdown-menu dropdown-menu-right w-200" aria-labelledby="navbar-dropdown-toggle-btn-1"> <!-- w-200 = width: 20rem (200px) -->
                        <a href="https://discord.com/invite/jRFfsdxnJR" target="_blank" class="nav-link">Support</a>
                        <a href="https://github.com/KarmaConfigs/" target="_blank" class="nav-link">GitHub</a>
                        <a href="https://www.patreon.com/karmaconfigs" target="_blank" class="nav-link">Patreon</a>
                        <div class="dropdown-divider"></div>
                            <div class="dropdown-content">
                                <form action="<?php echo $config->getHomePath(); ?>login/" method="POST">
                                    <div class="form-group">
                                        <input type="text" class="form-control" placeholder="Email address" required="required" id="token-email">
                                    </div>
                                    <button class="btn btn-primary btn-block" type="submit">Fast sign in</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        <script type="text/javascript">
            let cookie = halfmoon.getPreferredMode();

            switch (cookie) {
                case 'dark-mode':
                    document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-sun"></i>';
                    break;
                case 'light-mode':
                    document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-moon"></i>';
                    break;
                case 'not-set':
                default:
                    Swal.fire({
                        'title': 'Color scheme',
                        'text': 'This site has dark mode and light mode. You can choose whenever you want the scheme you want, but as this is your first time here, we will ask you',
                        'icon': 'info',
                        'showDenyButton': true,
                        'denyButtonText': 'Dark',
                        'confirmButtonText': 'Light',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            halfmoon.createCookie("halfmoon_preferredMode", 'light-mode', 999999);
                            document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-sun"></i>';
                        } else {
                            halfmoon.createCookie("halfmoon_preferredMode", 'dark-mode', 999999);
                            document.getElementById('d-switch').innerHTML = '<i class="fa-solid fa-moon"></i>'; 
                        }

                        window.location.reload(true);
                    });
                    break;
            }
        </script>

        <div class="container my-5">
            <strong class="badge badge-success badge-pill float-center">KarmaPanel open beta</strong>
        </div>
    </div>

    <?php
        $file = getcwd();
        $buttonText = ($file == $config->getWorkingDirectory() . 'public' ? 'Dimsiss' : 'Take me home');

        $message = Utils::get('alert_message', null);
        if ($message != null) {
            $type = Utils::get('alert_type', false);
            $header = Utils::get('alert_header', '');
        }

        Utils::set('alert_message', null);
        Utils::set('alert_type', null);
        Utils::set('alert_header', null);

        if ($message != null) {
            $message = str_replace('%h', '', $message);

            ?>
            <script type="text/javascript">
                Swal.fire({
                    'title': '<?php echo str_replace("'", "\'", $header); ?>',
                    'text': '<?php echo str_replace("'", "\'", $message); ?>',
                    'icon': '<?php echo str_replace("'", "\'", $type); ?>',
                    'showCancelButton': true,
                    'showDenyButton': true,
                    'cancelButtonText': 'Take me back',
                    'denyButtonText': 'Ok',
                    'confirmButtonText': '<?php echo str_replace("'", "\'", $buttonText); ?>',
                }).then((result) => {
                    if (result.isConfirmed) {
                        <?php 
                            if ($file != $config->getWorkingDirectory() . 'public') {
                                ?>
                                    document.location.href = <?php echo $config->getHomePath(); ?>;
                                <?php
                            }
                        ?>
                    } else {
                        if (!result.isDenied) {
                            window.history.back();
                        }
                    }
                })
            </script>
            <?php
        }
    ?>
</body>
</html>