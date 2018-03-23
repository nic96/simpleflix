<!DOCTYPE html>
<?php

$ffmpeg = '/usr/bin/ffmpeg';
$path_to_store_generated_thumbnail = '.video_thumbs';
$video_files = array("mp4", "MP4", "avi", "AVI", "mkv", "MKV");
$second             = 1;

?>
<html>
  <head>
    <title>SimpleFlix</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    body {
        background-color: #232629;
    }
    ul {
        list-style-type: none;
    }
    ul li a {
        float: left;
        background-color: #31363b;
        padding: 10px;
        margin: 3px;
        border-radius: 5px;
        border: 1px solid #7f7f7f;
    }
    </style>
  </head>
  <body>
    <article>
    <ul>
<?php

$video_files = implode('|', $video_files);
$di = new RecursiveDirectoryIterator('.');
foreach (new RecursiveIteratorIterator($di) as $filename => $file) {
    if(preg_match('/^.*\.('.$video_files.')$/i', $file)) {
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);
        if(!file_exists("$path_to_store_generated_thumbnail/$filename.jpg")) {
            mkdir("$path_to_store_generated_thumbnail/$dirname", 0750, true);
            $cmd = "{$ffmpeg} -i {$filename} -vf \"scale=200:298:force_original_aspect_ratio=decrease,pad=200:298:(ow-iw)/2:(oh-ih)/2\" -vframes 1 -deinterlace -an -ss {$second} {$path_to_store_generated_thumbnail}/{$filename}.jpg 2>&1";
            exec($cmd, $output, $retval);
        }
        echo '<li>';
        echo "<a href='$filename'><img src='$path_to_store_generated_thumbnail/$filename.jpg' /></a>";
        echo '</li>';
    }
}

?>
    </ul>
    </article>
  </body>
</html>
