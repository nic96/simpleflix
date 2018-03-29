<?php
# FIXME prevent two scans from being able to run simultaneously.
$omdb_apikey = '';
$ffmpeg = '/usr/bin/ffmpeg';
$path_to_store_generated_thumbnail = '.video_thumbs';
$file_extensions = array("mp4", "avi", "mkv");
$useless_text = array("720p", "1080p", "x264", "BluRay", "HDTV");
$replace_text = array("Worlds" => "World's");
$second             = 60;
if(file_exists(".scan.php.lck")) {
    echo "Scan already running. If you are sure it's not running delete the lock file and try again.";
    exit();
} else {
    touch(".scan.php.lck");
}
$file_extensions = implode('|', $file_extensions);
foreach ($useless_text as &$text) {
    $text = preg_quote($text, '/');
}
$useless_text = implode('|', $useless_text);

$db = new PDO("sqlite:db.sqlite");
$db->setAttribute(PDO::ATTR_ERRMODE,
                  PDO::ERRMODE_EXCEPTION);
$db->query('PRAGMA foreign_keys = ON;');


$results = $db->query('SELECT * FROM media_file;');
$media_files = array();
while ($row = $results->fetch()) {
    if(!file_exists($row['location'])) {
        $db->query("DELETE FROM media_file WHERE id =".$row['id'].";");
    } else {
        array_push($media_files, $row);
    }
}
$di = new RecursiveDirectoryIterator('./Media');
foreach (new RecursiveIteratorIterator($di) as $filename => $file) {
    if(preg_match('/^.*\.('.$file_extensions.')$/i', $file)) {
        if (preg_match('/^\.\/Media\/Movies/i', $filename) && !preg_match('/S\d{2}E\d{2}/i', basename($filename))) {
            $media_type = "movie";
        } else if (preg_match('/^\.\/Media\/TV Shows/i', $filename) || preg_match('/S\d{2}E\d{2}/i', basename($filename))) {
            $media_type = "episode";
        } else {
            $media_type = "other";
        }

        $in_database = false;
        foreach($media_files as $media_file) {
            if($media_file['location'] == $filename) {
                $in_database = true;
                break;
            }
        }
        if($in_database == false) {
            $stmt = $db->prepare('INSERT INTO media_file (location, type) values (:location, :type);');
            $stmt->bindValue(':location', $filename);
            $stmt->bindValue(':type', $media_type);
            $stmt->execute();
            $media_file_id = $db->lastInsertId();
        } else {
            $stmt = $db->prepare('SELECT id FROM media_file WHERE location = :location;');
            $stmt->bindValue(':location', $filename);
            $stmt->execute();
            $media_file_id = $stmt->fetchColumn();
            if($media_type == "movie") {
                $stmt = $db->prepare('SELECT COUNT(*) FROM movie where media_file_id = :media_file_id;');
                $stmt->bindValue(':media_file_id', $media_file_id, PDO::PARAM_INT);
                $stmt->execute();
                if($stmt->fetchColumn() > 0) {
                    continue;
                }
            }
            if($media_type == "episode") {
                $stmt = $db->prepare('SELECT COUNT(*) FROM episode where media_file_id = :media_file_id;');
                $stmt->bindValue(':media_file_id', $media_file_id, PDO::PARAM_INT);
                $stmt->execute();
                if($stmt->fetchColumn() > 0) {
                    continue;
                }
            }
        }

        // removes extension
        $search_string = preg_replace('/\.('.$file_extensions.')$/i', '', basename($filename));
        // removes useless text
        $search_string = preg_replace('/('.$useless_text.')/i', '', $search_string);
        // remove periods and dashes
        $search_string = preg_replace('/[\.\-]/', ' ', $search_string);
        // trim spaces
        $search_string = trim($search_string, " ");
        // create thumbnail directory
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);
        if(!file_exists("$path_to_store_generated_thumbnail/$dirname"))
            mkdir("$path_to_store_generated_thumbnail/$dirname", 0750, true);
        if($media_type == "movie") {
            // get movie info
            $movie_info = json_decode(file_get_contents("http://www.omdbapi.com/?apikey=$omdb_apikey&t=".urlencode($search_string)));

            if($movie_info->{'Response'} == "False") {
                // removes year
                $search_string = preg_replace('/(18|19|20)\d{2}/', '', $search_string);
                // trim spaces
                $search_string = trim($search_string, " ");
                $movie_info = json_decode(file_get_contents("http://www.omdbapi.com/?apikey=$omdb_apikey&t=".urlencode($search_string)));
            }
            if($movie_info->{"Response"} == "False") {
                foreach($replace_text as $search => $replace) {
                    preg_quote($search, "/");
                    $search_string = preg_replace("/$search/i", $replace, $search_string);
                    $movie_info = json_decode(file_get_contents("http://www.omdbapi.com/?apikey=$omdb_apikey&t=".urlencode($search_string)));
                }
            }

            if($movie_info->{'Response'} == "True") {
                file_put_contents("$path_to_store_generated_thumbnail/$filename.jpg", fopen($movie_info->{'Poster'}, 'r'));
                $stmt = $db->prepare('INSERT INTO movie (title, year, rated, released, runtime, genre, director, writer, actors, plot, language, country, awards, poster, imdb_id, production, website, media_file_id) values (:title, :year, :rated, :released, :runtime, :genre, :director, :writer, :actors, :plot, :language, :country, :awards, :poster, :imdb_id, :production, :website, :media_file_id);');
                $stmt->bindValue(':title', $movie_info->{'Title'});
                $stmt->bindValue(':year', $movie_info->{'Year'});
                $stmt->bindValue(':rated', $movie_info->{'Rated'});
                $stmt->bindValue(':released', $movie_info->{'Released'});
                $stmt->bindValue(':runtime', $movie_info->{'Runtime'});
                $stmt->bindValue(':genre', $movie_info->{'Genre'});
                $stmt->bindValue(':director', $movie_info->{'Director'});
                $stmt->bindValue(':writer', $movie_info->{'Writer'});
                $stmt->bindValue(':actors', $movie_info->{'Actors'});
                $stmt->bindValue(':plot', $movie_info->{'Plot'});
                $stmt->bindValue(':language', $movie_info->{'Language'});
                $stmt->bindValue(':country', $movie_info->{'Country'});
                $stmt->bindValue(':awards', $movie_info->{'Awards'});
                $stmt->bindValue(':poster', "$path_to_store_generated_thumbnail/$filename.jpg");
                $stmt->bindValue(':imdb_id', $movie_info->{'imdbID'});
                $stmt->bindValue(':production', $movie_info->{'Production'});
                $stmt->bindValue(':website', $movie_info->{'Website'});
                $stmt->bindValue(':media_file_id', $media_file_id);
                $stmt->execute();
                $movie_id = $db->lastInsertId();
                foreach($movie_info->{'Ratings'} as $rating) {
                    $stmt = $db->prepare('INSERT INTO movie_ratings (source, value, movie_id) values (:source, :value, :movie_id);');
                    $stmt->bindValue(':source', $rating->{'Source'});
                    $stmt->bindValue(':value', $rating->{'Value'});
                    $stmt->bindValue(':movie_id', $movie_id);
                    $stmt->execute();
                }
            }
        } else if($media_type == "episode") {
            $episode_string = preg_replace('/^.*(S\d{2}E\d{2}).*$/i', '$1', $search_string);
            $series = preg_replace("/$episode_string/", '', $search_string);
            $season = preg_replace('/^S(\d{2})E\d{2}$/i', '$1', $episode_string);
            $episode = preg_replace('/^S\d{2}E(\d{2})$/i', '$1', $episode_string);

            // get series info
            $series_info = json_decode(file_get_contents("http://www.omdbapi.com/?apikey=$omdb_apikey&t=".urlencode($series)));

            if($series_info->{'Response'} == "False") {
                // removes year
                $search_string = preg_replace('/(18|19|20)\d{2}/', '', $search_string);
                // trim spaces
                $series = trim($series, " ");
                $series_info = json_decode(file_get_contents("http://www.omdbapi.com/?apikey=$omdb_apikey&t=".urlencode($series)));
            }
            if($series_info->{'Response'} == "False") {
                foreach($replace_text as $search => $replace) {
                    preg_quote($search, "/");
                    $search_string = preg_replace("/$search/i", $replace, $search_string);
                    $search_string = trim($search_string, " ");
                    $series_info = json_decode(file_get_contents("http://www.omdbapi.com/?apikey=$omdb_apikey&t=".urlencode($search_string)));
                }
            }

            if($series_info->{'Response'} == "True") {
                if(!file_exists("$path_to_store_generated_thumbnail/series"))
                    mkdir("$path_to_store_generated_thumbnail/series", 0750, true);
                if(!file_exists("$path_to_store_generated_thumbnail/series/".$series_info->{'Title'}.".jpg")) {
                    file_put_contents("$path_to_store_generated_thumbnail/series/".$series_info->{'Title'}.".jpg", fopen($series_info->{'Poster'}, 'r'));
                }

                $stmt = $db->prepare('SELECT id, COUNT(*) as count FROM series where title = :title and year = :year and imdb_id = :imdb_id;');
                $stmt->bindValue(':title', $series_info->{'Title'});
                $stmt->bindValue(':year', $series_info->{'Year'});
                $stmt->bindValue(':imdb_id', $series_info->{'imdbID'});
                $stmt->execute();
                $series_db = $stmt->fetch();
                $series_id = $series_db['id'];
                if($series_db['count'] < 1) {
                    $stmt = $db->prepare('INSERT INTO series (title, year, rated, released, runtime, genre, director, writer, actors, plot, language, country, awards, poster, imdb_id, total_seasons, production, website) values (:title, :year, :rated, :released, :runtime, :genre, :director, :writer, :actors, :plot, :language, :country, :awards, :poster, :imdb_id, :total_seasons, :production, :website);');
                    $stmt->bindValue(':title', $series_info->{'Title'});
                    $stmt->bindValue(':year', $series_info->{'Year'});
                    $stmt->bindValue(':rated', $series_info->{'Rated'});
                    $stmt->bindValue(':released', $series_info->{'Released'});
                    $stmt->bindValue(':runtime', $series_info->{'Runtime'});
                    $stmt->bindValue(':genre', $series_info->{'Genre'});
                    $stmt->bindValue(':director', $series_info->{'Director'});
                    $stmt->bindValue(':writer', $series_info->{'Writer'});
                    $stmt->bindValue(':actors', $series_info->{'Actors'});
                    $stmt->bindValue(':plot', $series_info->{'Plot'});
                    $stmt->bindValue(':language', $series_info->{'Language'});
                    $stmt->bindValue(':country', $series_info->{'Country'});
                    $stmt->bindValue(':awards', $series_info->{'Awards'});
                    $stmt->bindValue(':poster', "$path_to_store_generated_thumbnail/series/".$series_info->{'Title'}.".jpg");
                    $stmt->bindValue(':imdb_id', $series_info->{'imdbID'});
                    $stmt->bindValue(':total_seasons', $series_info->{'totalSeasons'});
                    $stmt->bindValue(':production', isset($series_info->{'Production'}) ? $series_info->{'Production'} : "N/A");
                    $stmt->bindValue(':website', isset($series_info->{'Website'}) ? $series_info->{'Website'} : "N/A");
                    $stmt->execute();
                    $series_id = $db->lastInsertId();
                    foreach($series_info->{'Ratings'} as $rating) {
                        $stmt = $db->prepare('INSERT INTO series_ratings (source, value, series_id) values (:source, :value, :series_id);');
                        $stmt->bindValue(':source', $rating->{'Source'});
                        $stmt->bindValue(':value', $rating->{'Value'});
                        $stmt->bindValue(':series_id', $series_id);
                        $stmt->execute();
                    }
                }
                $episode_info = json_decode(file_get_contents("http://www.omdbapi.com/?apikey=$omdb_apikey&t=".urlencode($series)."&season=$season&episode=$episode"));
                $stmt = $db->prepare('SELECT COUNT(*) FROM episode where series_id = :series_id and season = :season and episode = :episode;');
                $stmt->bindValue(':series_id', $series_id);
                $stmt->bindValue(':season', $episode_info->{'Season'});
                $stmt->bindValue(':episode', $episode_info->{'Episode'});
                $stmt->execute();
                if($stmt->fetchColumn() == 0) {
                    $stmt = $db->prepare('INSERT INTO episode (title, year, rated, released, season, episode, runtime, genre, director, writer, actors, plot, language, country, awards, poster, imdb_id, production, website, media_file_id, series_id) values (:title, :year, :rated, :released, :season, :episode, :runtime, :genre, :director, :writer, :actors, :plot, :language, :country, :awards, :poster, :imdb_id, :production, :website, :media_file_id, :series_id);');
                    $stmt->bindValue(':title', $episode_info->{'Title'});
                    $stmt->bindValue(':year', $episode_info->{'Year'});
                    $stmt->bindValue(':rated', $episode_info->{'Rated'});
                    $stmt->bindValue(':released', $episode_info->{'Released'});
                    $stmt->bindValue(':season', $episode_info->{'Season'});
                    $stmt->bindValue(':episode', $episode_info->{'Episode'});
                    $stmt->bindValue(':runtime', $episode_info->{'Runtime'});
                    $stmt->bindValue(':genre', $episode_info->{'Genre'});
                    $stmt->bindValue(':director', $episode_info->{'Director'});
                    $stmt->bindValue(':writer', $episode_info->{'Writer'});
                    $stmt->bindValue(':actors', $episode_info->{'Actors'});
                    $stmt->bindValue(':plot', $episode_info->{'Plot'});
                    $stmt->bindValue(':language', $episode_info->{'Language'});
                    $stmt->bindValue(':country', $episode_info->{'Country'});
                    $stmt->bindValue(':awards', $episode_info->{'Awards'});
                    $stmt->bindValue(':poster', "N/A");
                    $stmt->bindValue(':imdb_id', $episode_info->{'imdbID'});
                    $stmt->bindValue(':production', isset($episode_info->{'Production'}) ? $episode_info->{'Production'} : "N/A");
                    $stmt->bindValue(':website', isset($episode_info->{'Website'}) ? $episode_info->{'Website'} : "N/A");
                    $stmt->bindValue(':media_file_id', $media_file_id);
                    $stmt->bindValue(':series_id', $series_id);
                    $stmt->execute();
                    $episode_id = $db->lastInsertId();
                    foreach($episode_info->{'Ratings'} as $rating) {
                        $stmt = $db->prepare('INSERT INTO episode_ratings (source, value, episode_id) values (:source, :value, :episode_id);');
                        $stmt->bindValue(':source', $rating->{'Source'});
                        $stmt->bindValue(':value', $rating->{'Value'});
                        $stmt->bindValue(':episode_id', $episode_id);
                        $stmt->execute();
                    }
                }
            }
        }


        if(!file_exists("$path_to_store_generated_thumbnail/$filename.jpg")) {
            if(!file_exists("$path_to_store_generated_thumbnail/$dirname"))
                mkdir("$path_to_store_generated_thumbnail/$dirname", 0750, true);
            $cmd = "{$ffmpeg} -ss {$second} -i '{$filename}' -vf 'scale=200:298:force_original_aspect_ratio=decrease,pad=200:298:(ow-iw)/2:(oh-ih)/2' -vframes 1 -deinterlace -an '{$path_to_store_generated_thumbnail}/{$filename}.jpg' 2>&1";
            exec($cmd, $output, $retval);
        }
    }
}
unlink(".scan.php.lck");
echo "Success!";

?>
