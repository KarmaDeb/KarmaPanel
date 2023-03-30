<?php
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/header.php';

use KarmaDev\Panel\SQL\ClientData;
use KarmaDev\Panel\SQL\PostData;
use KarmaDev\Panel\SQL\LockLogin;

use KarmaDev\Panel\Codes\PostStatus as Post;

use KarmaDev\Panel\Utilities as Utils;

use KarmaDev\Panel\Client\User;
use KarmaDev\Panel\Client\UserLess;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

ClientData::performAction('viewing <a href="'. $config->getHomePath() .'locklogin/">LockLogin versions</a>.');
$locklogin = new LockLogin();

if (isset($_GET['qr'])) {
    $qr = QrCode::create($_GET['qr']);
    $writer = new PngWriter();

    $image = 'data:image/png;base64,' . base64_encode($writer->write($qr)->getString());
} else {
    if (isset($_POST['view_channel'])) {
        Utils::set('locklogin_version_channel', $_POST['view_channel']);
        $result = '<script type="text/javascript">console.info(\'Successfully changed version channel\')</script>';
    } else {
        $result = '<script type="text/javascript">console.info(\'No version channel\')</script>';
    }

    if (isset($_POST['version']) && isset($_POST['version_name']) && isset($_POST['channel']) && isset($_POST['changelog'])) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            if ($cl->hasPermission('locklogin_manage_updates')) {
                $version = $_POST['version'];
                $update_name = $_POST['version_name'];
                $channel = $_POST['channel'];
                $changelog = $_POST['changelog'];

                $target_dir = "{$config->getWorkingDirectory()}vendor/upload/";
                $target_file = $target_dir . basename($_FILES["updateFile"]["name"]);
            
                $uploadFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            
                $extensions_arr = array("jar","zip");
                $rand = Utils::generate(32);

                if(in_array($uploadFileType, $extensions_arr)) {
                    if(move_uploaded_file($_FILES['updateFile']['tmp_name'], $target_dir . $rand)) {
                        $handle = fopen($target_dir . $rand, "r");
                        $contents = fread($handle, filesize($target_dir . $rand));
                        fclose($handle);
                        unlink($target_dir . $rand);

                        if ($locklogin->update($version, $update_name, $channel, $changelog, base64_encode($contents))) {
                            $result = '<script type="text/javascript">console.info(\'Versions added successfully\')</script>';
                        } else {
                            $result = '<script type="text/javascript">console.warn(\'Failed to add version\')</script>';
                        }
                    } else {
                        $result = '<script type="text/javascript">console.warn(\'Failed to add version because the uploaded version file failed to be moved to uploads folder\')</script>';
                    }
                } else {
                    $result = '<script type="text/javascript">console.warn(\'Failed to add version because version file is not of expected extension ( .jar | .zip )\')</script>';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KarmaDev Panel | LockLogin</title>
</head>
<body data-set-preferred-mode-onload="true">
    <h1 class="text-center" style="color: #e34250">Under development</h1>

    <?php
    if (!isset($image)) {
        if (isset($result)) {
            echo $result;
        }

        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));

            if ($cl->hasPermission('locklogin_manage_updates')) {
                ?>
                <div class="card">
                    <h2 class="card-title">
                        New update
                    </h2>
                    
                    <form action="" method="post" enctype="multipart/form-data" class="w-400 mw-full">
                        <div class="form-group">
                            <label for="version">Version</label>
                            <input type="text" name="version" class="form-control" id="version" placeholder="Version">
                        </div>

                        <div class="form-group">
                            <label for="version-name">Update name</label>
                            <input type="text" name="version_name" class="form-control" id="version-name" placeholder="Version name">
                        </div>

                        <div class="form-group">
                            <label for="channel-filter" id='view-text'>Channel</label>
                            <select name='channel' class="form-control" id="channel-filter">
                                    <option value="release">Release</option>
                                    <option value="candidate">Release candidate</option>
                                    <option value="snapshot">Snapshot</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="changelog">Changelog</label>
                            <textarea class="form-control" name="changelog" id="changelog" placeholder="What has changed?"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="update">Version file</label>
                            <div class="custom-file">
                                <input type="file" name="updateFile" id="update" accept=".jar, .zip">
                                <label for="update">Choose version file</label>
                            </div>
                        </div>

                        <input class="btn btn-primary" type="submit" value="Submit">
                    </form>
                </div>
                <?php
            }
        }
    }
    ?>

    <?php
    if (!isset($image)) {
    ?>
        <div class="d-flex justify-content-center">
            <div class="w-650 mw-650 h-800 mw-800">
                <div class="card" id='container'>
                    <form>
                        <div class="form-row row-eq-spacing-sm">
                            <div class="col-sm">
                                <label for="channel-filter" id='view-text'>Channel</label>
                                <select onchange='addFilter(this)' class="form-control" id="channel-filter">
                                    <option value="release" <?php echo (Utils::get('locklogin_version_channel') == 'release' ? 'selected' : '') ?>>Releases</option>
                                    <option value="candidate" <?php echo (Utils::get('locklogin_version_channel') == 'candidate' ? 'selected' : '') ?>>Release candidates ( beta )</option>
                                    <option value="snapshot" <?php echo (Utils::get('locklogin_version_channel') == 'snapshot' ? 'selected' : '') ?>>Snapshots ( alpha )</option>
                                </select>
                            </div>
                        </div>
                    </form>

                    <h2 class="card-title">
                        Released versions
                    </h2>
                    <?php
                    $lockloginVersions = $locklogin->getUpdates(Utils::get('locklogin_version_channel'));

                    $latest = null;
                    foreach ($lockloginVersions as $version => $versionInfo) {
                        if ($latest == null) {
                            $latest = $versionInfo['id'];
                        }

                        $badge = '<span class="badge badge-primary">Release</span>';
                        if ($versionInfo['channel'] == 'candidate') {
                            $badge = '<span class="badge badge-secondary">RC</span>';
                        } else {
                            if ($versionInfo['channel'] == 'snapshot') {
                                $badge = '<span class="badge badge-danger">Snapshot</span>';
                            }
                        }

                        $c_date = $versionInfo['release'];

                        $newTimezone = new DateTime($c_date, new DateTimeZone('UTC'));
                        $newTimezone->setTimezone(new DateTimeZone(Utils::get('timezone')));

                        $c_date = $newTimezone->format('U');

                        echo '
                        <div class="card">
                            <p>'. $badge .' '. $version .' - '. date("d/m/yyyy H:i:s", $c_date) .' ( '. Utils::getTimeAgo($c_date) .' )</p>
                            <p style="overflow-y: auto; height: 150px">
                            '.
                            str_replace("\n", '<br>', $versionInfo['changelog'])
                            . "\n" .'</p>
                            <button class="btn btn-success" type="button" onclick="download(\''. $version .'\','. ($locklogin->getOldness($versionInfo['id'], $latest) - 1) .')">Download</button>
                        </div>
                        ';
                    }
                    ?>
                </div>
            </div>
        </div>
    
        <script type="text/javascript">
            function addFilter(selection) {
                data = new FormData()
                data.set('view_channel', selection.value);

                let request = new XMLHttpRequest();
                request.onreadystatechange = (e) => {
                    if (request.readyState !== 4) {
                        return;
                    }

                    if (request.status == 200) {
                        window.location.reload(true);
                    }
                }
                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                request.open("POST", host + 'locklogin/', false);
                request.send(data)   
            }

            function download(version, breach) {
                var url = './download.php?download=' + version;

                if (breach == 0) {
                    window.location = url;
                } else {
                    Swal.fire({
                        'title': 'Download LockLogin',
                        'text': 'Are you sure you want to download version ' + version + '? It is ' + breach + ' versions behind the latest and it may contain already fixed bugs',
                        'icon': 'warning',
                        'showCancelButton': false,
                        'showDenyButton': true,
                        'cancelButtonText': 'No',
                        'confirmButtonText': 'Yes',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location = url;
                        }
                    });
                }
            }
        </script>

        <style>
            #container {
                overflow-y: scroll !important;
                height: 800px;
                width: 650px;
            }

            #container::-webkit-scrollbar {
                width: 0px;
            }

            #container p::-webkit-scrollbar {
                width: 0px;
            }
        </style>
    <?php 
    } else {
        ?>
        <div class="d-flex justify-content-center">
            <div>
                <img src="<?php echo $image; ?>" alt="LockLogin QR code">
            </div>
        </div>
        <?php
    }
    ?>
</body>
</html>