<!DOCTYPE html>
<?php
$db = new PDO("sqlite:db.sqlite");
$db->setAttribute(PDO::ATTR_ERRMODE,
                  PDO::ERRMODE_EXCEPTION);
$db->query('PRAGMA foreign_keys = ON;');
function navLink($link, $name) {
    $matchString = preg_quote($_SERVER['REQUEST_URI'], "/");
    $class = "";
    if (preg_match( "/^\.?\/?$matchString\/?$/", $link)) {
        $class = "class='currPage'";
    }
    return "<li><a href='$link' $class>$name</a></li>";
}
?>
<html>
  <head>
    <title>SimpleFlix</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="css/style.css">
  </head>
  <body>
    <div class="topBar">
        <img onclick="toggleLeftPanel()" src="img/menu.svg"></img>
    </div>
    <div class="leftPanel" id="leftPanel">
      <ul>
        <li>
          <a class="navbrand" href="./"><b>SimpleFlix</b></a>
        </li>
        <?=navLink("./", "Movies")?>
        <?=navLink("./?list=series", "TV Shows")?>
      </ul>
      <ul class="bottom">
        <li>
          <button onclick="scan()" class="btn">Scan Media</button>
        </li>
      </ul>
    </div>
    <div onclick="toggleLeftPanel()" class="coverArticle"></div>
    <article>
<?php
if(isset($_GET['list']) ? $_GET['list'] : "" == "series") {
    $results = $db->query('SELECT id, poster FROM series;');
    echo "<ul class='grid'>\n";
    while ($row = $results->fetch()) {
        echo "<li><a href='?series_id=".$row['id']."'><img src='".$row['poster']."' /></a></li>\n";
    }
    echo "</ul>\n";
} else if(!empty($_GET['series_id'])) {
    if(preg_match('/^\d+$/', $_GET['series_id'])) {

        $stmt = $db->prepare('SELECT id, title, year, rated, released, runtime, genre, director, writer, actors, plot, language, country, awards, poster, imdb_id, total_seasons, production, website from series where id = :id;');
        $stmt->bindValue(':id', $_GET['series_id'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(); ?>
        <ul id="series_info">
          <li id="series_poster">
            <img src="<?=$row['poster']?>" />
          </li>
          <li id="series_stats">
            <h3><?=$row['title']?></h3>
            <ul>
              <li>
                <small><?=$row['year']?></small>
              </li>
              <li>
                <small><?=$row['runtime']?></small>
              </li>
              <li>
                <small><?=$row['genre']?></small>
              </li>
              <li>
                <small><a href="http://www.imdb.com/title/<?=$row['imdb_id']?>/"><img src="img/imdb.svg" /></a></small>
              </li>
              <li>
              <?php
              $stmt = $db->prepare('SELECT value FROM series_ratings where series_id = :series_id and source = "Internet Movie Database"');
              $stmt->bindValue(':series_id', $_GET['series_id'], PDO::PARAM_INT);
              $stmt->execute();
              ?>
                <small><?=$stmt->fetchColumn()?></small>
              </li>
            </ul>
            <p>
            <?=$row['plot']?>
            </p>
          </li>
          <hr style="float:left;width:100%;box-sizing:border-box;" />
          <?php
            $stmt = $db->prepare('SELECT DISTINCT season from episode where series_id = :series_id ORDER BY season ASC');
            $stmt->bindValue(':series_id', $_GET['series_id'], PDO::PARAM_INT);
            $stmt->execute();
            while ($season = $stmt->fetch()) {
                echo "<li class='season'>Season ".$season['season']."\n";
                $stmt1 = $db->prepare('SELECT id, title, episode FROM episode WHERE series_id = :series_id AND season = :season ORDER BY episode ASC');
                $stmt1->bindValue(':series_id', $_GET['series_id'], PDO::PARAM_INT);
                $stmt1->bindValue(':season',$season['season'], PDO::PARAM_INT);
                $stmt1->execute();
                echo "<ul>\n";
                while ($episode = $stmt1->fetch()) {
                    echo "<li class='episode'><a href='./?episode_id=".$episode['id']."'><div>".$episode['episode']."</div><div>".$episode['title']."</div></a></li>\n";
                }
                echo "</ul>\n";
                echo "</li>\n";
            }
          ?>
        </ul>
    <?php } else {
        echo "<p>invalid series_id given.</p>\n";
    }
} else if(!empty($_GET['movie_id']) || !empty($_GET['episode_id'])) {
    if(!empty($_GET['movie_id'])) {
      $media_type="movie";
    } else {
      $media_type="episode";
    }
    if (preg_match('/^\d+$/', empty($_GET['movie_id']) ? "" : $_GET['movie_id']) ||
        preg_match('/^\d+$/', empty($_GET['episode_id']) ? "" : $_GET['episode_id'])) {
        if($media_type == "movie") {
            $stmt = $db->prepare('SELECT movie.id as id, title, year, rated, released, runtime, genre, director, writer, actors, plot, language, country, awards, poster, imdb_id, production, website, media_file_id, location from movie JOIN media_file ON movie.media_file_id = media_file.id where movie.id = :id;');
            $stmt->bindValue(':id', $_GET['movie_id'], PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare('SELECT episode.id as id, title, year, rated, released, season, episode, runtime, genre, director, writer, actors, plot, language, country, awards, poster, imdb_id, production, website, media_file_id, location from episode JOIN media_file ON episode.media_file_id = media_file.id where episode.id = :id;');
            $stmt->bindValue(':id', $_GET['episode_id'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(); ?>
        <ul class="video">
          <li>
          <video controls>
            <source src="<?=$row['location']?>">
            Your browser does not support the video tag.
          </video>
            <ul>
              <li>
                <a href="<?=$row['location']?>" download>Download</a>
              </li>
              <li class="ratings">
                <ul>
                <?php
                    $stmt = $db->prepare('SELECT source, value FROM movie_ratings where movie_id = :movie_id');
                    $stmt->bindValue(':movie_id', $row['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    while ($ratings_row = $stmt->fetch()) {
                        echo "<li class='rating'><b>".$ratings_row['source'].":</b> ".$ratings_row['value']."</li>";
                    }
                ?>
                </ul>
              </li>
            </ul>
          </li>
          <li>
            <h2><?=$row['title']?></h2>
          </li>
          <li>
            <p><?=$row['plot']?></p>
          </li>
        </ul>
        <table class="videoStats">
          <tr>
            <th>Title</th>
            <td><?=$row['title']?></td>
          </tr>
          <tr>
            <th>Year</th>
            <td><?=$row['year']?></td>
          </tr>
          <tr>
            <th>Rated</th>
            <td><?=$row['rated']?></td>
          </tr>
          <tr>
            <th>Released</th>
            <td><?=$row['released']?></td>
          </tr>
          <?php if($media_type == "episode") { ?>
          <tr>
            <th>Season</th>
            <td><?=$row['season']?></td>
          </tr>
          <tr>
            <th>Episode</th>
            <td><?=$row['episode']?></td>
          </tr>
          <?php } ?>
          <tr>
            <th>Runtime</th>
            <td><?=$row['runtime']?></td>
          </tr>
          <tr>
            <th>Genre</th>
            <td><?=$row['genre']?></td>
          </tr>
          <tr>
            <th>Director</th>
            <td><?=$row['director']?></td>
          </tr>
          <tr>
            <th>Writer</th>
            <td><?=$row['writer']?></td>
          </tr>
          <tr>
            <th>Actors</th>
            <td><?=$row['actors']?></td>
          </tr>
          <tr>
            <th>Language</th>
            <td><?=$row['language']?></td>
          </tr>
          <tr>
            <th>Country</th>
            <td><?=$row['country']?></td>
          </tr>
          <tr>
            <th>Awards</th>
            <td><?=$row['awards']?></td>
          </tr>
          <tr>
            <th>IMDB ID</th>
            <td><?=$row['imdb_id']?></td>
          </tr>
          <tr>
            <th>Production</th>
            <td><?=$row['production']?></td>
          </tr>
          <tr>
            <th>Website</th>
            <td>
            <?php if(preg_match( '/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i', $row['website'])) { ?>
              <a href="<?=$row['website']?>"><?=$row['website']?></a>
            <?php } else { ?>
              <?=$row['website']?>
            <?php } ?>
            </td>
          </tr>
        </table>
        <?php
    } else {
        echo "<p>invalid movie_id or episode_id given.</p>\n";
    }
} else {
    $results = $db->query('SELECT id, poster FROM movie;');
    echo "<ul class='grid'>\n";
    while ($row = $results->fetch()) {
        echo "<li><a href='?movie_id=".$row['id']."'><img src=\"".$row['poster']."\" /></a></li>\n";
    }
    echo "</ul>\n";
}

?>
    </ul>
    </article>
    <div id="scanStatusWrapper" class="hidden"><pre id="scanStatus"></pre></div>
    <script>
    function toggleLeftPanel() {
        document.getElementById("leftPanel").classList.toggle("unhide");
    }
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    async function scan() {
        var scanStatusWrapper = document.getElementById("scanStatusWrapper");
        var scanStatus = document.getElementById("scanStatus");
        scanStatusWrapper.classList.remove("hidden");
        scanStatus.innerHTML = 'Scanning Media Files...';
        xmlhttp=new XMLHttpRequest();
        xmlhttp.open("GET", "/scan.php", false);
        xmlhttp.send();
        var status = xmlhttp.responseText;
        await sleep(1000);
        scanStatus.innerHTML = status;
        await sleep(2000);
        scanStatusWrapper.classList.add("hidden");
        await sleep(500);
        location.reload();
    }
    </script>
  </body>
</html>
