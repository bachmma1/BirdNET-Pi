<?php

/* Prevent XSS input */
$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

ini_set('user_agent', 'BirdNET-Pi/1.0');
error_reporting(E_ERROR);
ini_set('display_errors', 0);
require_once 'scripts/common.php';
$home = get_home();

$result = fetch_species_array($_GET['sort']);

if(!file_exists($home."/BirdNET-Pi/scripts/disk_check_exclude.txt") || strpos(file_get_contents($home."/BirdNET-Pi/scripts/disk_check_exclude.txt"),"##start") === false) {
  file_put_contents($home."/BirdNET-Pi/scripts/disk_check_exclude.txt", "");
  file_put_contents($home."/BirdNET-Pi/scripts/disk_check_exclude.txt", "##start\n##end\n");
}

if (get_included_files()[0] === __FILE__) {
  echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BirdNET-Pi DB</title>
</head>';
}
?>

<div class="stats">
<div class="column">
<div style="width: auto;
   text-align: center">
   <form action="views.php" method="GET">
    <input type="hidden" name="sort" value="<?php if(isset($_GET['sort'])){echo $_GET['sort'];}?>">
      <input type="hidden" name="view" value="Species Stats">
      <button <?php if(!isset($_GET['sort']) || $_GET['sort'] == "alphabetical"){ echo "class='sortbutton active'";} else { echo "class='sortbutton'"; }?> type="submit" name="sort" value="alphabetical">
         <img src="images/sort_abc.svg" title="Sort by alphabetical" alt="Sort by alphabetical">
      </button>
      <button <?php if(isset($_GET['sort']) && $_GET['sort'] == "occurrences"){ echo "class='sortbutton active'";} else { echo "class='sortbutton'"; }?> type="submit" name="sort" value="occurrences">
         <img src="images/sort_occ.svg" title="Sort by occurrences" alt="Sort by occurrences">
      </button>
      <button <?php if(isset($_GET['sort']) && $_GET['sort'] == "confidence"){ echo "class='sortbutton active'";} else { echo "class='sortbutton'"; }?> type="submit" name="sort" value="confidence">
         <img src="images/sort_conf.svg" title="Sort by confidence" alt="Sort by confidence">
      </button>
      <button <?php if(isset($_GET['sort']) && $_GET['sort'] == "date"){ echo "class='sortbutton active'";} else { echo "class='sortbutton'"; }?> type="submit" name="sort" value="date">
         <img src="images/sort_date.svg" title="Sort by date" alt="Sort by date">
      </button>
   </form>
</div>
<br>
<form action="views.php" method="GET">
<input type="hidden" name="sort" value="<?php if(isset($_GET['sort'])){echo $_GET['sort'];}?>">
<input type="hidden" name="view" value="Species Stats">
<table>
  <?php
  $birds = array();
  $values = array();

  while($results=$result->fetchArray(SQLITE3_ASSOC))
  {
    $comname = preg_replace('/ /', '_', $results['Com_Name']);
    $comname = preg_replace('/\'/', '', $comname);
    $filename = "/By_Date/".$results['Date']."/".$comname."/".$results['File_Name'];
    $birds[] = $results['Com_Name'];
    $values[] = get_label($results, $_GET['sort']);
  }

  if(count($birds) > 45) {
    $num_cols = 3;
  } else {
    $num_cols = 1;
  }
  $num_rows = ceil(count($birds) / $num_cols);

  for ($row = 0; $row < $num_rows; $row++) {
    echo "<tr>";

    for ($col = 0; $col < $num_cols; $col++) {
      $index = $row + $col * $num_rows;

      if ($index < count($birds)) {
        ?>
        <td>
            <button type="submit" name="species" value="<?php echo $birds[$index];?>"><?php echo $values[$index];?></button>
        </td>
        <?php
      } else {
        echo "<td></td>";
      }
    }

    echo "</tr>";
  }
  ?>
</table>
</form>
</div>
<dialog style="margin-top: 5px;max-height: 95vh;
  overflow-y: auto;overscroll-behavior:contain" id="attribution-dialog">
  <h1 id="modalHeading"></h1>
  <p id="modalText"></p>
  <button style="font-weight:bold;color:blue" onclick="hideDialog()">Close</button>
  <button style="font-weight:bold;color:blue" onclick="if(confirm('Are you sure you want to blacklist this image?')) { blacklistImage(); }">Blacklist this image</button>
</dialog>
<script src="static/dialog-polyfill.js"></script>
<script>
var dialog = document.querySelector('dialog');
dialogPolyfill.registerDialog(dialog);

function showDialog() {
  document.getElementById('attribution-dialog').showModal();
}

function hideDialog() {
  document.getElementById('attribution-dialog').close();
}

function blacklistImage() {
    const match = last_photo_link.match(/\d+$/); // match one or more digits
    const result = match ? match[0] : null; // extract the first match or return null if no match is found
    console.log(last_photo_link)
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
      if(this.responseText.length > 0) {
       location.reload();
      }
    }
    xhttp.open("GET", "overview.php?blacklistimage="+result, true);
    xhttp.send();

}

function setModalText(iter, title, text, authorlink, photolink, licenseurl) {
    document.getElementById('modalHeading').innerHTML = "Photo: \""+decodeURIComponent(title.replaceAll("+"," "))+"\" Attribution";
    document.getElementById('modalText').innerHTML = "<div><img style='border-radius:5px;max-height: calc(100vh - 15rem);display: block;margin: 0 auto;' src='"+photolink+"'></div><br><div style='white-space:nowrap'>Image link: <a target='_blank' href="+text+">"+text+"</a><br>Author link: <a target='_blank' href="+authorlink+">"+authorlink+"</a><br>License URL: <a href="+licenseurl+" target='_blank'>"+licenseurl+"</a></div>";
    last_photo_link = text;
    showDialog();
}
</script>  
<div class="column center">
<?php if(!isset($_GET['species'])){
?><p class="centered">Choose a species to load images from Wikipedia.</p><?php
};?>
<?php if(isset($_GET['species'])) {
  $species = $_GET['species'];
  $iter=0;
  $config = get_config();
  $result3 = fetch_best_detection(htmlspecialchars_decode($_GET['species'], ENT_QUOTES));
  while($results=$result3->fetchArray(SQLITE3_ASSOC)){
    $count = $results['COUNT(*)'];
    $maxconf = round((float)round($results['MAX(Confidence)'],2) * 100 ) . '%';
    $date = $results['Date'];
    $time = $results['Time'];
    $name = $results['Com_Name'];
    $sciname = $results['Sci_Name'];
    $dbsciname = preg_replace('/ /', '_', $sciname);
    $comname = preg_replace('/ /', '_', $results['Com_Name']);
    $comname = preg_replace('/\'/', '', $comname);
    $linkname = preg_replace('/_/', '+', $dbsciname);
    $filename = "/By_Date/".$date."/".$comname."/".$results['File_Name'];
    $engname = get_com_en_name($sciname);

    $info_url = get_info_url($results['Sci_Name']);
    $url = $info_url['URL'];
    $url_title = $info_url['TITLE'];

    // Always use Wikipedia images
    $wiki = new WikipediaImages();
    $wiki_image = $wiki->get_image($sciname);

    if (!empty($wiki_image['image_url'])) {
      $iter++;
      $wikiTitle = $wiki_image['title'];
      $modaltext = $wiki_image['page_url'];
      $authorlink = $wiki_image['page_url'];
      $licenselink = $wiki_image['page_url'];
      $imageurl = $wiki_image['image_url'];
    }

    echo "<h3>$species</h3>";
    echo "<table>";
    echo "<tr>";
    echo "<td class=\"relative\">";
    echo "<a target=\"_blank\" href=\"index.php?filename=".$results['File_Name']."\">";
    echo "<img title=\"Open in new tab\" class=\"copyimage\" width=25 src=\"images/copy.png\">";
    echo "</a>";
    echo "<div class=\"centered_image_container\">";
      if (!empty($imageurl)) {
        echo "<img style='vertical-align:top' onclick='setModalText(".$iter.",\"".$wikiTitle."\",\"".$modaltext."\", \"".$authorlink."\", \"".$imageurl."\", \"".$licenselink."\")' src=\"$imageurl\" class=\"img1\">";
      }
      echo "<i>$sciname</i>";
      echo "<a href=\"$url\" target=\"_blank\"><img style=\"width: unset !important; display: inline; height: 1em; cursor: pointer;\" title=\"$url_title\" src=\"images/info.png\" width=\"20\"></a>";
      echo "<a href=\"https://wikipedia.org/wiki/$sciname\" target=\"_blank\"><img style=\"width: unset !important; display: inline; height: 1em; cursor: pointer;\" title=\"Wikipedia\" src=\"images/wiki.png\" width=\"20\"></a>";
      echo "<br>";
      echo "Occurrences: $count<br>";
      echo "Max Confidence: $maxconf<br>";
      echo "Best Recording: $date $time<br><br>";
    echo "</div>";
    echo "<div>";
    echo "<video onplay='setLiveStreamVolume(0)' onended='setLiveStreamVolume(1)' onpause='setLiveStreamVolume(1)' controls poster=\"$filename.png\" title=\"$filename\"><source src=\"$filename\"></video>";
    echo "</div>";
    echo "</td>";
    echo "</tr>";
    echo "</table>";
    echo "<script>document.getElementsByTagName(\"h3\")[0].scrollIntoView();</script>";
    
    ob_flush();
    flush();

    if (!empty($wiki_image['image_url'])) {
    // Add a link to search for more images on Wikimedia Commons
      $commons_search = "https://commons.wikimedia.org/w/index.php?search=" . urlencode($sciname . " OR " . $engname);
      echo "<p><a href='$commons_search' target='_blank'>View more images on Wikimedia Commons</a></p>";
    }
  }
}
?>
<?php if(isset($_GET['species'])){?>
<br><br>
<div class="brbanner">Best Recordings for Other Species:</div><br>
<?php } else {?>
<hr><br>
<?php } ?>
  <form action="views.php" method="GET">
    <input type="hidden" name="sort" value="<?php if(isset($_GET['sort'])){echo $_GET['sort'];}?>">
    <input type="hidden" name="view" value="Species Stats">
    <table>
<?php
$excludelines = [];
while($results=$result->fetchArray(SQLITE3_ASSOC))
{
  $count = $results['Count'];
  $maxconf = round((float)round($results['MaxConfidence'],2) * 100 ) . '%';
  $date = $results['Date'];
  $time = $results['Time'];
  $name = $results['Com_Name'];
  $comname = preg_replace('/ /', '_', $results['Com_Name']);
  $comname = preg_replace('/\'/', '', $comname);
  $filename = "/By_Date/".$results['Date']."/".$comname."/".$results['File_Name'];
  $sciname = $results['Sci_Name'];

  // Always use Wikipedia images
      $wiki = new WikipediaImages();
      $wiki_image = $wiki->get_image($sciname);

      if (!empty($wiki_image['image_url'])) {
        $iter++;
        $wikiTitle = $wiki_image['title'];
        $modaltext = $wiki_image['page_url'];
        $authorlink = $wiki_image['page_url'];
        $licenselink = $wiki_image['page_url'];
        $imageurl = $wiki_image['image_url'];
      }

  array_push($excludelines, $results['Date']."/".$comname."/".$results['File_Name']);
  array_push($excludelines, $results['Date']."/".$comname."/".$results['File_Name'].".png");

  echo "<tr>";
  echo "<td class=\"relative\">";
  echo "<a target=\"_blank\" href=\"index.php?filename=".$results['File_Name']."\">";
  echo "<img title=\"Open in new tab\" class=\"copyimage\" width=25 src=\"images/copy.png\">";
  echo "</a>";
  echo "<div class=\"centered_image_container\">";
    if (!empty($imageurl)) {
      echo "<img style='vertical-align:top' onclick='setModalText(".$iter.",\"".$wikiTitle."\",\"".$modaltext."\", \"".$authorlink."\", \"".$imageurl."\", \"".$licenselink."\")' src=\"$imageurl\" class=\"img1\">";
    }
    echo "<button type=\"submit\" name=\"species\" value=\"".$results['Com_Name']."\">$comname</i>";
    echo "<br>";
    echo "Occurrences: $count<br>";
    echo "Max Confidence: $maxconf<br>";
    echo "Best Recording: $date $time<br><br>";
  echo "</div>";
  echo "<div>";
  echo "<video onplay='setLiveStreamVolume(0)' onended='setLiveStreamVolume(1)' onpause='setLiveStreamVolume(1)' controls poster=\"$filename.png\" title=\"$filename\"><source src=\"$filename\"></video>";
  echo "</div>";
  echo "</td>";
  echo "</tr>";
}

$file = file_get_contents($home."/BirdNET-Pi/scripts/disk_check_exclude.txt");
file_put_contents($home."/BirdNET-Pi/scripts/disk_check_exclude.txt", "##start"."\n".implode("\n",$excludelines)."\n".substr($file, strpos($file, "##end")));
?>
    </table>
  </form>
</div>
</div>
<?php
if (get_included_files()[0] === __FILE__) {
  echo '</body></html>';
}
