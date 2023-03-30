<?php
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/header.php';

use KarmaDev\Panel\SQL\ClientData;

use KarmaDev\Panel\SQL\PostData;

use KarmaDev\Panel\Codes\PostStatus as Post;

use KarmaDev\Panel\Utilities as Utils;

use KarmaDev\Panel\Client\User;
use KarmaDev\Panel\Client\UserLess;

ClientData::performAction('on the <a href="'. $config->getHomePath() .'">main</a> site.');

$hasLike = false;
$data = [];
$showOnline = true;

if (ClientData::isAuthenticated()) {
    $cl = unserialize(Utils::get('client')); //Predefine

    if (isset($_GET['edit'])) {
        $tmp_id = $_GET['edit'];
        $postConnection = new PostData();
    
        $data = $postConnection->getPost(htmlspecialchars($tmp_id));
        if (sizeof($data) >= 9) {
            if (($data['owner'] == $cl->getIdentifier() && $cl->hasPermission('edit_post')) || $cl->hasPermission('edit_other_post')) {
                $edit_id = $tmp_id; //We allow to edit
            }
        }
    }
}

if (isset($_GET['post'])) {
    $post_id = $_GET['post'];
    $postConnection = new PostData();

    $data = $postConnection->getPost(htmlspecialchars($post_id));
    if (sizeof($data) >= 9) {
        if (ClientData::isAuthenticated()) {
            $cl = unserialize(Utils::get('client'));
            $nm = $cl->getName();

            foreach ($data['likes'] as $client) {
                if ($nm == $client) {
                    $hasLike = false;
                    break;
                }
            }
        }
    }
}

$title = 'Home';
if (isset($data) && count($data) >= 1) {
    $showOnline = false;
    $title = $data['title'];
} else {
    if (isset($post_id) && $post_id == 'new-post') {
        $showOnline = false;
        $title = 'New post';
    } else {
        if (isset($post_id) && $post_id == 'approve') {
            $showOnline = false;
            $title = 'Approve post';
        } else {
            if (isset($edit_id)) {
                $showOnline = false;
                $title = 'Edit post';
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

    <title>KarmaDev Panel | <?php echo $title; ?></title>

    <style>
        #likebutton:hover {
            cursor: pointer;
        }
    </style>
</head>
<body data-set-preferred-mode-onload="true">
    <?php
    if (isset($edit_id)) {
        $client = unserialize(Utils::get('client'));
        
        $owner = $data['owner'];
        $status = $data['status'];
        $code = $status['code'];
        if (isset($cl)) {
            $title = $data['title'];
            $content = str_replace("<br>", "\n", str_replace('[/lb]', '<br>', $data['content']));
            $tags = $data['tags'];
            $modified = $data['modified'];
            $comments = $data['comments'];
            $likes = $data['likes'];

            ClientData::performAction('editing post <a href="'. $config->getHomePath() .'?post='. $edit_id .'">'. $title .'</a>.');

            ?>
            <!-- Card with no padding with a content container nested inside of it -->
            <div class="w-800 mh-full mw-full"> <!-- w-400 = width: 40rem (400px), mw-full = max-width: 100% -->
                <!-- Card with no padding with multiple content containers nested inside of it -->
                <div class="w-800 mh-full mw-full"> <!-- w-600 = width: 60rem (600px), mw-full = max-width: 100% -->
                    <div class="card p-0"> <!-- p-0 = padding: 0 -->
                        <!-- First content container nested inside card -->
                        <div class="content">
                        <h2 contenteditable="true" id='post_title' class="content-title">
                            <?php echo $title; ?>
                        </h2>
                        <div>
                            <span class="text-muted">
                                <i class="fa fa-clock-o mr-5" aria-hidden="true"></i> <?php echo $modified; ?> - <?php 
                                    $owner_name = $postConnection->getProfile($owner);
                                    $connection = new ClientData();
                                    $less = $connection->getUser($owner_name, null, true);

                                    $group_name = $less->getGroup();
                                    $priority = $less->getGroupPriority();

                                    $patreon = $connection->loadPatreon($owner_name);
                                    $patreonBadge = '';

                                    if ($patreon != null && !empty($patreon) && gettype($patreon) == 'array') {
                                        if (isset($patreonData['included'])) {
                                            $patreonData = $patreon['included'];
                                    
                                            foreach ($patreonData as $includedData) {
                                                if (isset($includedData['attributes']) && isset($includedData['id'])) {
                                                    $id = $includedData['id'];
                                                    if ($id == 'd36adb45-1842-4fed-88a3-1ce77843bb99') {
                                                        $attributes = $includedData['attributes'];
                                                        
                                                        if ($attributes['patron_status'] == 'declined_patron') {
                                                            $patreonBadge = '<span class="badge badge-danger">Ex Patreon</span>  ';
                                                        } else {
                                                            $patreonBadge = '<span class="badge badge-secondary">Patreon</span>  ';
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    $bdStyle = '';
                                    if ($priority > 0) {
                                        switch ($priority) {
                                            case 1:
                                                $bdStyle = 'badge-success';
                                                break;
                                            case 2:
                                                $bdStyle = 'badge-primary';
                                                break;
                                            case 3:
                                                $bdStyle = 'badge-secondary';
                                                break;
                                            case 4:
                                            default:
                                                $bdStyle = 'badge-danger';
                                                break;
                                        }
                                    }

                                    echo $patreonBadge . "<span style='padding: 0px 0px 0px 0px' class='badge {$bdStyle}'>{$group_name}</span> <a href='{$config->getHomePath()}profile/?id={$owner}'>{$owner_name}</a>";
                                ?> <!-- mr-5 = margin-right: 0.5rem (5px) -->
                            </span>
                        </div>
                        <div>
                            <span class="badge">
                                <i class="fa fa-comments text-primary mr-5" aria-hidden="true"></i> <?php echo sizeof($comments); ?> comments <!-- text-primary = color: primary-color, mr-5 = margin-right: 0.5rem (5px) -->
                            </span>
                            <span class="badge ml-5" id="likebutton"> <!-- ml-5 = margin-left: 0.5rem (5px) -->
                                <i class="fa fa-heart text-danger mr-5" aria-hidden="true"></i> <span id="liketext"><?php echo sizeof($likes); ?> likes</span> <!-- text-danger = color: danger-color, mr-5 = margin-right: 0.5rem (5px) -->
                            </span>
                        </div>
                        </div>
                        <hr/>
                        <form onSubmit="return false;" class="form-inline mw-full"> <!-- w-400 = width: 40rem (400px), mw-full = max-width: 100% -->
                            <textarea style="resize: none; overflow: hidden; margin: 0px 20px 0px 20px" type="text" class="form-control" placeholder="My post content" id="new-post-content" required="required"><?php echo $content; ?></textarea>
                        </form>

                        <p class='text-center'><button class="btn" onclick="savePost()" type="button">Save</button></p>

                        <script type="text/javascript">
                            const textarea = document.getElementById("new-post-content");

                            textarea.style.height = "auto";
                            textarea.style.height = textarea.scrollHeight + "px";

                            window.onresize = function(event) {
                                textarea.style.height = "auto";
                                textarea.style.height = textarea.scrollHeight + "px";
                            }

                            textarea.addEventListener("input", function (e) {
                                textarea.style.height = "auto";
                                textarea.style.height = textarea.scrollHeight + "px";
                            });

                            function savePost() {
                                var content = textarea.value;
                                requestTemporalAPIKey(function(api_key) {
                                    data = new FormData()
                                    data.set('method', 'post');
                                    data.set('action', 'edit');
                                    data.set('api', api_key);
                                    data.set('title', document.getElementById('post_title').innerHTML);
                                    data.set('content', content);
                                    data.set('post', '<?php echo $edit_id; ?>');

                                    var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                    let request = new XMLHttpRequest();
                                    request.onreadystatechange = (e) => {
                                        if (request.readyState !== 4) {
                                            return;
                                        }

                                        if (request.status == 200) {
                                            var json = JSON.parse(request.responseText);
                                            
                                            if (json['success']) {
                                                document.location.href = host + '?post=<?php echo $edit_id ?>';
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

                                    request.open("POST", host + 'api/', false);
                                    request.send(data)
                                });
                            }
                        </script>
                    </div>
                </div>
            </div>
            <?php
        } else {
            ?>
            <script type="text/javascript">
                Swal.fire({
                    'title': 'Error',
                    'text': 'You do not have permissions to view this post',
                    'icon': 'error',
                    'showCancelButton': true,
                    'confirmButtonText': 'Dismiss',
                    'cancelButtonText': 'Take me back'
                }).then((result) => {
                    if (!result.isConfirmed) {
                        alert(window.history.back());
                    }
                })
            </script>
            <?php
        }
    } else {
        if (sizeof($data) >= 1) {
            $client = unserialize(Utils::get('client'));
            
            $owner = $data['owner'];
            $status = $data['status'];
            $code = $status['code'];
            
            if ($code == Post::post_active() || ($client != null && $client->hasPermission('approve_post')) || ($client != null && $client->getIdentifier() == $owner)) {
                $title = $data['title'];
                $content = str_replace('[/lb]', '<br>', $data['content']);
                $tags = $data['tags'];
                $modified = $data['modified'];
                $comments = $data['comments'];
                $likes = $data['likes'];

                if ($code == Post::post_active()) {
                    ClientData::performAction('viewing post <a href="'. $config->getHomePath() .'?post='. $post_id .'">'. $title .'</a>.');
                }

                $regex = '#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#';
                $content = preg_replace_callback($regex, function ($matches) {
                    $img = getimagesize($matches[0]);
                    if (gettype($img) != 'array') {
                        $youtubePattern = '#^(?:https?://)?(?:www\.)?(?:youtu\.be/|youtube\.com(?:/embed/|/v/|/watch\?v=|/watch\?.+&v=))([\w-]{11})(?:.+)?$#x';
                        preg_match($youtubePattern, $matches[0], $ytMatches);
                        if (isset($ytMatches[1])) {
                            return "<iframe width='700' height='500' src='https://www.youtube.com/embed/{$ytMatches[1]}' title='YouTube video player' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>";
                        } else {
                            return "<a href='{$matches[0]}' target='_blank'>{$matches[0]}</a>";
                        }
                    } else {
                        $img = Utils::resizeImage($matches[0], 300, 300);
                        return "<a href='{$matches[0]}' target='_blank'><img src='{$img}'/></a>";
                    }  
                }, $content);

                ?>
                <!-- Card with no padding with a content container nested inside of it -->
                <div class="w-800 mw-full"> <!-- w-400 = width: 40rem (400px), mw-full = max-width: 100% -->
                    <!-- Card with no padding with multiple content containers nested inside of it -->
                    <div class="w-800 mw-full"> <!-- w-600 = width: 60rem (600px), mw-full = max-width: 100% -->
                        <div class="card p-0"> <!-- p-0 = padding: 0 -->
                            <!-- First content container nested inside card -->
                            <div class="content">
                            <h2 class="content-title">
                                <?php echo $title; ?>
                            </h2>
                            <div>
                                <span class="text-muted">
                                    <i class="fa fa-clock-o mr-5" aria-hidden="true"></i> <?php echo $modified; ?> - <?php 
                                        $owner_name = $postConnection->getProfile($owner);
                                        $connection = new ClientData();
                                        $less = $connection->getUser($owner_name, null, true);

                                        $group_name = $less->getGroup();
                                        $priority = $less->getGroupPriority();

                                        $patreon = $connection->loadPatreon($owner_name);
                                        $patreonBadge = '';

                                        if ($patreon != null && !empty($patreon) && gettype($patreon) == 'array' && isset($patreon['included'])) {
                                            $patreonData = $patreon['included'];
                                    
                                            foreach ($patreonData as $includedData) {
                                                if (isset($includedData['attributes']) && isset($includedData['id'])) {
                                                    $id = $includedData['id'];
                                                    if ($id == 'd36adb45-1842-4fed-88a3-1ce77843bb99') {
                                                        $attributes = $includedData['attributes'];
                                                        
                                                        if ($attributes['patron_status'] == 'declined_patron') {
                                                            $patreonBadge = '<span class="badge badge-danger">Ex Patreon</span>  ';
                                                        } else {
                                                            $patreonBadge = '<span class="badge badge-secondary">Patreon</span>  ';
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $bdStyle = '';
                                        if ($priority > 0) {
                                            switch ($priority) {
                                                case 1:
                                                    $bdStyle = 'badge-success';
                                                    break;
                                                case 2:
                                                    $bdStyle = 'badge-primary';
                                                    break;
                                                case 3:
                                                    $bdStyle = 'badge-secondary';
                                                    break;
                                                case 4:
                                                default:
                                                    $bdStyle = 'badge-danger';
                                                    break;
                                            }
                                        }

                                        echo $patreonBadge . "<span style='padding: 0px 0px 0px 0px' class='badge {$bdStyle}'>{$group_name}</span> <a href='{$config->getHomePath()}profile/?id={$owner}'>{$owner_name}</a>";
                                    ?> <!-- mr-5 = margin-right: 0.5rem (5px) -->
                                </span>
                            </div>
                            <div>
                                <span class="badge">
                                    <i class="fa fa-comments text-primary mr-5" aria-hidden="true"></i> <?php echo sizeof($comments); ?> comments <!-- text-primary = color: primary-color, mr-5 = margin-right: 0.5rem (5px) -->
                                </span>
                                <span class="badge ml-5" id="likebutton"> <!-- ml-5 = margin-left: 0.5rem (5px) -->
                                    <i class="fa fa-heart text-danger mr-5" aria-hidden="true"></i> <span id="liketext"><?php echo sizeof($likes); ?> likes</span> <!-- text-danger = color: danger-color, mr-5 = margin-right: 0.5rem (5px) -->
                                </span>

                                <?php
                                    if (ClientData::isAuthenticated() && isset($post_id)) {
                                        ?>
                                        <script type="text/javascript">
                                            document.getElementById('likebutton').addEventListener('click', (event) => {
                                                requestTemporalAPIKey(function(api_key) {
                                                    data = new FormData()
                                                    data.set('method', 'post');
                                                    data.set('action', 'like');
                                                    data.set('api', api_key);
                                                    data.set('post', '<?php echo $post_id; ?>');
                                                    data.set('content', comment);

                                                    let request = new XMLHttpRequest();
                                                    request.onreadystatechange = (e) => {
                                                        if (request.readyState !== 4) {
                                                            return;
                                                        }

                                                        if (request.status == 200) {
                                                            document.location.reload(true);
                                                        }
                                                    }

                                                    var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                                    request.open("POST", host + 'api/', false);
                                                    request.send(data)
                                                });
                                            })
                                        </script>
                                        <?php
                                    }
                                ?>
                            </div>
                            </div>
                            <hr/>
                                <p style="margin: 5% 5% 5% 5%;">
                                    <?php echo $content; ?>
                                </p>
                            <hr/>
                            
                            <div class="content">
                                <h2 class="content-title">
                                    Comments
                                </h2>
                                <?php
                                    if (sizeof($comments) >= 1) {
                                        $comments = array_reverse($comments, true);

                                        if (isset($_GET['page'])) {
                                            $page = intval($_GET['page']);
                                        } else {
                                            $page = 1;
                                        }

                                        $page_size = 10;

                                        $total_records = count($comments);
                                        $total_pages   = ceil($total_records / $page_size);

                                        if ($page > $total_pages) {
                                            $page = $total_pages;
                                        }

                                        if ($page < 1) {
                                            $page = 1;
                                        }

                                        $offset = ($page - 1) * $page_size;

                                        $comments = array_slice($comments, $offset, $page_size);
                                        $index = 0;
                                        foreach ($comments as $id => $data) {
                                            foreach ($data as $c_owner => $c_data) {
                                                $c_text = $c_data['comment'];
                                                $c_owner_id = $c_data['user_id'];
                                                $c_creation = date('d/m/Y', $c_data['creation']);

                                                $patreon = $connection->loadPatreon($c_owner);

                                                if ($patreon != null && !empty($patreon) && gettype($patreon) == 'array' && isset($patreon['included'])) {
                                                    $patreonData = $patreon['included'];
                                            
                                                    foreach ($patreonData as $includedData) {
                                                        if (isset($includedData['attributes']) && isset($includedData['id'])) {
                                                            $id = $includedData['id'];
                                                            if ($id == 'd36adb45-1842-4fed-88a3-1ce77843bb99') {
                                                                $attributes = $includedData['attributes'];
                                                                
                                                                if ($attributes['patron_status'] != 'declined_patron') {
                                                                    $c_owner = '<span class="badge badge-secondary">'. $c_owner .'</span>  ';
                                                                }
                                                            }
                                                        }
                                                    }
                                                }

                                                echo "
                                                    <div>
                                                        <strong><a href='{$config->getHomePath()}profile/?id={$c_owner_id}'>{$c_owner}</a></strong> - $c_creation
                                                        <br/>
                                                        {$c_text}
                                                    </div>
                                                    <hr>
                                                ";
                                            }
                                        }

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
                                        <nav aria-label="...">
                                            <ul class="pagination pagination-sm">
                                            <li class="page-item'. ($page == 1 ? ' disabled"' : '" onclick="prev(\'page\')"') . '>
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
                                        </nav>';
                                    } else {
                                        echo "
                                        <div>
                                            <strong>Be the first comment!</strong>
                                            <br/>
                                            No comments have been made
                                        </div>";
                                    }

                                    if (ClientData::isAuthenticated()) {
                                        echo '
                                        <hr/>
                                        <form onSubmit="submitComment(\''.  $post_id .'\'); return false;" class="form-inline w-400 mw-full">
                                            <div class="form-group">
                                                <textarea type="textarea" maxlength="255" class="form-control" placeholder="Comment" id="comment" name="comment" required="required"></textarea>
                                            </div>
                                            <div class="form-group mb-0"> <!-- mb-0 = margin-bottom: 0 -->
                                                <input type="submit" class="btn btn-primary ml-auto" name="submit" value="Comment"> <!-- ml-auto = margin-left: auto -->
                                            </div>
                                        </form>
                                        ';
                                    } else {
                                        echo '
                                        <hr/>
                                        <form onSubmit="submitComment(\''.  $post_id .'\'); return false;" class="form-inline w-400 mw-full">
                                            <div class="form-group">
                                                <textarea readonly type="textarea" maxlength="255" class="form-control" placeholder="You must be logged in" id="comment" name="comment" required="required"></textarea>
                                            </div>
                                            <div class="form-group mb-0"> <!-- mb-0 = margin-bottom: 0 -->
                                                <input type="submit" class="btn btn-primary ml-auto" name="submit" value="Comment" disabled> <!-- ml-auto = margin-left: auto -->
                                            </div>
                                        </form>
                                        ';
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <script type="text/javascript">
                    Swal.fire({
                        'title': 'Error',
                        'text': 'You do not have permissions to view this post',
                        'icon': 'error',
                        'showCancelButton': true,
                        'confirmButtonText': 'Dismiss',
                        'cancelButtonText': 'Take me back'
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            alert(window.history.back());
                        }
                    })
                </script>
                <?php
            }
        } else {
            if (isset($post_id) && $post_id == 'new-post') {
                ClientData::performAction('creating new content');

                echo "<div class='card'>";
                ?>
                <form onSubmit="submitNewPost(); return false;" class="form-inline w-400 mw-full"> <!-- w-400 = width: 40rem (400px), mw-full = max-width: 100% -->
                    <label class="required w-100" for="new-post-title">Title</label> <!-- w-100 = width: 10rem (100px) -->
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="My amazing post" id="new-post-title" required="required" name="title">
                    </div>
                    <label class="required w-100" for="new-post-topic">Topic</label> <!-- w-100 = width: 10rem (100px) -->
                    <div class="form-group">
                        <select name='topic' class="form-control" id="new-post-topic">
                            <?php
                            $postConnection = new PostData();
                            $topics = $postConnection->getTopics();

                            foreach ($topics as $topicId => $topicData) {
                                $internal = $topicData['internal'];
                                $displayName = $topicData['display'];
                                $color = $topicData['color'];

                                ?>
                                <option value="<?php echo $internal; ?>"><?php echo $displayName; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <label class="required w-100" for="new-post-tags">Tags</label> <!-- w-100 = width: 10rem (100px) -->
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="my,post,tags" id="new-post-tags" required="required">
                    </div>
                    <label class="required w-100" for="new-post-content">Content</label> <!-- w-100 = width: 10rem (100px) -->
                    <div class="form-group mb-0"> <!-- mb-0 = margin-bottom: 0 -->
                        <textarea type="text" class="form-control" placeholder="My post content" id="new-post-content" required="required"></textarea>
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-primary ml-auto" value="Post" style="margin-top: 25px"> <!-- ml-auto = margin-left: auto -->
                    </div>
                </form>

                <script type="text/javascript">
                    function submitNewPost() {
                        requestTemporalAPIKey(function(api_key) {
                            data = new FormData()
                            data.set('method', 'post');
                            data.set('action', 'create');
                            data.set('api', api_key);
                            data.set('title', document.getElementById('new-post-title').value);
                            data.set('content', document.getElementById('new-post-content').value);
                            data.set('tags', document.getElementById('new-post-tags').value);
                            data.set('topic', document.getElementById('new-post-topic').value);

                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                            let request = new XMLHttpRequest();
                            request.onreadystatechange = (e) => {
                                console.info(request.responseText);

                                if (request.readyState !== 4) {
                                    return;
                                }

                                if (request.status == 200) {
                                    var json = JSON.parse(request.responseText);
                                    
                                    if (json['success']) {
                                        document.location.href = host + '?post=' + json['message'];
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

                            request.open("POST", host + 'api/', false);
                            request.send(data)
                        });
                    }
                </script>
                <?php
                echo "</div>";
            } else {
                $postConnection = new PostData();

                $query = null;
                if (isset($_GET['query'])) {
                    $query = $_GET['query'];
                    if (empty($query)) {
                        $query = null;
                    }
                }
                $searchTopic = null;
                if (isset($_GET['topic'])) {
                    $searchTopic = $_GET['topic'];
                }

                ?>
                <div class="d-flex">
                    <div class="flex-fill">
                        <div class="card">
                            <form class="form-inline d-none d-md-flex ml-auto" action="<?php echo $config->getHomePath(); ?>" method="GET">
                                <div class="form-group">
                                    <input type="text" style="display: none" name='page' value='<?php echo (isset($_GET['page']) ? $_GET['page'] : '0'); ?>'>
                                    <input type="text" class="form-control" placeholder="Post title / tags: / author: " name="query" id="search-query">
                                </div>
                                
                                <div class="form-group">
                                    <label for="search-topic">Topic: </label>
                                    <select name='topic' class="form-control" id="search-topic" onload="resizeSelect(this)">
                                        <?php
                                        $anyTopicSelected = strtolower($searchTopic) == 'any';
                                        $postConnection = new PostData();
                                        $topics = $postConnection->getTopics();

                                        foreach ($topics as $topicId => $topicData) {
                                            $internal = $topicData['internal'];
                                            $displayName = $topicData['display'];

                                            $selected = '';

                                            if ($searchTopic != null) {
                                                if (strtolower($searchTopic) == strtolower($internal)) {
                                                    $selected = 'selected';
                                                }
                                            }

                                            ?>
                                            <option value="<?php echo $internal; ?>" <?php echo $selected ?>><?php echo $displayName; ?></option>
                                            <?php
                                        }
                                        ?>
                                        <option value="any" <?php echo $anyTopicSelected ?>>Any topic</option>
                                    </select>
                                </div>

                                <button class="btn btn-primary" type="submit">Search</button>
                                <button class="btn btn-primary" type="button" onclick='home()'>Clear</button>

                                <script type='text/javascript'>
                                    function home() {
                                        var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';
                                        window.location.assign(host);
                                    }
                                </script>
                            </form>
                        </div>
                <?php

                echo "<div class='card'>";
                echo "<h2 class='card-title'>Posts</h2>";
                echo "<hr/>";
                
                if (isset($post_id) && $post_id == 'approve' && isset($cl) && $cl->hasPermission('approve_post')) {
                    $posts = $postConnection->getAllPosts($query);
                    $posts = array_reverse($posts, true);

                    $index = 0;

                    foreach ($posts as $postId => $postData) {
                        if ($postData['status']['code'] == Post::post_pending_approval()) {
                            $comments = $postData['comments'];
                            $comments = sizeof($comments);

                            $likes = $postData['likes'];
                            $likes = sizeof($likes);

                            $postOwner = $postData['owner'];
                            $postId = $postData['post'];
                            $title = $postData['title'];
                            $tags = $postData['tags'];

                            $topicInfo = $postData['topic'];

                            $displayTopic = $topicInfo['display'];
                            $internalTopic = $topicInfo['localizer'];
                            $colorTopic = $topicInfo['color'];

                            $rgb = Utils::hexToRGB($colorTopic);
                            $hsl = Utils::rgbToHSL($rgb);

                            if ($hsl->lightness > 150) {
                                $topicBadge = "<span class='badge' style='background-color: {$colorTopic}; color: black'>{$displayTopic}</span>";
                            } else {
                                $topicBadge = "<span class='badge' style='background-color: {$colorTopic}'>{$displayTopic}</span>";
                            }

                            if ($searchTopic == null || strtolower($searchTopic) == strtolower($internalTopic) || strtolower($searchTopic) == 'any') {
                                $txt = str_replace('[/lb]', '<br>', $postData['content']);
                                echo "
                                    <h2 style='font-size: 16px' class='card-title'>
                                        {$topicBadge} {$title}  -  [  {$comments} <i class='fa fa-comments text-primary mr-5' aria-hidden='true'></i> | {$likes} <i class='fa fa-heart text-danger mr-5' aria-hidden='true'></i>]
                                    </h2>
                                    <span class='badge'>{$tags}</span>
                                    <p style='font-size: 12px' id='{$postId}' class='text-muted previewText'>
                                        {$txt}
                                    </p>
                                    <div class='text-center'> <!-- text-right = text-align: right -->
                                        <a style='font-size: 12px' href='{$config->getHomePath()}?post={$postId}' class='btn'>View</a>
                                        <a style='font-size: 12px' class='btn' onclick='approve(\"{$postId}\")'>Aprove</a>
                                        <a style='font-size: 12px' class='btn' onclick='decline(\"{$postId}\")'>Decline</a>
                                    </div>
                                ";

                                if ($index++ != sizeof($posts) - 1) {
                                    echo "<hr/>";
                                }
                            }
                        }
                    }

                    ?>
                    <script type="text/javascript">
                        function approve(postId) {
                            requestTemporalAPIKey(function(api_key) {
                                data = new FormData()
                                data.set('method', 'post');
                                data.set('action', 'status');
                                data.set('api', api_key);
                                data.set('post', postId);
                                data.set('content', '<?php echo Post::post_active(); ?>');

                                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                let request = new XMLHttpRequest();
                                request.onreadystatechange = (e) => {
                                    if (request.readyState !== 4) {
                                        return;
                                    }

                                    if (request.status == 200) {
                                        var json = JSON.parse(request.responseText);
                                        
                                        if (json['success']) {
                                            Swal.fire({
                                                'title': 'Success',
                                                'text': 'The post ' + postId + ' has been approved',
                                                'icon': 'success',
                                                'showCancelButton': false,
                                                'confirmButtonText': 'Ok',
                                            }).then((result) => {
                                                document.location.href = host + "?post=" + postId;
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

                                request.open("POST", host + 'api/', false);
                                request.send(data)
                            });
                        }

                        function decline(postId) {
                            requestTemporalAPIKey(function(api_key) {
                                data = new FormData()
                                data.set('method', 'post');
                                data.set('action', 'status');
                                data.set('api', api_key);
                                data.set('post', postId);
                                data.set('content', '<?php echo Post::post_removed(); ?>');

                                var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                                let request = new XMLHttpRequest();
                                request.onreadystatechange = (e) => {
                                    if (request.readyState !== 4) {
                                        return;
                                    }

                                    if (request.status == 200) {
                                        var json = JSON.parse(request.responseText);
                                        
                                        if (json['success']) {
                                            Swal.fire({
                                                'title': 'Success',
                                                'text': 'The post ' + postId + ' has been declined',
                                                'icon': 'success',
                                                'showCancelButton': false,
                                                'confirmButtonText': 'Ok',
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

                                request.open("POST", host + 'api/', false);
                                request.send(data)
                            });
                            
                        }
                    </script>
                    <?php
                } else {
                    $posts = $postConnection->getActivePosts($query, false);
                    $posts = array_reverse($posts, true);

                    if (isset($_GET['page'])) {
                        $page = intval($_GET['page']);
                    } else {
                        $page = 1;
                    }
                    
                    $page_size = 6;
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
                        if ($postData['status']['code'] == Post::post_active()) {
                            $comments = $postData['comments'];
                            $comments = sizeof($comments);

                            $likes = $postData['likes'];
                            $likes = sizeof($likes);

                            $postOwner = $postData['owner'];
                            $postId = $postData['post'];
                            $title = $postData['title'];

                            $topicInfo = $postData['topic'];

                            $displayTopic = $topicInfo['display'];
                            $internalTopic = $topicInfo['localizer'];
                            $colorTopic = $topicInfo['color'];

                            $rgb = Utils::hexToRGB($colorTopic);
                            $hsl = Utils::rgbToHSL($rgb);

                            if ($hsl->lightness > 150) {
                                $topicBadge = "<span class='badge' style='background-color: {$colorTopic}; color: black; cursor: help'>{$displayTopic}</span>";
                            } else {
                                $topicBadge = "<span class='badge' style='background-color: {$colorTopic}; cursor: help'>{$displayTopic}</span>";
                            }

                            $txt = str_replace('[/lb]', '<br>', $postData['content']);
                            $size = count(preg_split('//u', $txt, -1, PREG_SPLIT_NO_EMPTY));
                            if ($size > 30) {
                                $txt = str_split($txt, 30)[0] . ' ...';
                            }

                            $tags = $postData['tags'];
                            $displayTags = "";
                            foreach (explode(',', $tags) as $tag) {
                                $displayTags = $displayTags . " <span class='badge' style='font-size: 12px; cursor: alias' onclick='goTags(\"{$tag}\")'>{$tag}</span>";
                            }

                            if ($searchTopic == null || strtolower($searchTopic) == strtolower($internalTopic) || strtolower($searchTopic) == 'any') {
                                echo "
                                <div style='cursor: pointer' id='preview_{$postId}' class='postPreview'>
                                    <h2 style='font-size: 16px' class='card-title'>
                                        <a onclick='goTopic(\"{$displayTopic}\")' style='color: inherit; cursor: pointer; text-decoration: inherit'>{$topicBadge}</a> <a onclick='goPost(\"{$postId}\")' style='color: inherit; text-decoration: none'>{$title}</a> <a style='color: inherit; cursor: default; text-decoration: inherit'>- {$displayTags}</a>
                                        <br>{$comments} <i class='fa fa-comments text-primary mr-5' aria-hidden='true'></i>  {$likes} <i class='fa fa-heart text-danger mr-5' aria-hidden='true'></i>
                                    </h2>
                                            
                                    <p style='font-size: 12px' id='{$postId}' class='text-muted previewText'>
                                        {$txt}
                                    </p>
                                </div>
                                ";

                                if ($index++ != sizeof($posts) - 1) {
                                    echo "<hr/>";
                                }
                            } else {
                                $total_records--;
                                $total_pages = ceil($total_records / $page_size);
                            }
                        } else {
                            $total_records--;
                            $total_pages   = ceil($total_records / $page_size);

                            if ($page > $total_pages) {
                                $page = $total_pages;
                            }

                            if ($page < 1) {
                                $page = 1;
                            }
                        }
                    }

                    ?>
                    <script type='text/javascript'>
                        function goPost(postId) {
                            var urlParams = new URLSearchParams(window.location.search);
                            var current_post = urlParams.get('post');

                            if (current_post) {
                                urlParams.set('post', postId);
                            } else {
                                urlParams.append('post', postId);
                            }

                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';
                            window.location = host + '?' + urlParams.toString();
                        }

                        function goTopic(topic) {
                            var urlParams = new URLSearchParams(window.location.search);
                            var current_topic = urlParams.get('topic');

                            if (current_topic) {
                                urlParams.set('topic', topic);
                            } else {
                                urlParams.append('topic', topic);
                            }

                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';
                            window.location = host + '?' + urlParams.toString();
                        }

                        function goTags(tag) {
                            var urlParams = new URLSearchParams(window.location.search);
                            var current_query = urlParams.get('query');
                            var current_topic = urlParams.get('topic');

                            if (current_query) {
                                urlParams.set('query', 'tags:' + tag);
                            } else {
                                urlParams.append('query', 'tags:' + tag);
                            }

                            if (!current_topic) {
                                urlParams.append('topic', 'any');
                            }

                            var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';
                            window.location = host + '?' + urlParams.toString();
                        }

                        const pP = document.getElementsByClassName('previewText');

                        for (var i = 0; i < pP.length; i++) {
                            const ppElement = pP[i];

                            ppElement.addEventListener('click', function(event) {
                                goPost(ppElement.id)
                            });
                        }
                    </script>
                    <?php

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
                        <div class="d-flex justify-content-end">
                            <nav aria-label="...">
                                <ul class="pagination pagination-sm">
                                <li class="page-item'. ($page == 1 ? ' disabled"' : '" onclick="prev(\'page\')"') . '>
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
                    
                    echo "</div>";
                }
            }

            echo "</div>";
            if ($showOnline) {
                ?>
                        <div class='card'>
                            <h2 class='card-title'>Online users</h2>
                            <hr>
                            <?php 
                                $online = Utils::getOnlineUsers();
                                $online = array_reverse($online, true);

                                if (isset($_GET['uPage'])) {
                                    $uPage = intval($_GET['uPage']);
                                } else {
                                    $uPage = 1;
                                }
                                
                                $uPage_size = 16;
                                $uTotal_records = count($online);
                                $uTotal_pages   = ceil($uTotal_records / $uPage_size);
                
                                if ($uPage > $uTotal_pages) {
                                    $uPage = $uTotal_pages;
                                }
                
                                if ($uPage < 1) {
                                    $uPage = 1;
                                }
                
                                $uOffset = ($uPage - 1) * $uPage_size;
                                $online = array_slice($online, $uOffset, $uPage_size);

                                foreach ($online as $fId => $userData) {
                                    echo "<div class='text-center'>";
                                    echo "  <a href='{$config->getHomePath()}profile/?id={$userData['id']}'><p>{$userData['name']}</p></href>";
                                    echo "</div>";
                                }
                                if (count($online) < 16) {
                                    for($i = count($online); $i < 16; $i++) {
                                        echo "<div class='text-center'>";
                                        echo "  <a href=''><p>&#8203</p></href>";
                                        echo "</div>";
                                    }
                                }

                                if (count($online) >= 1) {
                                    $N = min($uTotal_pages, 9);
                                    $uPages_links = array();
                
                                    $tmp = $N;
                                    if ($tmp < $uPage || $uPage > $N) {
                                        $tmp = 2;
                                    }
                                    for ($i = 1; $i <= $tmp; $i++) {
                                        $uPages_links[$i] = $i;
                                    }
                
                                    if ($uPage > $N && $uPage <= ($uTotal_pages - $N + 2)) {
                                        for ($i = $uPage - 3; $i <= $uPage + 3; $i++) {
                                            if ($i > 0 && $i < $uTotal_pages) {
                                                $uPages_links[$i] = $i;
                                            }
                                        }
                                    }
                
                                    $tmp = $uTotal_pages - $N + 1;
                                    if ($tmp > $uPage - 2) {
                                        $tmp = $uTotal_pages - 1;
                                    }
                                    for ($i = $tmp; $i <= $uTotal_pages; $i++) {
                                        if ($i > 0) {
                                            $uPages_links[$i] = $i;
                                        }
                                    }
                
                                    echo '
                                    <nav aria-label="...">
                                        <ul class="pagination pagination-sm">
                                        <li class="page-item'. ($page == 1 ? ' disabled"' : '" onclick="prev(\'uPage\')"') . '>
                                                <a href="#" class="page-link w-50" tabindex="-1"><</a>
                                            </li>
                                            ';

                                    foreach ($uPages_links as $p) {
                                        if ($p == $page) {
                                            echo '
                                            <li class="page-item active">
                                                <a href="#" class="page-link" tabindex="-1">'. $p .'</a>
                                            </li>';
                                        } else {
                                            $getBuilder = null;
                                            if ($p == 1) {
                                                foreach ($_GET as $key => $val) {
                                                    if ($key != 'uPage') {
                                                        if ($getBuilder == null) {
                                                            $getBuilder = '?' . $key . '=' . $val;
                                                        } else {
                                                            $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                                        }
                                                    }
                                                }
                                            } else {
                                                foreach ($_GET as $key => $val) {
                                                    if ($key != 'uPage') {
                                                        if ($getBuilder == null) {
                                                            $getBuilder = '?' . $key . '=' . $val;
                                                        } else {
                                                            $getBuilder = $getBuilder . '&' . $key . '=' .$val;
                                                        }
                                                    }
                                                }
                                            }

                                            if ($getBuilder == null) {
                                                $getBuilder = '?uPage=' . $p;
                                            } else {
                                                $getBuilder = $getBuilder . '&uPage=' . $p;
                                            }

                                            echo '
                                                <li class="page-item" aria-current="page"><a href="'. $getBuilder .'" class="page-link">'. $p .'</a></li>
                                            ';
                                        }
                                    }

                                    echo '
                                            <li class="page-item'. (count($uPages_links) == $page ? ' disabled"' : '" onclick="next(\'uPage\')"') . '>
                                                <a href="#" class="page-link w-50">></a>
                                            </li>
                                        </ul>
                                    </nav>';
                                }
                            ?>
                        </div>
                    </div>
                <?php
            }
        }
    }
    ?>

    <script type="text/javascript">
        function isString(variable) { 
            return typeof (variable) === 'string'; 
        }

        function replaceLastOccurrenceInString(input, find, replaceWith) {
            if (!isString(input) || !isString(find) || !isString(replaceWith)) {
                // returns input on invalid arguments
                return input;
            }

            const lastIndex = input.lastIndexOf(find);
            if (lastIndex < 0) {
                return input;
            }

            return input.substr(0, lastIndex) + replaceWith + input.substr(lastIndex + find.length);
        }

        function submitComment(postId) {
            var value = document.getElementById('comment').value;
            const tmpValue = value.replace(/\s+/g, "");
            if (!tmpValue) {
                Swal.fire({
                    'title': 'Error',
                    'text': 'The comment can\'t be empty and must be over 25 characters and less than 255 characters',
                    'icon': 'error',
                    'showCancelButton': false,
                    'confirmButtonText': 'Dismiss',
                })
            } else {
                value = value.replace('[/lb]', '\n').split('\n');
                var comment = '';
                value.forEach(element => {
                    comment = comment + element + '[/lb]';
                });

                comment = replaceLastOccurrenceInString(comment, '[/lb]', '');

                requestTemporalAPIKey(function(api_key) {
                    data = new FormData()
                    data.set('method', 'post');
                    data.set('action', 'comment');
                    data.set('api', api_key);
                    data.set('create_messages', true);
                    data.set('post', postId);
                    data.set('content', comment);

                    let request = new XMLHttpRequest();
                    request.onreadystatechange = (e) => {
                        if (request.readyState !== 4) {
                            return;
                        }

                        if (request.status == 200) {
                            document.location.reload(true);
                        }
                    }

                    var host = window.location.protocol + "//" + window.location.host + '<?php echo $config->getHomePath(); ?>';

                    request.open("POST", host + 'api/', false);
                    request.send(data)
                });
                
            }
        }

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