<?php
$edit = false;
if ($_GET['edit'] == 'true')
  $edit = true;

$db = new mysqli("localhost", "cron", "1234", "asterisk");
?>
<!DOCTYPE html>
<html lang="en" ondragover="drag_over(event)" ondrop="drop(event)">
  <head>
    <meta charset="utf-8">
    <meta name="generator" content="CoffeeCup HTML Editor (www.coffeecup.com)">
    <meta name="dcterms.created" content="Fri, 27 Jun 2014 21:02:23 GMT">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <title>Agent Status Wallboard</title>
    <script src="jquery-2.1.1.min.js"></script>
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <!--[if IE]>
    <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
    <style>
    li
    {
      position:  absolute;
      left: 0;
      top: 0; /* set these so Chrome doesn't return 'auto' from getComputedStyle */
      width: 250px;
      background: rgba(255,255,255,0.66);
      border: 2px  solid rgba(0,0,0,0.5);
      border-radius: 4px; padding: 8px;
    }
    .box-sipinfo {
      height: 75px;
      border: 1px solid rgba(0,0,0,0.5);
      background: #696969;
      position:relative;
    }
    .container-sipinfo {
      position: absolute;
      width: 150px;
      border: 1px solid rgba(0,0,0,0.5);
      color: #FFFFFF;
    }
    .sip-border {
      border: 1px solid rgba(0,0,0,0.5);
    }

    .sip-header {
      background: #000000;
    }

    .info-top {
      position: absolute;
      top: 0;
      width: 100%;
    }

    .fleft {
      float: left;
    }

    .fright {
      float: right;
    }

    .user {
      text-align: center;
      position: absolute;
      top:35%;
      width: 100%;
    }

    .info-bottom {
      position: absolute;
      bottom: 0;
      width: 100%;
    }

    a {
      color: #FFFFFF;
    }

    .table-container {
      display: table;
      position: absolute;
      border: 1px solid rgba(0,0,0,0.5);
      color: #FFFFFF;
    }
    .row {
      display: table-row;
      height: 100px;
    }
    .column {
      display: table-cell;
      width: 250px;
      text-align: center;
      vertical-align: middle;
      border: 1px solid rgba(0,0,0,0.5);
    }
    </style>
    <script>
    $(document).ready(function(){ 
      <?php
      $data = json_decode(file_get_contents('data.json'), true);

      foreach ($data as $key => $val){
        ?>addBox("<?=$key?>", <?=$val['top']?>, <?=$val['left']?>);<?php
      }

      ?>

      window.setInterval(function() {
        getData();
      }, 1000);
    });

    function toHHMMSS (data) {
      var sec_num = parseInt(data, 10); // don't forget the second param
      var hours   = Math.floor(sec_num / 3600);
      var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
      var seconds = sec_num - (hours * 3600) - (minutes * 60);

      if (hours   < 10) {hours   = "0"+hours;}
      if (minutes < 10) {minutes = "0"+minutes;}
      if (seconds < 10) {seconds = "0"+seconds;}
      var time    = hours+':'+minutes+':'+seconds;
      return time;
    }

    function drag_start(event) {
      var style = window.getComputedStyle(event.target, null);
      var str = (parseInt(style.getPropertyValue("left")) - event.clientX) + ',' + (parseInt(style.getPropertyValue("top")) - event.clientY)+ ',' + event.target.id;
      event.dataTransfer.setData("Text",str);
    }

    function drop(event) {
      var offset = event.dataTransfer.getData("Text").split(',');
      var dm = document.getElementById(offset[2]);
      dm.style.left = (event.clientX + parseInt(offset[0],10)) + 'px';
      dm.style.top = (event.clientY + parseInt(offset[1],10)) + 'px';
      event.preventDefault();
      return false;
    }

    function drag_over(event){
      event.preventDefault();
      return false;
    }

    function getData(){
      $.getJSON( "wallboard_json.php", function( data ) {
        $('#extinfo-containers').children('.container-sipinfo').each( function(i,j){
          //alert('Got Child');
          var name = $(this).children('.box-header').children('.title').text();
          //alert(name);
          //console.log(data);
          if (data.hasOwnProperty(name)){
            $(this).children('.box-content').css('background','#' + data[name]["colour"]);

            $(this).children('.box-content').children('.info-top').children('.camp').text(data[name]["campaign_id"]);
            $(this).children('.box-content').children('.info-top').children('.callstoday').text(data[name]["calls_today"]);

            $(this).children('.box-content').children('.user').text(data[name]["user"]);

            $(this).children('.box-content').children('.info-bottom').children('.status').text(data[name]["status"]);
            $(this).children('.box-content').children('.info-bottom').children('.time').text(toHHMMSS(data[name]["seconds"]));
          } else {
            $(this).children('.box-content').css('background','#696969');

            $(this).children('.box-content').children('.info-top').children('.camp').html('&nbsp;');
            $(this).children('.box-content').children('.info-top').children('.callstoday').html('&nbsp;');

            $(this).children('.box-content').children('.user').text('Offline');

            $(this).children('.box-content').children('.info-bottom').children('.status').html('&nbsp;');
            $(this).children('.box-content').children('.info-bottom').children('.time').html('&nbsp;');
          }
        });
        var selval = $('#campselect').val();
        /*$('#campselect').empty();
        $.each( data["queue"], function (key, value){
          if (selval = key){
            $('#campselect').append('<option value=' + key + ' selected="selected">' + value["name"] + '</option>');
          } else {
            $('#campselect').append('<option value=' + key + '>' + value["name"] + '</option>');
          }
        });*/
        $('#callsqueued').text(data["queue"][selval]["number"]);
        $('#callsqueued_cell').css('background','#' + data["queue"][selval]["number_colour"]);
        $('#longestwait').text(toHHMMSS(data["queue"][selval]["wait_time"]));
        $('#longestwait_cell').css('background','#' + data["queue"][selval]["wait_time_colour"]);
      });
    }

    function savePosition(){
      var objectExport = new Object();

      $('#extinfo-containers').children('.container-sipinfo').each( function(i,j){
          var name = $(this).children('.box-header').children('.title').text();
          var top = $(this).css('top');
          var left = $(this).css('left');
          console.log(name + ' ' + top + ' ' + left);

          objectExport[name] = new Object();
          objectExport[name]["top"] = top.replace(/px/, '');
          objectExport[name]["left"] = left.replace(/px/, '');
      });

      $.post("wallboard_save.php", new Object({data: JSON.stringify(objectExport)}), function(data){ alert('Success'); });
    }

    function manualAddBox(){
      var name = $('#name').val().toUpperCase();
      addBox(name, 0, 0);
    }

    function removeBox(id){
      $('#' + id).remove();
    }

    function addBox(name, top, left){
      var stripname = name.replace(/\//g, '');
      console.log(stripname);

      var html = "<div id=\"" + stripname + "\" class=\"box container-sipinfo\" draggable=\"true\" ondragstart=\"drag_start(event)\" style=\"top: " + top + "px; left: " + left + "px;\"><div class=\"box-header sip-border sip-header\"><?php if ($edit){ ?><div class=\"close fright\"><a href=\"#\" onClick=\"removeBox('" + stripname + "');\"><i class=\"fa fa-close\"></i></a></div><?php } ?><div class=\"title\">" + name + "</div></div><div class=\"box-content padded box-sipinfo\"><div class=\"info-top\"><div class=\"camp fleft\">&nbsp;</div><div class=\"callstoday fright\">&nbsp;</div></div><br /><div class=\"user\">Offline</div><div class=\"info-bottom\"><div class=\"status fleft\">&nbsp;</div><div class=\"time fright\">&nbsp;</div></div></div></div>";

      if ($("#" + stripname).length == 0){
        $('#extinfo-containers').append(html);
      }
    }
    </script>
  </head>
  <body>
    <?php if ($edit){ ?>
  <input type="text" id="name" value="" />&nbsp;<input type="button" value="Add" onclick="manualAddBox()" /><br />
  <input type="button" value="Save" onclick="savePosition()" />
    <?php } ?>
  <div id="extinfo-containers" class="container padded">
    <?php if (!$edit) { ?>
    <div id="CALLCOUNT" class="table-container" draggable="true" ondragstart="drag_start(event)" style="left: 25px; top: 0px;">
      <div class="row">
        <div class="column" style="background: #34495E;">
          <select id="campselect" name="camp">
            <option value="global" selected>All Campaigns</option>
            <?php
            $sqllist = "SELECT
                          campaign_id,
                          campaign_name
                        FROM
                          vicidial_campaigns
                        WHERE
                          active='Y'";
            $result = $db->query($sqllist);
            while ($row = $result->fetch_assoc()){
              ?><option value="<?=$row['campaign_id']?>"><?=$row['campaign_name']?></option><?php
            }
            ?>
          </select>
        </div>
        <div id="callsqueued_cell" class="column" style="background: #27ae60;">
          <b>
            <div style="font-size: 25px;">Calls Queued</div>
            <div id="callsqueued" style="font-size: 40px;">00</div>
          </b>
        </div>
        <div id="longestwait_cell" class="column" style="background: #27ae60;">
          <b>
            <div style="font-size: 25px;">Longest Wait</div>
            <div id="longestwait" style="font-size: 40px;">00:00:00</div>
          </b>
        </div>
      </div>
    </div>
    <?php } ?>
    <!--<div id="SIP8001" class="box container-sipinfo" draggable="true" ondragstart="drag_start(event)" style="left: 553px; top: 349px;">
      <div class="box-header sip-border sip-header">
        <div class="close fright"><a href="#"><i class="fa fa-close"></i></a></div><div class="title">SIP&#x2F;8001</div>
      </div>
      <div class="box-content padded box-sipinfo">
          <div class="info-top"><div class="camp fleft">&nbsp;</div><div class="callstoday fright">&nbsp;</div></div><br />
          <div class="user">Offline</div>
          <div class="info-bottom"><div class="status fleft">&nbsp;</div><div class="time fright">&nbsp;</div></div>
      </div>
    </div>

    <div id="SIP8009" class="box container-sipinfo" draggable="true" ondragstart="drag_start(event)" style="left: 200px; top: 349px;">
      <div class="box-header sip-border sip-header">
        <div class="title">SIP&#x2F;8009</div>
      </div>
      <div class="box-content padded box-sipinfo">
          <div class="info-top"><div class="camp fleft">TESTCAMP</div><div class="callstoday fright">100</div></div><br />
          <div class="user">smelrose</div>
          <div class="info-bottom"><div class="status fleft">INCALL</div><div class="time fright">1:00:00</div></div>
      </div>
    </div>-->
  </div>
</body>
</html>
