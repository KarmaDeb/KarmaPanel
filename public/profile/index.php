<?php
include '/var/www/panel/vendor/header.php';

use KarmaDev\Panel\Configuration;

use KarmaDev\Panel\SQL\ClientData;
use KarmaDev\Panel\SQL\PostData;

use KarmaDev\Panel\Codes\PostStatus as Post;

use KarmaDev\Panel\Utilities as Utils;

$config = new Configuration();
if (isset($_COOKIE['view'])) {
    $view = $_COOKIE['view'];
    if ($view == 'settings') {
        if (isset($_GET['view'])) {
            if ($_GET['view'] != 'settings') {
                $view = 'edit';
            }
        } else {
            $view = 'edit';
        }
    }
} else {
    $view = 'edit';
}

if(isset($_FILES['file']) && ClientData::isAuthenticated()) {
    $cl = unserialize(Utils::get('client'));
    $connection = new ClientData();

    $name = $_FILES['file']['name'];
    $target_dir = "{$config->getWorkingDirectory()}vendor/upload/";
    $target_file = $target_dir . basename($_FILES["file"]["name"]);
  
    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
  
    $extensions_arr = array("jpg","jpeg","png","gif");
  
    if(in_array($imageFileType, $extensions_arr)) {
        if(move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $name)) {
            $target_file = $target_dir . $name;
            $resized = Utils::resizeImage($target_file, 120, 120);

            $connection->updateProfilePhoto($cl->getIdentifier(), $resized);
        }
    }
}

$profile = [];
$profileName = 'Guest';
$currentName = 'Guest';
if (ClientData::isAuthenticated()) {
    $cl = unserialize(Utils::get('client'));
    $currentName = $cl->getName();
}

$date = date("d/m/y", time());
$last_online = date("d/m/y H:i:s", time());
$last_doing = 'navigating through the panel';
$description = "You must be <a href='{$config->getHomePath()}login/'>logged in</a> to edit your profile";
$patreon = array();
$user_settings = [
    'visibility' => 'public',
    'broadcast_status' => true,
    'broadcast_registration' => true
];
$extraBadge = '';
$profilePhoto = Utils::default_photo();
$connection = new ClientData();

$userId = -1;
$ownerId = -1;

$profile = null;
if (isset($_GET['id'])) {
    $profile = $connection->loadProfileData(htmlspecialchars($_GET['id']));
} else {
    if (ClientData::isAuthenticated()) {
        $cl = unserialize(Utils::get('client'));
        $profile = $connection->loadProfileData($cl->getIdentifier());
    }
}

if ($profile != null) {
    $userId = $profile['id'];
    $profilePhoto = $profile['photo'];

    $c_date = $profile['registered'];

    $newTimezone = new DateTime($c_date, new DateTimeZone('UTC'));
    $newTimezone->setTimezone(new DateTimeZone(Utils::get('timezone')));

    $c_date = $newTimezone->format('U');
    
    $date = date("d/m/y", $c_date);;

    $c_date = Utils::getLastOnline($userId);

    $newTimezone = new DateTime($c_date, new DateTimeZone('UTC'));
    $newTimezone->setTimezone(new DateTimeZone(Utils::get('timezone')));

    $c_date = $newTimezone->format('U');

    $last_online = date("d/m/y H:i:s", $c_date);
    $last_doing = Utils::getLastDoing($userId);
    $profileName = $profile['name'];
    $description = $profile['description'];
    $user_settings = json_decode($profile['setting'], true);
    $patreon = $profile['patreon'];

    $extraBadge = '';

    if ($patreon != null && !empty($patreon) && gettype($patreon) == 'array' && isset($patreon['included'])) {
        $patreonData = $patreon['included'];

        foreach ($patreonData as $includedData) {
            if (isset($includedData['attributes']) && isset($includedData['id'])) {
                $id = $includedData['id'];
                if ($id == 'd36adb45-1842-4fed-88a3-1ce77843bb99') {
                    $attributes = $includedData['attributes'];
                    
                    if ($attributes['patron_status'] == 'declined_patron') {
                        $extraBadge = '<span class="badge badge-danger">Ex Patreon</span>  ';
                    } else {
                        $extraBadge = '<span class="badge badge-secondary">Patreon</span>  ';
                    }
                }
            }
        }
    }
}

ClientData::performAction('viewing profile of <a href="'. $config->getHomePath() .'profile/?id='. $userId .'">' . $profileName . '</a>');

if (ClientData::isAuthenticated()) {
    $ownerCL = unserialize(Utils::get('client'));

    $ownerId = $ownerCL->getIdentifier();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>KarmaDev Panel | <?php echo $profileName ?></title>

    <style>
        div.file-names {
            display: none;
        }
    </style>
</head>
<body data-set-preferred-mode-onload="true">
    <script type="text/javascript">
        function goPost(postId) {
            var urlParams = new URLSearchParams(window.location.search);
            urlParams.forEach((value, key) => {
                urlParams.delete(key);
            });
            urlParams.append('post', postId);

            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';
            window.location = host + '?' + urlParams.toString();
        }
    </script>

    <?php 
        if ($userId > 0) {
            ?>
            <script>
                var link = document.createElement('link');
                link.type = 'image/png';
                link.rel = 'icon';
                link.href = '<?php echo $profilePhoto; ?>';
                document.getElementsByTagName('head')[0].appendChild(link);
            </script>
            <?php
        }
    ?>
    

    <?php
        if ($view != 'settings' || $userId != $ownerId) {
            $showOther = true;

            if (ClientData::isAuthenticated()) {
                $owner = true;
                $cl = unserialize(Utils::get('client'));

                if (isset($_GET['id'])) {
                    $t_id = $_GET['id'];
                    $owner = $cl->getIdentifier() == $t_id;
                }

                if ($owner) {
                    $showOther = false;
                    ?>
                    <div class="card">
                        <img src="<?php echo $profilePhoto; ?>" class="img-fluid rounded-top" alt="profile-picture">
                        <div class="w-400 mw-full" style="margin-bottom: 25px">
                            <form method="POST" action="" enctype="multipart/form-data" id="avatarForm" class="form-inline-sm">
                                <div class="custom-control">
                                    <div class="custom-file">
                                        <input type="file" name="file" id="if-8-file-input" accept=".jpg, .jpeg, .png, .gif" required>
                                        <label for="if-8-file-input">Change avatar</label>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $extraBadge . $profileName; ?> - <ins style="text-decoration: none; font-size: 20px"><?php echo $date; ?></ins></h5>
                            <p>Last seen online: <?php echo $last_online; ?>, <?php echo $last_doing ?></p>
                            <hr/>
                            <form onSubmit="submitProfile(); return false;" class="form-inline w-400 mw-full">
                                <div class="form-group">
                                    <textarea type="textarea" maxlength="255" style="width: 255px; height: 200px; resize: none" class="form-control" placeholder="<?php $description; ?>" id="description" name="description"><?php echo $description; ?></textarea>
                                    <p style="display: none;" id="preview" class="card-text"><?php echo str_replace("\n", "<br>", str_replace('%username%', $currentName, $description)); ?></p>
                                </div>
                                <div class="form-group mb-0">
                                    <button class="btn btn-primary" id="toggleView" type="button">Toggle view</button>
                                    <input type="submit" class="btn btn-primary ml-auto" name="submit" value="Save profile">  
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php
                }
            }

            if ($showOther) {
                if (strtolower($user_settings['visibility']) == 'public' || $ownerId == $userId) {
                    ?>
                    <div class="card">
                        <?php
                        if (isset($cl)) {
                            $friend = $cl->getFriend($userId);

                            $isFollow = $friend['following'];
                            if ($friend['since'] != null) {
                                echo '<img src="' . $profilePhoto . '" onclick="openUnFriendMenu(\'' . $profileName . '\', \'' . $profilePhoto . '\', ' . $userId . ', ' . $isFollow . ')" class="img-fluid rounded-top" alt="profile-picture">';
                            } else {
                                echo '<img src="' . $profilePhoto . '" onclick="openFriendMenu(\'' . $profileName . '\', \'' . $profilePhoto . '\', ' . $userId . ', ' . $isFollow . ')" class="img-fluid rounded-top" alt="profile-picture">';
                            }
                        } else {
                            echo '<img src="' . $profilePhoto . '" class="img-fluid rounded-top" alt="profile-picture">';
                        }
                        ?>
                        <script type="text/javascript">
                            function openUnFriendMenu(name, photo, user, following) {
                                Swal.fire({
                                    toast: true,
                                    grow: true,
                                    imageUrl: photo,
                                    imageHeight: 120,
                                    imageWidth: 120,
                                    title: name,
                                    showDenyButton: true,
                                    showCancelButton: true,
                                    confirmButtonText: 'Remove friend',
                                    denyButtonText: (following ? 'Unfollow' : 'Follow'),
                                    confirmButtonColor: '#dc3741',
                                    denyButtonColor: (following ? '#dc3741' : '#94b55e')
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        requestTemporalAPIKey(function(api_key) {
                                            data = new FormData()
                                            data.set('method', 'account');
                                            data.set('action', 'status');
                                            data.set('api', api_key);
                                            data.set('content', 'unfriend');
                                            data.set('value', user);

                                            let request = new XMLHttpRequest();
                                            request.onreadystatechange = (e) => {
                                                if (request.readyState !== 4) {
                                                    return;
                                                }

                                                if (request.status == 200) {
                                                    var json = JSON.parse(request.responseText);

                                                    if (json['success']) {
                                                        Swal.fire({
                                                            icon: 'info',
                                                            title: 'A friend request has been sent to ' + name,
                                                            showConfirmButton: false,
                                                            timer: 1500
                                                        }).then(() => {
                                                            window.location.reload(true);
                                                        });
                                                    } else {
                                                        Swal.fire({
                                                            icon: 'error',
                                                            title: json['message'],
                                                            showConfirmButton: false,
                                                            timer: 1500
                                                        }).then(() => {
                                                            window.location.reload(true);
                                                        });
                                                    }
                                                }
                                            }
                                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                            request.open("POST", host + 'api/', false);
                                            request.send(data)
                                        });
                                    } else if (result.isDenied) {
                                        if (following) {
                                            requestTemporalAPIKey(function(api_key) {
                                                data = new FormData()
                                                data.set('method', 'account');
                                                data.set('action', 'status');
                                                data.set('api', api_key);
                                                data.set('content', 'unfollow');
                                                data.set('value', user);

                                                let request = new XMLHttpRequest();
                                                request.onreadystatechange = (e) => {
                                                    if (request.readyState !== 4) {
                                                        return;
                                                    }

                                                    if (request.status == 200) {
                                                        var json = JSON.parse(request.responseText);

                                                        if (json['success']) {
                                                            Swal.fire({
                                                                icon: 'info',
                                                                title: 'You are not longer following ' + name,
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            }).then(() => {
                                                                window.location.reload(true);
                                                            });
                                                        } else {
                                                            Swal.fire({
                                                                icon: 'error',
                                                                title: json['message'],
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            }).then(() => {
                                                                window.location.reload(true);
                                                            });
                                                        }
                                                    }
                                                }
                                                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                                request.open("POST", host + 'api/', false);
                                                request.send(data)
                                            });
                                        } else {
                                            requestTemporalAPIKey(function(api_key) {
                                                data = new FormData()
                                                data.set('method', 'account');
                                                data.set('action', 'status');
                                                data.set('api', api_key);
                                                data.set('content', 'follow');
                                                data.set('value', user);

                                                let request = new XMLHttpRequest();
                                                request.onreadystatechange = (e) => {
                                                    if (request.readyState !== 4) {
                                                        return;
                                                    }

                                                    if (request.status == 200) {
                                                        var json = JSON.parse(request.responseText);

                                                        if (json['success']) {
                                                            Swal.fire({
                                                                icon: 'info',
                                                                title: 'You are now following ' + name,
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            }).then(() => {
                                                                window.location.reload(true);
                                                            });
                                                        } else {
                                                            Swal.fire({
                                                                icon: 'error',
                                                                title: json['message'],
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            }).then(() => {
                                                                window.location.reload(true);
                                                            });
                                                        }
                                                    }
                                                }
                                                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                                request.open("POST", host + 'api/', false);
                                                request.send(data)
                                            });
                                        }
                                    }
                                });
                            }

                            function openFriendMenu(name, photo, user, following) {
                                Swal.fire({
                                    toast: true,
                                    grow: true,
                                    imageUrl: photo,
                                    imageHeight: 120,
                                    imageWidth: 120,
                                    title: name,
                                    showDenyButton: true,
                                    showCancelButton: true,
                                    confirmButtonText: 'Add friend',
                                    denyButtonText: (following ? 'Unfollow' : 'Follow'),
                                    confirmButtonColor: '#94b55e',
                                    denyButtonColor: (following ? '#dc3741' : '#94b55e')
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        requestTemporalAPIKey(function(api_key) {
                                            data = new FormData()
                                            data.set('method', 'account');
                                            data.set('action', 'status');
                                            data.set('api', api_key);
                                            data.set('content', 'friend');
                                            data.set('value', user);

                                            let request = new XMLHttpRequest();
                                            request.onreadystatechange = (e) => {
                                                if (request.readyState !== 4) {
                                                    return;
                                                }

                                                if (request.status == 200) {
                                                    try {
                                                        var json = JSON.parse(request.responseText);

                                                        if (json['success']) {
                                                            Swal.fire({
                                                                icon: 'info',
                                                                title: 'A friend request has been sent to ' + name,
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            }).then(() => {
                                                                window.location.reload(true);
                                                            });
                                                        } else {
                                                            Swal.fire({
                                                                icon: 'error',
                                                                title: json['message'],
                                                                showConfirmButton: false,
                                                                timer: 1500
                                                            }).then(() => {
                                                                window.location.reload(true);
                                                            });
                                                        }
                                                    } catch (error) {
                                                        console.info(request.responseText);
                                                    }
                                                }
                                            }
                                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                            request.open("POST", host + 'api/', false);
                                            request.send(data)
                                        });
                                    } else if (result.isDenied) {
                                        if (following) {
                                            requestTemporalAPIKey(function(api_key) {
                                                data = new FormData()
                                                data.set('method', 'account');
                                                data.set('action', 'status');
                                                data.set('api', api_key);
                                                data.set('content', 'unfollow');
                                                data.set('value', user);

                                                let request = new XMLHttpRequest();
                                                request.onreadystatechange = (e) => {
                                                    if (request.readyState !== 4) {
                                                        return;
                                                    }

                                                    if (request.status == 200) {
                                                        try {
                                                            var json = JSON.parse(request.responseText);

                                                            if (json['success']) {
                                                                Swal.fire({
                                                                    icon: 'info',
                                                                    title: 'You are not longer following ' + name,
                                                                    showConfirmButton: false,
                                                                    timer: 1500
                                                                }).then(() => {
                                                                    window.location.reload(true);
                                                                });
                                                            } else {
                                                                Swal.fire({
                                                                    icon: 'error',
                                                                    title: json['message'],
                                                                    showConfirmButton: false,
                                                                    timer: 1500
                                                                }).then(() => {
                                                                    window.location.reload(true);
                                                                });
                                                            }
                                                        } catch (error) {
                                                            console.info(request.responseText)
                                                        }
                                                    }
                                                }
                                                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                                request.open("POST", host + 'api/', false);
                                                request.send(data)
                                            });
                                        } else {
                                            requestTemporalAPIKey(function(api_key) {
                                                data = new FormData()
                                                data.set('method', 'account');
                                                data.set('action', 'status');
                                                data.set('api', api_key);
                                                data.set('content', 'follow');
                                                data.set('value', user);

                                                let request = new XMLHttpRequest();
                                                request.onreadystatechange = (e) => {
                                                    if (request.readyState !== 4) {
                                                        return;
                                                    }

                                                    if (request.status == 200) {
                                                        try {
                                                            var json = JSON.parse(request.responseText);

                                                            if (json['success']) {
                                                                Swal.fire({
                                                                    icon: 'info',
                                                                    title: 'You are now following ' + name,
                                                                    showConfirmButton: false,
                                                                    timer: 1500
                                                                }).then(() => {
                                                                    window.location.reload(true);
                                                                });
                                                            } else {
                                                                Swal.fire({
                                                                    icon: 'error',
                                                                    title: json['message'],
                                                                    showConfirmButton: false,
                                                                    timer: 1500
                                                                }).then(() => {
                                                                    window.location.reload(true);
                                                                });
                                                            }
                                                        } catch (error) {
                                                            console.info(request.responseText)
                                                        }
                                                    }
                                                }
                                                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                                request.open("POST", host + 'api/', false);
                                                request.send(data)
                                            });
                                        }
                                    }
                                });
                            }
                        </script>
                        <br />
                        <div class="card-body">
                            <?php
                                if ($user_settings['broadcast_registration']) {
                                    ?>
                                    <h5 class="card-title"><?php echo $extraBadge . $profileName; ?> - <ins style="text-decoration: none; font-size: 20px"><?php echo $date; ?></ins></h5>
                                    <?php
                                } else {
                                    ?>
                                    <h5 class="card-title"><?php echo $extraBadge . $profileName; ?></h5>
                                    <?php
                                }

                                if ($user_settings['broadcast_status']) {
                                    ?>
                                        <p>Last seen online: <?php echo $last_online; ?>, <?php echo $last_doing ?></p>
                                        <hr/>
                                    <?php
                                }
                            ?>
                                
                            <form onSubmit="return false;" class="form-inline w-400 mw-full">
                                <div class="form-group">
                                    <textarea style="display: none" type="textarea" maxlength="255" class="form-control" placeholder="<?php $description; ?>" id="description" name="description"><?php echo $description; ?></textarea>
                                    <p style="display: block;" id="preview" class="card-text"><?php echo str_replace("\n", "<br>", str_replace('%username%', $currentName, $description)); ?></p>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="card">
                        <img src="<?php echo $profilePhoto; ?>" class="img-fluid rounded-top" alt="profile-picture">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $profileName; ?></h5>
                                
                            <form onSubmit="return false;" class="form-inline w-400 mw-full">
                                <div class="form-group">
                                    <textarea style="display: none" type="textarea" maxlength="255" class="form-control" placeholder="This profile is private" id="description" name="description"><?php echo $description; ?></textarea>
                                    <p style="display: block;" id="preview" class="card-text">This profile is private</p>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php
                }
            }
        }
        
        if ($view == 'settings' && $userId == $ownerId) {
            echo "<div class='card'>";
            echo "<h2 class='card-title'>User settings</h2>";
            ?>
            <form action="..." method="..." class="w-400 mw-full">
                <div class="custom-control">
                    <div class="form-group">
                        <?php
                        $viewText = 'Public';
                        $publicSelected = '';
                        $privateSelected = '';

                        $settings = $connection->loadProfileData($cl->getIdentifier());
                        $settings = json_decode($settings['setting'], true);
                        if ($settings['visibility'] == 'public') {
                            $publicSelected = 'selected="selected"';
                        } else {
                            $privateSelected = 'selected="selected"';
                            $viewText = 'Private';
                        }
                        ?>

                        <label for="profile-visibility" id='view-text'>Profile visibility: <?php echo $viewText; ?></label>
                        <select onchange='changePublicView(this)' class="form-control" id="profile-visibility">
                            <option value="" selected="selected" disabled="disabled">Select how your profile will be visible</option>
                            <option value="public" <?php echo $publicSelected; ?>>Everyone</option>
                            <option value="private" <?php echo $privateSelected; ?>>Only me and staff members</option>
                            <option value="friends" disabled>Friends only [not implemented]</option>
                        </select>

                        <script type="text/javascript">
                            function changePublicView(obj) {
                                requestTemporalAPIKey(function(api_key) {
                                    data = new FormData()
                                    data.set('method', 'account');
                                    data.set('action', 'setting');
                                    data.set('api', api_key);
                                    data.set('content', 'visibility');
                                    data.set('value', '' + obj.value + '');

                                    let request = new XMLHttpRequest();
                                    request.onreadystatechange = (e) => {
                                        if (request.readyState !== 4) {
                                            return;
                                        }

                                        if (request.status == 200) {
                                            var json = JSON.parse(request.responseText);
                                                
                                            if (json['success']) {
                                                Swal.fire({
                                                    'title': 'Done',
                                                    'text': 'Your profile has been updated',
                                                    'icon': 'success',
                                                    'showCancelButton': false,
                                                    'confirmButtonText': 'Perfect!',
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

                                            var vText = document.getElementById('view-text');

                                            switch (obj.value) {
                                                case 'public':
                                                    vText.innerHTML = 'Profile visibility: Public';
                                                    break;
                                                case 'private':
                                                    vText.innerHTML = 'Profile visibility: Private';
                                                    break;
                                                default:
                                                    break;
                                            }
                                        }
                                    }

                                    var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                    request.open("POST", host + 'api/', false);
                                    request.send(data)
                                })
                                
                            }
                        </script>
                    </div>
                    <br><br><br>
                    <div class="custom-control">
                        <div class="custom-switch">
                            <?php 
                                $broadcast_status = '';

                                $settings = $connection->loadProfileData($cl->getIdentifier());
                                $settings = json_decode($settings['setting'], true);
                                if ($settings['broadcast_status']) {
                                    $broadcast_status = 'checked';
                                }
                            ?>

                            <input onclick='switchStatusBroadcast()' type="checkbox" id="show-online" value="" name="show-online" <?php echo $broadcast_status; ?>>
                            <label class="required" for="show-online">Show me in online users list</label>

                            <script type='text/javascript'>
                                function switchStatusBroadcast() {
                                    requestTemporalAPIKey(function(api_key) {
                                        data = new FormData()
                                        data.set('method', 'account');
                                        data.set('action', 'setting');
                                        data.set('api', api_key);
                                        data.set('content', 'broadcast_status');
                                        data.set('value', '' + document.querySelector('#show-online').checked + '');

                                        let request = new XMLHttpRequest();
                                        request.onreadystatechange = (e) => {
                                            if (request.readyState !== 4) {
                                                return;
                                            }

                                            if (request.status == 200) {
                                                var json = JSON.parse(request.responseText);
                                                
                                                if (json['success']) {
                                                    Swal.fire({
                                                        'title': 'Done',
                                                        'text': 'Your profile has been updated',
                                                        'icon': 'success',
                                                        'showCancelButton': false,
                                                        'confirmButtonText': 'Perfect!',
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

                                        var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                        request.open("POST", host + 'api/', false);
                                        request.send(data)
                                    });
                                }
                            </script>
                        </div>
                        <br>
                        <div class="custom-switch">
                            <?php 
                                $registration_status = '';

                                $settings = $connection->loadProfileData($cl->getIdentifier());
                                $settings = json_decode($settings['setting'], true);
                                if ($settings['broadcast_registration']) {
                                    $registration_status = 'checked';
                                }
                            ?>

                            <input onclick="switchRegistrationBroadcast()" type="checkbox" id="show-registration" value="" name="show-registration" <?php echo $registration_status; ?>>
                            <label class="required" for="show-registration">Registration date is public</label>

                            <script type='text/javascript'>
                                function switchRegistrationBroadcast() {
                                    requestTemporalAPIKey(function(api_key) {
                                        data = new FormData()
                                        data.set('method', 'account');
                                        data.set('action', 'setting');
                                        data.set('api', api_key);
                                        data.set('content', 'broadcast_registration');
                                        data.set('value', '' + document.querySelector('#show-registration').checked + '');

                                        let request = new XMLHttpRequest();
                                        request.onreadystatechange = (e) => {
                                            if (request.readyState !== 4) {
                                                return;
                                            }

                                            if (request.status == 200) {
                                                var json = JSON.parse(request.responseText);
                                                
                                                if (json['success']) {
                                                    Swal.fire({
                                                        'title': 'Done',
                                                        'text': 'Your profile has been updated',
                                                        'icon': 'success',
                                                        'showCancelButton': false,
                                                        'confirmButtonText': 'Perfect!',
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

                                        var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                        request.open("POST", host + 'api/', false);
                                        request.send(data)
                                    });
                                }
                            </script>
                        </div>
                        <br>
                        <?php 
                            $email_status = '';

                            $settings = $connection->loadProfileData($cl->getIdentifier());
                            $settings = json_decode($settings['setting'], true);
                            if ($settings['email_notifications']) {
                                $email_status = 'checked';
                            }
                        ?>

                        <div class="custom-switch">
                            <input onclick="switchEmailNotifications()" type="checkbox" id="email-notifications" value="" name="email-notifications" <?php echo $registration_status; ?>>
                            <label class="required" for="email-notifications">Email me for notifications</label>
                        </div>

                        <script type='text/javascript'>
                            function switchEmailNotifications() {
                                requestTemporalAPIKey(function(api_key) {
                                    data = new FormData()
                                    data.set('method', 'account');
                                    data.set('action', 'setting');
                                    data.set('api', api_key);
                                    data.set('content', 'email_notifications');
                                    data.set('value', '' + document.querySelector('#email-notifications').checked + '');

                                    let request = new XMLHttpRequest();
                                    request.onreadystatechange = (e) => {
                                        if (request.readyState !== 4) {
                                            return;
                                        }

                                        if (request.status == 200) {
                                            var json = JSON.parse(request.responseText);
                                                
                                            if (json['success']) {
                                                Swal.fire({
                                                    'title': 'Done',
                                                    'text': 'Your profile has been updated',
                                                    'icon': 'success',
                                                    'showCancelButton': false,
                                                    'confirmButtonText': 'Perfect!',
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

                                    var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                    request.open("POST", host + 'api/', false);
                                    request.send(data)
                                });
                            }
                        </script>

                        <br>
                        <?php
                            if (!$connection->hasAPIKey($cl->getIdentifier())) {
                                ?>
                                <div>
                                    <button onclick="requestAPIKey()" type="button" class="btn btn-primary" id="request-api-key" value="Submit" name="request-api-key">
                                    <label for="request-api-key">Request API key</label>

                                    <input type="text" id="api_key_value" style="display: none">
                                </div>

                                <script type='text/javascript'>
                                    function requestAPIKey() {
                                        data = new FormData()
                                        data.set('method', 'api');
                                        data.set('action', 'request');

                                        let request = new XMLHttpRequest();
                                        request.onreadystatechange = (e) => {
                                            if (request.readyState !== 4) {
                                                return;
                                            }

                                            if (request.status == 200) {
                                                var json = JSON.parse(request.responseText);
                                                    
                                                if (json['success']) {
                                                    navigator.clipboard.writeText(json['api_key']);

                                                    Swal.fire({
                                                        'title': 'Done',
                                                        'text': 'Your API key has been generated and coppied to clipboard',
                                                        'icon': 'success',
                                                        'showCancelButton': false,
                                                        'confirmButtonText': 'Perfect!',
                                                    }).then((result) => {
                                                        window.location.reload(true);
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

                                        var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                        request.open("POST", host + 'api/', false);
                                        request.send(data)
                                    }
                                </script>
                                <?php
                            } else {
                                ?>
                                <div>
                                    <button onclick="revokeAPIKey()" type="button" class="btn btn-primary" id="revoke-api-key" value="Submit" name="revoke-api-key">
                                    <label id="revoke_api_text" for="revoke-api-key">Revoke API key</label>

                                    <script type='text/javascript'>
                                        function revokeAPIKey() {
                                            data = new FormData()
                                            data.set('method', 'api');
                                            data.set('action', 'revoke');

                                            let request = new XMLHttpRequest();
                                            request.onreadystatechange = (e) => {
                                                if (request.readyState !== 4) {
                                                    return;
                                                }

                                                if (request.status == 200) {
                                                    var json = JSON.parse(request.responseText);
                                                        
                                                    if (json['success']) {
                                                        navigator.clipboard.writeText(json['api_key']);

                                                        Swal.fire({
                                                            'title': 'Done',
                                                            'text': 'Your API key has been revoked, you can generate a new one',
                                                            'icon': 'success',
                                                            'showCancelButton': false,
                                                            'confirmButtonText': 'Perfect!',
                                                        }).then((result) => {
                                                            window.location.reload(true);
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

                                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                            request.open("POST", host + 'api/', false);
                                            request.send(data)
                                        }
                                    </script>
                                </div>
                                <br /><br />
                                <div class="d-flex justify-content-start">
                                    <div>
                                        <input type="text" length="80" maxlength="80" id="api_key_text" class="form-control" placeholder="API key">
                                    </div>
                                    <div>
                                        <button onclick="testAPIkey()" type="button" class="btn btn-primary" id="test-api-key" value="Submit" name="test-api-key">
                                        <label id="test_api_text" for="test-api-key">Test API key</label>
                                    </div>
                                </div>

                                <script type='text/javascript'>
                                    function testAPIkey() {
                                        data = new FormData()
                                        data.set('method', 'api');
                                        data.set('action', 'test');
                                        data.set('content', document.getElementById('api_key_text').value)

                                        let request = new XMLHttpRequest();
                                        request.onreadystatechange = (e) => {
                                            if (request.readyState !== 4) {
                                                return;
                                            }

                                            if (request.status == 200) {
                                                var json = JSON.parse(request.responseText);
                                                    
                                                if (json['success']) {
                                                    Swal.fire({
                                                        'title': 'Done',
                                                        'text': 'Your API key is completely working',
                                                        'icon': 'success',
                                                        'showCancelButton': false,
                                                        'confirmButtonText': 'Perfect!',
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

                                        var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                        request.open("POST", host + 'api/', false);
                                        request.send(data)
                                    }
                                </script>
                                <?php
                            }
                        ?>
                    </div>
                </div>
            </form>
            <?php
            echo "<hr/>";
            echo "<h1>API requests</h1>";

            echo "<div class='api_request_displayer' style=\"overflow-y: auto; height: 500px;\">";
                $requests = $connection->fetchRequests($cl->getIdentifier());
                $requests = array_reverse($requests, true);

                foreach ($requests as $requestId => $requestData) {
                    echo "<div class='card'>";
                    if (isset($requestData['method'])) {
                        echo "<p class='text-left'>Method: " . $requestData['method'] . "</p>"; 
                    }
                    if (isset($requestData['action'])) {
                        echo "<p class='text-left'>Action: " . $requestData['action'] . "</p>"; 
                    }
                    echo "<p class='text-left'>Date: " . $requestData['modification'] . "</p>"; 
                    if (isset($requestData['data'])) {
                        echo "<textarea class='form-control api_request_data' readonly='readonly'>" . json_encode($requestData['data'], JSON_PRETTY_PRINT + JSON_UNESCAPED_LINE_TERMINATORS + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE) . "</textarea>";
                    }
                    echo "</div>";
                }
            echo "</div>";

            ?>
            <style>
                .api_request_displayer::-webkit-scrollbar {
                    display: none;
                }

                .api_request_data::-webkit-scrollbar {
                    display: none;
                }

                .api_request_data {
                    height: 200px;
                    resize: none;
                }
            </style>
            <?php

            echo "</div>";
        } else {
            if ($userId > 0) {
                $postConnection = new PostData();
                $posts = $postConnection->getUserPosts($userId);

                echo "<div class='d-flex'>";
                echo "<div class='flex-grow-1 card'>";
                echo "<h2 class='card-title'>Posts made by user</h2>";
                echo "<hr/>";

                if (strtolower($user_settings['visibility']) == 'public' || $ownerId == $userId) {
                    $posts = array_reverse($posts, true);
                    if (isset($_GET['page'])) {
                        $page = intval($_GET['page']);
                    } else {
                        $page = 1;
                    }
                    
                    $page_size = 5;
                    $total_records = count($posts);
                    $total_pages   = ceil($total_records / $page_size);

                    if ($page > $total_pages) {
                        $page = $total_pages;
                    }

                    if ($page < 1) {
                        $page = 1;
                    }

                    $offset = ($page - 1) * $page_size;
                    $posts = array_slice($posts, $offset, $page_size);

                    $index = 0;
                    foreach ($posts as $postData) {
                        $postOwner = $postData['owner'];
                        $postId = $postData['post'];
                        $title = $postData['title'];

                        $badge = "<span style='padding: 0px 0px 0px 0px; font-size: 12px; font-weight: var(--card-title-font-weight);' class='badge'>Post</span>";
                        $extra = '';
                        switch ($postData['status']['code']) {
                            case Post::post_pending_approval():
                                $badge = "<span style='padding: 0px 0px 0px 0px; font-size: 12px; font-weight: var(--card-title-font-weight);' class='badge badge-secondary'>Revision</span>";
                                break;
                            case Post::post_active():
                                $badge = "<span style='padding: 0px 0px 0px 0px; font-size: 12px; font-weight: var(--card-title-font-weight);' class='badge badge-success'>Public</span>";
                                if ($ownerId == $postOwner) {
                                    $issuer = $postData['status']['issuer'];
                                    if ($issuer != null) {
                                        $issuerName = $postConnection->getProfile($issuer);
                                        $extra = "- Approved by <a href='{$config->getHomePath()}profile/?id={$issuer}' target='_blank'>{$issuerName}</a>";
                                    }
                                }
                                break;
                            case Post::post_private():
                                $badge = "<span style='padding: 0px 0px 0px 0px; font-size: 12px; font-weight: var(--card-title-font-weight);' class='badge badge-primary'>Private</span>";
                                if ($ownerId == $postOwner) {
                                    $issuer = $postData['status']['issuer'];
                                    if ($issuer != null) {
                                        $issuerName = $postConnection->getProfile($issuer);
                                        $extra = "- Approved by <a href='{$config->getHomePath()}profile/?id={$issuer}' target='_blank'>{$issuerName}</a>";
                                    }
                                }
                                break;
                            case Post::post_removed():
                                $badge = "<span style='padding: 0px 0px 0px 0px; font-size: 12px; font-weight: var(--card-title-font-weight);' class='badge badge-danger'>Removed</span>";
                                if ($ownerId == $postOwner) {
                                    $issuer = $postData['status']['issuer'];
                                    if ($issuer != null) {
                                        $issuerName = $postConnection->getProfile($issuer);
                                        $extra = "- Removed by <a href='{$config->getHomePath()}profile/?id={$issuer}' target='_blank'>{$issuerName}</a>";
                                    }
                                }
                                break;
                        }

                        $txt = str_replace('[/lb]', '<br>', $postData['content']);
                        $size = count(preg_split('//u', $txt, -1, PREG_SPLIT_NO_EMPTY));
                        if ($size > 30) {
                            $txt = str_split($txt, 30)[0] . ' ...';
                        }

                        $viewType = ($postData['status']['code'] == Post::post_private() ? 'fa-eye' : 'fa-eye-slash');
                        $disabled = ($postData['status']['code'] == Post::post_removed() ? ' disabled ' : ' ');

                        $dAction = ($postData['status']['code'] == Post::post_removed() ? '' : "removePost(\"{$postId}\")");
                        $diAction = ($postData['status']['code'] == Post::post_removed() ? '' : "toggleVisibility(\"{$postId}\")");

                        if ($postOwner == $ownerId) {
                            echo "
                            <div style='cursor: pointer' id='preview_{$postId}' class='postPreview'>
                                <h2 style='font-size: 16px' class='card-title'>
                                    <span style='text-decoration: none' onclick='goPost(\"$postId\")'>{$badge} {$title}</span> {$extra}  | <i class='fa{$disabled}fa-trash' aria-hidden='true' onclick='{$dAction}'></i> | <i class='fa{$disabled}{$viewType}' aria-hidden='true' onclick='{$diAction}'></i>
                                </h2>
                                            
                                <p style='font-size: 12px' class='text-muted' onclick='goPost(\"$postId\")'>
                                    {$txt}
                                </p>
                            </div>
                            ";
                        } else {
                            echo "
                            <div style='cursor: pointer' id='preview_{$postId}' class='postPreview'>
                                <h2 style='font-size: 16px' class='card-title'>
                                    <span style='text-decoration: none' onclick='goPost(\"$postId\")'>{$badge} {$title} </span>{$extra}
                                </h2>
                                <p style='font-size: 12px' class='text-muted' onclick='goPost(\"$postId\")'>
                                    {$txt}
                                </p>
                            </div>
                            ";
                        }

                        if ($index++ != sizeof($posts) - 1) {
                            echo "<hr/>";
                        }
                    }

                    if (count($posts) >= 1) {
                        $N = min($total_pages, 9);
                        $pages_links = array();

                        $tmp = $N;
                        if ($tmp < $page || $page > $N) {
                            $tmp = 2;
                        }
                        for ($i = 1; $i <= $tmp; $i++) {
                            $pages_links[$i] = $i;
                        }

                        if ($page > $N && $page <= ($total_pages - $N + 2)) {
                            for ($i = $page - 3; $i <= $page + 3; $i++) {
                                if ($i > 0 && $i < $total_pages) {
                                    $pages_links[$i] = $i;
                                }
                            }
                        }

                        $tmp = $total_pages - $N + 1;
                        if ($tmp > $page - 2) {
                            $tmp = $total_pages - 1;
                        }
                        for ($i = $tmp; $i <= $total_pages; $i++) {
                            if ($i > 0) {
                                $pages_links[$i] = $i;
                            }
                        }

                        echo '
                        <hr />
                        <div class="d-flex justify-content-center">
                            <nav aria-label="...">
                                <ul class="pagination pagination-sm">
                                <li class="page-item'. ($page == 1 ? ' disabled"' : '" onclick="prev(\'page\')"') . '>
                                        <a href="#" class="page-link w-50" tabindex="-1"><</a>
                                    </li>
                                    ';

                        $prev = 0;
                        foreach ($pages_links as $p) {
                            if ($p == $page) {
                                echo '
                                <li class="page-item active">
                                    <a href="#" class="page-link" tabindex="-1">'. $p .'</a>
                                </li>';
                            } else {
                                $getBuilder = null;
                                if ($p == 1) {
                                    foreach ($_GET as $key => $val) {
                                        if ($key != 'page') {
                                            if ($getBuilder == null) {
                                                $getBuilder = '?' . $key . '=' . $val;
                                            } else {
                                                $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                            }
                                        }
                                    }
                                } else {
                                    foreach ($_GET as $key => $val) {
                                        if ($key != 'page') {
                                            if ($getBuilder == null) {
                                                $getBuilder = '?' . $key . '=' . $val;
                                            } else {
                                                $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                            }
                                        }
                                    }
                                }

                                if ($getBuilder == null) {
                                    $getBuilder = '?page=' . $p;
                                } else {
                                    $getBuilder = $getBuilder . '&page=' . $p;
                                }

                                echo '
                                    <li class="page-item" aria-current="page"><a href="'. $getBuilder .'" class="page-link">'. $p .'</a></li>
                                ';
                            }
                        }

                        echo '
                                    <li class="page-item'. (count($pages_links) == $page ? ' disabled"' : '" onclick="next(\'page\')"') . '>
                                        <a href="#" class="page-link w-50">></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>';
                    }
                }

                echo "</div>";
                echo "<div class='flex-grow-2 card'>";
                echo "<h2 class='card-title'>Liked by user</h2>";
                $likesAndComments = $postConnection->getCommentAndLikes($userId);

                if (strtolower($user_settings['visibility']) == 'public' || $ownerId == $userId) {
                    $likes = $likesAndComments['likes'];

                    if (isset($_GET['lPage'])) {
                        $lPage = intval($_GET['lPage']);
                    } else {
                        $lPage = 1;
                    }
                    
                    $lPage_size = 10;
                    $lTotal_records = count($likes);
                    $lTotal_pages   = ceil($lTotal_records / $lPage_size);

                    if ($lPage > $lTotal_pages) {
                        $lPage = $lTotal_pages;
                    }

                    if ($lPage < 1) {
                        $lPage = 1;
                    }

                    $lOffset = ($lPage - 1) * $lPage_size;
                    $likes = array_slice($likes, $lOffset, $lPage_size);
                    $index = 0;
                    foreach ($likes as $id => $likeData) {
                        $postId = $likeData['id'];
                        $title = $likeData['title'];
                        $comments = $likeData['comments'];
                        $likeCount = $likeData['likes'];

                        $txt = str_replace('[/lb]', '<br>', $likeData['content']);
                        $size = count(preg_split('//u', $txt, -1, PREG_SPLIT_NO_EMPTY));
                        if ($size > 30) {
                            $txt = str_split($txt, 30)[0] . ' ...';
                        }

                        echo "
                            <div style='cursor: pointer' id='preview_{$postId}' class='postPreview'>
                                <h2 style='font-size: 16px' class='card-title'>
                                    <span style='text-decoration: none' onclick='goPost(\"$postId\")'>{$title}</span>
                                    <br>{$comments} <i class='fa fa-comments text-primary mr-5' aria-hidden='true'></i> | {$likeCount} <i class='fa fa-heart text-danger mr-5' aria-hidden='true'></i>
                                </h2>
                                <p style='font-size: 12px' class='text-muted' onclick='goPost(\"$postId\")'>
                                    {$txt}
                                </p>
                            </div>
                        ";

                        if ($index++ != sizeof($likes) - 1) {
                            echo "<hr/>";
                        }
                    }

                    if (count($likes) >= 1) {
                        $N = min($lTotal_pages, 9);
                        $pages_links = array();

                        $tmp = $N;
                        if ($tmp < $lPage || $lPage > $N) {
                            $tmp = 2;
                        }
                        for ($i = 1; $i <= $tmp; $i++) {
                            $pages_links[$i] = $i;
                        }

                        if ($lPage > $N && $lPage <= ($lTotal_pages - $N + 2)) {
                            for ($i = $lPage - 3; $i <= $lPage + 3; $i++) {
                                if ($i > 0 && $i < $lTotal_pages) {
                                    $pages_links[$i] = $i;
                                }
                            }
                        }

                        $tmp = $lTotal_pages - $N + 1;
                        if ($tmp > $lPage - 2) {
                            $tmp = $lTotal_pages - 1;
                        }
                        for ($i = $tmp; $i <= $lTotal_pages; $i++) {
                            if ($i > 0) {
                                $pages_links[$i] = $i;
                            }
                        }

                        echo '
                        <hr />
                        <div class="d-flex justify-content-center">
                            <nav aria-label="...">
                                <ul class="pagination pagination-sm">
                                <li class="page-item'. ($page == 1 ? ' disabled"' : '" onclick="prev(\'lPage\')"') . '>
                                        <a href="#" class="page-link w-50" tabindex="-1"><</a>
                                    </li>
                                    ';

                        foreach ($pages_links as $p) {
                            if ($p == $page) {
                                echo '
                                <li class="page-item active">
                                    <a href="#" class="page-link" tabindex="-1">'. $p .'</a>
                                </li>';
                            } else {
                                $getBuilder = null;
                                if ($p == 1) {
                                    foreach ($_GET as $key => $val) {
                                        if ($key != 'lPage') {
                                            if ($getBuilder == null) {
                                                $getBuilder = '?' . $key . '=' . $val;
                                            } else {
                                                $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                            }
                                        }
                                    }
                                } else {
                                    foreach ($_GET as $key => $val) {
                                        if ($key != 'lPage') {
                                            if ($getBuilder == null) {
                                                $getBuilder = '?' . $key . '=' . $val;
                                            } else {
                                                $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                            }
                                        }
                                    }
                                }

                                if ($getBuilder == null) {
                                    $getBuilder = '?lPage=' . $p;
                                } else {
                                    $getBuilder = $getBuilder . '&lPage=' . $p;
                                }

                                echo '
                                    <li class="page-item" aria-current="page"><a href="'. $getBuilder .'" class="page-link">'. $p .'</a></li>
                                ';
                            }
                        }

                        echo '
                                    <li class="page-item'. (count($pages_links) == $page ? ' disabled"' : '" onclick="next(\'lPage\')"') . '>
                                        <a href="#" class="page-link w-50">></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>';
                    }
                }
                echo "</div>";
                echo "<div class='flex-grow-3 card'>";
                echo "<h2 class='card-title'>Commented by user</h2>";
                if (strtolower($user_settings['visibility']) == 'public' || $ownerId == $userId) {
                    $comments = $likesAndComments['comments'];
                    $comments = array_reverse($comments, true);

                    if (isset($_GET['cPage'])) {
                        $cPage = intval($_GET['cPage']);
                    } else {
                        $cPage = 1;
                    }
                    
                    $cPage_size = 5;
                    $cTotal_records = count($comments);
                    $cTotal_pages   = ceil($cTotal_records / $cPage_size);

                    if ($cPage > $cTotal_pages) {
                        $cPage = $cTotal_pages;
                    }

                    if ($cPage < 1) {
                        $cPage = 1;
                    }

                    $cOffset = ($cPage - 1) * $cPage_size;
                    $comments = array_slice($comments, $cOffset, $cPage_size);
                    $index = 0;
                    foreach ($comments as $id => $commentData) {
                        $postId = $commentData['id'];
                        $title = $commentData['title'];
                        $commentCount = $commentData['comments'];
                        $likes = $commentData['likes'];

                        $txt = str_replace('[/lb]', '<br>', $commentData['content']);
                        $size = count(preg_split('//u', $txt, -1, PREG_SPLIT_NO_EMPTY));
                        if ($size > 30) {
                            $txt = str_split($txt, 30)[0] . ' ...';
                        }

                        $comment = str_replace('[/lb]', '<br>', $commentData['comment']);
                        $size = count(preg_split('//u', $comment, -1, PREG_SPLIT_NO_EMPTY));
                        if ($size > 30) {
                            $comment = str_split($comment, 30)[0] . ' ...';
                        }

                        echo "
                            <div style='cursor: pointer' id='preview_{$postId}' class='postPreview'>
                                <h2 style='font-size: 16px' class='card-title'>
                                    <span style='text-decoration: none' onclick='goPost(\"$postId\")'>{$title}</span>
                                    <br>{$commentCount} <i class='fa fa-comments text-primary mr-5' aria-hidden='true'></i> | {$likes} <i class='fa fa-heart text-danger mr-5' aria-hidden='true'></i>
                                </h2>
                                <p style='font-size: 12px' class='text-muted' onclick='goPost(\"$postId\")'>
                                    {$txt}
                                </p>
                            </div>
                            <hr/>
                            <p style='font-size: 12px;' class='text-muted'>
                                <strong>{$profileName}</strong>: {$comment}
                            </p>
                        ";

                        if ($index++ != sizeof($comments) - 1) {
                            echo "<hr/>";
                        }
                    }

                    if (count($comments) >= 1) {
                        $N = min($cTotal_pages, 9);
                        $pages_links = array();

                        $tmp = $N;
                        if ($tmp < $cPage || $cPage > $N) {
                            $tmp = 2;
                        }
                        for ($i = 1; $i <= $tmp; $i++) {
                            $pages_links[$i] = $i;
                        }

                        if ($cPage > $N && $cPage <= ($cTotal_pages - $N + 2)) {
                            for ($i = $cPage - 3; $i <= $cPage + 3; $i++) {
                                if ($i > 0 && $i < $cTotal_pages) {
                                    $pages_links[$i] = $i;
                                }
                            }
                        }

                        $tmp = $cTotal_pages - $N + 1;
                        if ($tmp > $cPage - 2) {
                            $tmp = $cTotal_pages - 1;
                        }
                        for ($i = $tmp; $i <= $cTotal_pages; $i++) {
                            if ($i > 0) {
                                $pages_links[$i] = $i;
                            }
                        }

                        echo '
                        <hr />
                        <div class="d-flex justify-content-center">
                            <nav aria-label="...">
                                <ul class="pagination pagination-sm">
                                <li class="page-item'. ($page == 1 ? ' disabled"' : '" onclick="prev(\'cPage\')"') . '>
                                        <a href="#" class="page-link w-50" tabindex="-1"><</a>
                                    </li>
                                    ';

                        foreach ($pages_links as $p) {
                            if ($p == $page) {
                                echo '
                                <li class="page-item active">
                                    <a href="#" class="page-link" tabindex="-1">'. $p .'</a>
                                </li>';
                            } else {
                                $getBuilder = null;
                                if ($p == 1) {
                                    foreach ($_GET as $key => $val) {
                                        if ($key != 'cPage') {
                                            if ($getBuilder == null) {
                                                $getBuilder = '?' . $key . '=' . $val;
                                            } else {
                                                $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                            }
                                        }
                                    }
                                } else {
                                    foreach ($_GET as $key => $val) {
                                        if ($key != 'cPage') {
                                            if ($getBuilder == null) {
                                                $getBuilder = '?' . $key . '=' . $val;
                                            } else {
                                                $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                            }
                                        }
                                    }
                                }

                                if ($getBuilder == null) {
                                    $getBuilder = '?cPage=' . $p;
                                } else {
                                    $getBuilder = $getBuilder . '&cPage=' . $p;
                                }

                                echo '
                                    <li class="page-item" aria-current="page"><a href="'. $getBuilder .'" class="page-link">'. $p .'</a></li>
                                ';
                            }
                        }

                        echo '
                                    <li class="page-item'. (count($pages_links) == $page ? ' disabled"' : '" onclick="next(\'cPage\')"') . '>
                                        <a href="#" class="page-link w-50">></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>';
                    }
                }
                echo "</div>";

                echo "</div>";
                echo "</div>";
            }
        }
    ?>
    
    <style>
        .fa {
            cursor: pointer;
        }

        .disabled:hover {
            color: inherit !important; 
        }

        .fa-trash:hover {
            color: #ff4d4f;
        }

        .fa-eye:hover {
            color: #62e177;
        }

        .fa-eye-slash:hover {
            color: #ff4d4f;
        }
    </style>

    <script type="text/javascript">
        var urlParams = new URLSearchParams(window.location.search);
        var viewType = urlParams.get('view');

        if (viewType && document.getElementById('toggleView') != null) {
            var preview = document.getElementById('preview');
            var edit = document.getElementById('description');

            if (viewType == 'user') {
                preview.style.display = 'block';
                edit.style.display = 'none';
            } else {
                if (viewType == 'edit') {
                    preview.style.display = 'none';
                    edit.style.display = 'block';
                }
            }
        }

        function submitProfile() {
            requestTemporalAPIKey(function(api_key) {
                data = new FormData()
                data.set('method', 'account');
                data.set('action', 'description');
                data.set('api', api_key);
                data.set('content', document.getElementById('description').value);

                let request = new XMLHttpRequest();
                request.onreadystatechange = (e) => {
                    if (request.readyState !== 4) {
                        return;
                    }

                    if (request.status == 200) {
                        var json = JSON.parse(request.responseText);
                        
                        if (json['success']) {
                            Swal.fire({
                                'title': 'Done',
                                'text': 'Your profile has been updated',
                                'icon': 'success',
                                'showCancelButton': false,
                                'confirmButtonText': 'Perfect!',
                            }).then((result) => {
                                document.location.reload(true);
                            });
                        } else {
                            Swal.fire({
                                'title': 'Error',
                                'text': json['message'],
                                'icon': 'error',
                                'showCancelButton': false,
                                'confirmButtonText': 'Ok',
                            }).then((result) => {
                                document.location.reload(true);
                            });
                        }
                    }
                }

                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                request.open("POST", host + 'api/', false);
                request.send(data)
            });
        }

        function removePost(postId) {
            Swal.fire({
                'title': 'Confirmation',
                'text': 'The post ' + postId + ' will be removed. This action can be undone, but additional support will be required to recover the post',
                'icon': 'warning',
                'showCancelButton': true,
                'confirmButtonText': 'Yes',
                'cancelButtonText': 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    requestTemporalAPIKey(function(api_key) {
                        data = new FormData();

                        data.set('method', 'post');
                        data.set('action', 'remove');
                        data.set('api', api_key);
                        data.set('post', postId);

                        let request = new XMLHttpRequest();
                        request.onreadystatechange = (e) => {
                            if (request.readyState !== 4) {
                                return;
                            }

                            if (request.status == 200) {
                                var json = JSON.parse(request.responseText);
                                
                                if (json['success']) {
                                    Swal.fire({
                                        'title': 'Done',
                                        'text': 'The post ' + postId + ' has been removed',
                                        'icon': 'success',
                                        'showCancelButton': false,
                                        'confirmButtonText': 'Perfect!',
                                    }).then((result) => {
                                        document.location.reload(true);
                                    });
                                } else {
                                    Swal.fire({
                                        'title': 'Error',
                                        'text': json['message'],
                                        'icon': 'error',
                                        'showCancelButton': false,
                                        'confirmButtonText': 'Ok',
                                    }).then((result) => {
                                        document.location.reload(true);
                                    });
                                }
                            }
                        }

                        var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                        request.open("POST", host + 'api/', false);
                        request.send(data);
                    });
                }
            });
        }

        function toggleVisibility(postId) {
            requestTemporalAPIKey(function(api_key) {
                data = new FormData();

                data.set('method', 'post');
                data.set('action', 'visibility');
                data.set('api', api_key);
                data.set('post', postId);

                let request = new XMLHttpRequest();
                request.onreadystatechange = (e) => {
                    if (request.readyState !== 4) {
                        return;
                    }

                    if (request.status == 200) {
                        var json = JSON.parse(request.responseText);
                        
                        if (!json['success']) {
                            Swal.fire({
                                'title': 'Oh no!',
                                'text': json['message'],
                                'icon': 'error',
                                'showCancelButton': false,
                                'confirmButtonText': 'Ok',
                            }).then((result) => {
                                document.location.reload(true);
                            });
                        } else {
                            document.location.reload(true);
                        }
                    }
                }

                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                request.open("POST", host + 'api/', false);
                request.send(data);
            });
        }

        function setCookie(cName, cValue, expDays) {
            let date = new Date();
            date.setTime(date.getTime() + (expDays * 24 * 60 * 60 * 1000));
            const expires = "expires=" + date.toUTCString();
            document.cookie = cName + "=" + cValue + "; " + expires + "; path=/";
        }

        function getCookie(cName) {
            const name = cName + "=";
            const cDecoded = decodeURIComponent(document.cookie);
            const cArr = cDecoded .split('; ');
            let res = null;
            cArr.forEach(val => {
                if (val.indexOf(name) === 0) res = val.substring(name.length);
            })
            return res;
        }

        var url = new URL(window.location);
        var viewCookie = url.searchParams.get('view');
        if (viewCookie) {
            if (getCookie('view') != viewCookie) {
                setCookie('view', url.searchParams.get('view'), 365);
                window.location.reload(true);
            }
        }

        document.getElementById('toggleView').addEventListener('click', (event) => {
            var preview = document.getElementById('preview');
            var edit = document.getElementById('description');

            if (preview.offsetParent == null) {
                var finalEdit = edit.value;
                if (edit.value.includes('\n')) {
                    var editData = edit.value.split('\n');
                    finalEdit = '';
                    for (var line of editData) {
                        finalEdit = finalEdit + line + '<br>';
                    }
                }

                preview.innerHTML = finalEdit.replace('%username%', '<?php echo $currentName ?>');
                preview.style.display = 'block';
                edit.style.display = 'none';

                setCookie('view', 'user', 365);
                url.searchParams.set('view', 'user');
            } else {
                preview.style.display = 'none';
                edit.style.display = 'block';

                setCookie('view', 'edit', 365);
                url.searchParams.set('view', 'edit');
            }

            window.history.pushState('state', 'title', url);
        })

        document.getElementById("if-8-file-input").onchange = function(e) {
            let file = e.target.files[0];
            let reader = new FileReader();

            reader.onload = function(e) {
                var image = document.createElement("img");
                image.src = e.target.result;

                Swal.fire({
                    'imageUrl': e.target.result,
                    'imageHeight': 120,
                    'imageWidth': 120,
                    'title': 'Confirmation',
                    'text': 'Are you sure you want to change your avatar with this one?',
                    'icon': 'warning',
                    'showCancelButton': true,
                    'confirmButtonText': 'Yes!',
                    'cancelButtonText': 'No!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById("avatarForm").submit();
                    }
                });
            }

            reader.readAsDataURL(file);
        };

        function next(paramPageName) {
            var urlParams = new URLSearchParams(window.location.search);
            var current_page = urlParams.get(paramPageName);

            if (!current_page) {
                current_page = 2;
                urlParams.append(paramPageName, current_page);
            } else {
                urlParams.set(paramPageName, parseInt(current_page) + 1);
            }

                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';
                window.location = host + '?' + urlParams.toString();
        }

        function prev(paramPageName) {
            var urlParams = new URLSearchParams(window.location.search);
            var current_page = urlParams.get(paramPageName);

            if (current_page) {
                if (current_page - 1 == 1) {
                    urlParams.delete(paramPageName);
                } else {
                    urlParams.set(paramPageName, parseInt(current_page) - 1);
                }
            }

            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';
            window.location = host + '?' + urlParams.toString();
        }
    </script>
</body>
</html>