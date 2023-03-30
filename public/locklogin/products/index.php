<?php
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
use KarmaDev\Panel\SQL\LockLogin;

$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/header.php';

use KarmaDev\Panel\SQL\ClientData;

use KarmaDev\Panel\Utilities as Utils;

$locklogin = new LockLogin();
if (isset($_POST['name']) && isset($_POST['internal']) && isset($_POST['version']) && isset($_POST['min_ver']) && isset($_POST['max_ver']) && isset($_POST['description'])) {
    if (ClientData::isAuthenticated()) {
        $cl = unserialize(Utils::get('client'));

        if ($cl->hasPermission('locklogin_manage_modules')) {
            $name = $_POST['name'];
            $internal = str_replace(' ', '_', $_POST['internal']);
            $version = $_POST['version'];
            $min_ver = $_POST['min_ver'];
            $max_ver = $_POST['max_ver'];
            $description = $_POST['description'];

            $target_dir = "{$config->getWorkingDirectory()}vendor/upload/";
            $target_file = $target_dir . basename($_FILES["moduleFile"]["name"]);
        
            $uploadFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
            $extensions_arr = array("jar","zip");
            $rand = Utils::generate(32);

            if(in_array($uploadFileType, $extensions_arr)) {
                if(move_uploaded_file($_FILES['moduleFile']['tmp_name'], $target_dir . $rand)) {
                    $handle = fopen($target_dir . $rand, "r");
                    $contents = fread($handle, filesize($target_dir . $rand));
                    fclose($handle);
                    unlink($target_dir . $rand);

                    if ($locklogin->addModule($name, $internal, $version, $min_ver, $max_ver, $description, base64_encode($contents))) {
                        $result = '<script type="text/javascript">console.info(\'Module added successfully\')</script>';
                    } else {
                        $result = '<script type="text/javascript">console.warn(\'Failed to add module\')</script>';
                    }
                } else {
                    $result = '<script type="text/javascript">console.warn(\'Failed to add module because the uploaded module file failed to be moved to uploads folder\')</script>';
                }
            } else {
                $result = '<script type="text/javascript">console.warn(\'Failed to add module because module file is not of expected extension ( .jar | .zip )\')</script>';
            }
        }
    }
}

ClientData::performAction('viewing <a href="'. $config->getHomePath() .'locklogin/products/">LockLogin modules</a>.');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>KarmaDev Panel | LockLogin | Modules</title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.5.7/angular.min.js"></script>
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/angularjs/1.5.7/angular-animate.js"></script>
</head>
<body data-set-preferred-mode-onload="true">
    <?php
    if (isset($result)) {
        echo $result;
    }

    if (ClientData::isAuthenticated()) {
        $cl = unserialize(Utils::get('client'));

        if ($cl->hasPermission('locklogin_manage_modules')) {
            ?>
            <div class="card">
                <h2 class="card-title">
                    New module
                </h2>
                
                <form action="" method="post" enctype="multipart/form-data" class="w-400 mw-full">
                    <div class="form-group">
                        <label for="full-name">Name</label>
                        <input type="text" name="name" class="form-control" id="full-name" placeholder="Module name">
                    </div>

                    <div class="form-group">
                        <label for="internal-name">Internal Name</label>
                        <input type="text" name="internal" class="form-control" id="internal-name" placeholder="ModuleName">
                    </div>

                    <div class="form-group">
                        <label for="version">Version</label>
                        <input type="text" name="version" class="form-control" id="version" placeholder="Current module version">
                    </div>

                    <div class="form-group">
                        <label for="min-version">Minimal version</label>
                        <input type="text" name="min_ver" class="form-control" id="min-version" placeholder="LockLogin minimal version">
                    </div>

                    <div class="form-group">
                        <label for="max-version">Max version</label>
                        <input type="text" name="max_ver" class="form-control" id="max-version" placeholder="LockLogin max version">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" placeholder="What does the module do?"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="module">Module file</label>
                        <div class="custom-file">
                            <input type="file" name="moduleFile" id="module" accept=".jar, .zip">
                            <label for="module">Choose module file</label>
                        </div>
                    </div>

                    <input class="btn btn-primary" type="submit" value="Submit">
                </form>
            </div>
            <?php
        }
    }
    ?>

    <div class="d-flex justify-content-around">
        <?php

        $modules = $locklogin->getModules();

        $displayed = 1;
        foreach ($modules as $mN => $mId) {
            $module = $locklogin->getModule($mId, false);

            if ($displayed > 3) {
                ?>
                </div>
                <div class="d-flex justify-content-around">
                <?php
                $displayed = 0;
            }

            $displayed++;
            ?>
                <div class="w-500">
                    <div class="card" id="<?php echo $module['internal_name']; ?>">
                        <h2 class="card-title">
                            <?php echo $module['name'] ?>
                        </h2>
                        <p class="text-left">
                            <?php echo str_replace("\n", "<br>", $module['description']); ?>
                        </p>

                        <div class="d-flex justify-content-around">
                            <div><select class="form-control" id="version_<?php echo $mId; ?>_download">
            <?php

            $versions = $module['versions'];
            foreach ($versions as $version => $versionInfo) {
                ?>
                <option value="<?php echo $versionInfo['version_id']; ?>;<?php echo $versionInfo['min_version']; ?>;<?php echo $versionInfo['max_version']; ?>;<?php echo $version ?>"><?php echo $version ?></option>
                <?php
            }

            ?>
                                </select>
                            </div>
                            <div>
                                <button class="btn btn-success" type="button" onclick='download(<?php echo $mId; ?>,"<?php echo $module["name"]; ?>")'>Download</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
        }
        ?>

        <script type="text/javascript">
            async function download(mId, mName) {
                var selection = document.getElementById('version_' + mId + '_download');
                
                var value = selection.value;
                let data = value.split(';');

                var url = './product.php?download=' + data[0] + Math.random().toString().substr(2, 8);

                var minimal = data[1];
                var maximum = data[2];
                var version = data[3];

                if (maximum != 'latest') {
                    Swal.fire({
                        'title': 'Download module ' + mName,
                        'text': 'Are you sure you want to download version ' + version + '? It is only compatible for version ' + minimal + ' to ' + maximum,
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
                } else {
                    window.location = url;
                } 
            }
        </script>
    </div>
</body>
</html>