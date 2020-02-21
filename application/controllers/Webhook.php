  <?php defined('BASEPATH') OR exit('No direct script access allowed');

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;

class Webhook extends CI_Controller {

  private $bot;
  private $events;
  private $signature;
  private $user;

  function __construct()
  {
    parent::__construct();
    $this->load->model('tebakkode_m');

    // create bot object
    $httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $this->bot  = new LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
  }

  public function index()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo "Hello Coders!";
      header('HTTP/1.1 400 Only POST method allowed');
      exit;
    }

    // get request
    $body = file_get_contents('php://input');
    $this->signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : "-";
    $this->events = json_decode($body, true);

    // log every event requests
    $this->tebakkode_m->log_events($this->signature, $body);

    // debuging data
    file_put_contents('php://stderr', 'Body: '.$body);

    if(is_array($this->events['events'])){
      foreach ($this->events['events'] as $event){
        // your code here

        //skip group and room event
        if(! isset($event['source']['userId'])) continue;
        
        //get user data from db
        $this->user = $this->tebakkode_m->getMember($event['source']['userId']);

        //if user not registered
        if(!$this->user) $this->followCallback($event);
        else{
          //respond event
          if($event['type'] == 'message'){
            if(method_exists($this, $event['message']['type'].'Message')){
              $this->{$event['message']['type'].'Message'}($event);
            }
          } else{
            if(method_exists($this, $event['type'].'Callback')){
              $this->{$event['type'].'Callback'}($event);
            }
          }
        }
      }
    }
  } // end of index.php

  private function followCallback($event){
    $res = $this->bot->getProfile($event['source']['userId']);
    if($res->isSucceeded()){
      $profile = $res->getJSONDecodedBody();

      $andri = 'U956ff5986ce46603aeb6dc92ae49b0fc';

      //welcome message
      $message = "Terimakasih sudah menambahkan Alan sebagai teman kak ".$profile['displayName']." !\n";
      $message .= "Silahkan ketik help untuk mengetahui fitur yang dimiliki Alan...";
      $tm = new TextMessageBuilder($message);

      //sticker message
      $sm = new StickerMessageBuilder(1,2);

      //merge message
      $mm = new MultiMessageBuilder();
      $mm->add($tm);
      $mm->add($sm);

      //relpy message
      $this->bot->replyMessage($event['replyToken'], $mm);

      $this->bot->pushMessage($andri,$mm);

      //save user data
      $this->tebakkode_m->saveMember($profile);
    }
  }

  private function textMessage($event){
    $srcType = $event['source']['type'];
    $usrMsg = $event['message']['text'];
    $lowUsrMsg = strtolower($usrMsg);
    $upUsrMsg = strtoupper($usrMsg);
    $uid = $event['source']['userId'];
    $profile = $this->tebakkode_m->getMember($uid);
    $divisi = $this->tebakkode_m->getDivName();
    $i = 1;
    $divlist = array();
    foreach ($divisi as $div) {
      array_push($divlist, $div['div_name']);
    }

    //room chat
    if($srcType != 'group'){
      if(strpos($usrMsg, ' ') !== false){
        $expMsg = explode(' ', $usrMsg, 2);
        $headMsg = $expMsg[0];
        $tailMsg = $expMsg[1];
        $lowHeadMsg = strtolower($headMsg);
        $lowTailMsg = strtolower($tailMsg);
        $upHeadMsg = strtoupper($headMsg);
        $upTailMsg = strtoupper($tailMsg);
        $ucHeadMsg = ucfirst($headMsg);
        $ucTailMsg = ucfirst($tailMsg);

        //join divisi
        if($lowHeadMsg == 'join'){
          if(!is_null($profile['divisi'])){
            $msg = "Kakak sudah terdaftar dalam bagian ".$profile['divisi'].".";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          else{
            if(!in_array($upTailMsg, $divlist)){
              $msg = "Bagian tidak tersedia.\n";
              $msg .= "Daftar bagian yang tersedia :\n";
              foreach ($divisi as $div) {
                $msg .= $i.". ".$div['div_name']."\n";
                $i++;
              }
              $msg .= "Harap bergabung sesuai dengan bagian yang tersedia.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
            else{
              $this->tebakkode_m->setDivisi($uid,$upTailMsg); 
              $msg = "Kakak telah terdaftar sebagai bagian dari ".$upTailMsg.".";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }
        }

        //leave divisi
        elseif($lowUsrMsg == "leave bagian"){
          if(is_null($profile['divisi'])){
            $msg = "Kakak belum terdaftar dalam bagian manapun.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          else{
            $this->tebakkode_m->leaveDivisi($uid);
            $msg = "Kakak telah keluar dari bagian.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //crud divisi
        elseif($lowHeadMsg == 'bagian'){
          $dexpMsg = explode(' ', $usrMsg, 3);
          $secMsg = strtolower($dexpMsg[1]);
          $trdMsg = strtoupper($dexpMsg[2]);
          $pdiv = $profile['divisi'];
          if($pdiv == 'PIMRED'){

            //add divisi
            if($secMsg == 'add'){
              if(strpos($trdMsg, ' ') == false){
                if(!in_array($trdMsg, $divlist)){
                  $this->tebakkode_m->setDiv($trdMsg);
                  $msg = "Bagian berhasil ditambahkan.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
                else{
                  $msg = "Bagian sudah terdaftar.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);  
                }
              }
              else{
                $msg = "Gunakan (-) untuk menggantikan (spasi) dalam menginputkan nama bagian.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //delete divisi
            elseif($secMsg == 'delete'){
              if(strpos($trdMsg, ' ') == false){
                if(in_array($trdMsg, $divlist)){
                  $this->tebakkode_m->delDiv($trdMsg);
                  $msg = "Bagian berhasil dihapus.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
                else{
                  $msg = "Bagian tidak ditemukan.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Gunakan (-) untuk menggantikan (spasi) dalam menginputkan nama bagian.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            else{
              $msg = "fitur tidak dikenali, list fitur bagian :\n";
              $msg .= "bagian add (namabagian) untuk menambahkan divisi baru.\n\n";
              $msg .= "bagian delete (namabagian) untuk menghapus divisi.\n\n";
              $msg .= "tekan help untuk mengetahui fitur Alan lainnya.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }
          else{
            //show bagian
            if($secMsg == 'show') {
              $msg .= "Daftar bagian yang tersedia :";
              foreach ($divisi as $div) {
                $msg .= "\n".$i.". ".$div['div_name'];
                $i++;
              }
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
            else{
              $msg = "Kakak tidak memiliki wewenang untuk menggunakan fitur ini.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }   
          } 
        }

        //look member divisi
        elseif($lowHeadMsg == 'look'){
          if(in_array($upTailMsg, $divlist)){
            $lookdiv = $this->tebakkode_m->getDiv($upTailMsg);
            if($upTailMsg == 'PU' || $upTailMsg == 'PIMPER' || $upTailMsg == 'PIMRED'){
              $msg = $upTailMsg." DISPLAY adalah :\n";
              foreach ($lookdiv as $name) {
                $msg .= $i.". ".$name['call_name']."\n";
                $i++;
              }
              $msg .= "\nPastikan kakak sudah melakukan join dan setname di roomchat Alan agar nama dapat terlihat di dalam bagian kakak.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
            else{
              $msg = "Bagian dari ".$upTailMsg." yang berteman dengan Alan adalah :\n";
              foreach ($lookdiv as $name) {
                $msg .= $i.". ".$name['call_name']."\n";
                $i++;
              }
              $msg .= "\nPastikan kakak sudah melakukan join dan setname di roomchat Alan agar nama dapat terlihat di dalam bagian kakak.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }
          elseif($lowTailMsg == 'all'){
            $lookall = $this->tebakkode_m->getCallMember();
            $msg = "Anggota DISPLAY yang berteman dengan Alan adalah :\n";
            foreach ($lookall as $name) {
              $msg .= $i.". ".$name['call_name']."\n";
              $i++;
            }
            $msg .= "\nPastikan kakak sudah melakukan setname di roomchat Alan agar nama dapat terlihat di list.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          else{
            $msg = "Bagian tidak tersedia.\n";
            $msg .= "List bagian yang tersedia :";
            foreach ($divisi as $div){
                $msg .= "\n".$i.". ".$div['div_name'];
                $i++;
            }
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //set name
        elseif($lowHeadMsg == 'setname'){
          if(!is_null($profile['call_name'])){
            $msg = "Bukankah kakak ingin Alan panggil ".$profile['call_name']."?\n\n";
            $msg .= "Jika ingin mengganti nama panggilan ketik \"chname(spasi)nama_baru\".";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          elseif(is_null($profile['call_name'])){
            if(strpos($ucTailMsg, ' ') == false){
              $this->tebakkode_m->setName($uid,$ucTailMsg);
              $msg = "OK, Alan akan mengingat nama panggilan kakak.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
            else{
              $msg = "Nama gagal disimpan, gunakan (_) untuk menggantikan (spasi).";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }
        }

        //change name
        elseif($lowHeadMsg == 'chname'){
          if(strpos($ucTailMsg, ' ') == false){
            $this->tebakkode_m->setName($uid,$ucTailMsg);
            $msg = "OK, mulai sekarang Alan akan memanggil kakak dengan nama panggilan baru.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          else{
            $msg = "Nama gagal disimpan, gunakan (_) untuk menggantikan (spasi).";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //change divisi
        elseif($lowHeadMsg == 'chbag'){
          if($profile['divisi'] == 'PIMRED'){
            $chexpMsg = explode(' ', $usrMsg, 3);
            $name = ucfirst($chexpMsg[1]);
            $div = strtoupper($chexpMsg[2]);
            $this->tebakkode_m->setODivisi($name,$div);
            $msg = "Anggota berhasil dipindahkan.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          else{
            $msg = "Kakak tidak memiliki wewenang untuk menggunakan fitur ini.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //call
        elseif(in_array($upHeadMsg, $divlist)){
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $prof = $res->getJSONDecodedBody();
            $cid = $this->tebakkode_m->getDiv($upHeadMsg);
            foreach ($cid as $id) {
              $lowDiv = strtolower($id['divisi']);
              $smsgbuild = new StickerMessageBuilder(2,150);
              $msg = "Dari : ".$prof['displayName'].".\n";
              $msg .= "Pesan untuk seluruh bagian ".strtoupper($lowDiv)." :\n".$tailMsg.".";
              $msgbuild = new TextMessageBuilder($msg);
              $mmsgbuild = new MultiMessageBuilder();
              $mmsgbuild->add($smsgbuild);
              $mmsgbuild->add($msgbuild);
              $this->bot->pushMessage($id['member_id'],$mmsgbuild);
            }
            $msgg = "Alan telah mengirimkan pesan ke ".$upHeadMsg.".";
            $msggbuild = new TextMessageBuilder($msgg);
            $this->bot->replyMessage($event['replyToken'], $msggbuild); 
          }
        }
        elseif($lowHeadMsg == 'all'){
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $prof = $res->getJSONDecodedBody();
            $aid = $this->tebakkode_m->getAllMember();
            foreach ($aid as $id) {
              $smsgbuild = new StickerMessageBuilder(2,150); 
              $msg = "Dari : ".$prof['displayName'].".\n";
              $msg .= "Pesan untuk semua :\n".$tailMsg.".";
              $msgbuild = new TextMessageBuilder($msg);
              $mmsgbuild = new MultiMessageBuilder();
              $mmsgbuild->add($smsgbuild);
              $mmsgbuild->add($msgbuild);
              $this->bot->pushMessage($id['member_id'],$mmsgbuild);
            }
            $msgg = "Alan telah mengirimkan pesan ke semua anggota DISPLAY.";
            $msggbuild = new TextMessageBuilder($msgg);
            $this->bot->replyMessage($event['replyToken'], $msggbuild);
          }
        }

        // product func
        elseif($lowHeadMsg == 'product'){
          $expProMsg = explode(' ', $usrMsg, 5);
          $headMsg = strtolower($expProMsg[0]);
          $cmdMsg = strtolower($expProMsg[1]);
          $typeMsg = strtolower($expProMsg[2]);
          $pjMsg = ucfirst($expProMsg[3]);
          $ttlMsg = ucfirst($expProMsg[4]);
          $proType = $this->tebakkode_m->getTypeName();
          $typelist = array();
          foreach ($proType as $pt) {
            array_push($typelist, $pt['prod_id']);
          }
          $num = 1;
          $cName = $this->tebakkode_m->getCallMember();
          $namelist = array();
          foreach ($cName as $nl) {
            array_push($namelist, $nl['call_name']);
          }
          $ecName = $this->tebakkode_m->getEcallMember();
          $enamelist = array();
          foreach ($ecName as $enl) {
            array_push($enamelist, $enl['call_name']);
          }
          $role = $profile['divisi'];
          $issue = $this->tebakkode_m->getAllIssue();
          $ilist = array();
          foreach ($issue as $isp) {
            array_push($ilist, $isp['issue_name']);
          }

          //add issue
          if($cmdMsg == 'add'){
            if($role == 'PIMRED' || $role == 'REPORTASE' || $role == 'MULTIMEDIA' || $role == 'KDP' || $role == 'SASTRA' || $role == 'EDITOR'){
              if(in_array($typeMsg, $typelist)){
                if(in_array($pjMsg, $namelist)){
                  if(strpos($ttlMsg, ' ') == false){
                    if(!in_array($ttlMsg, $ilist)){
                      $this->tebakkode_m->setIssue($typeMsg, $pjMsg, $ttlMsg, $profile['call_name']);
                      $msg = "Issue berhasil ditambahkan.";
                      $msgbuild = new TextMessageBuilder($msg);
                      $this->bot->replyMessage($event['replyToken'], $msgbuild);
                    }
                    else{
                      $msg = "Issue gagal ditambahkan, nama(judul) issue sudah pernah digunakan.";
                      $msgbuild = new TextMessageBuilder($msg);
                      $this->bot->replyMessage($event['replyToken'], $msgbuild);
                    }
                  }
                  else{
                    $msg = "Issue gagal ditambahkan, gunakan (-) untuk mengganti (spasi) pada judul issue. Contoh (Berita-bagus)";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                }
                else{
                  $msg = "Nama anggota tidak ditemukan, gunakan look(spasi)all untuk melihat seluruh anggota yang berteman dengan Alan.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Tipe produk salah, gunakan product(spasi)show(spasi)type untuk melihat jenis product yang tersedia.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }
            else{
              $msg = "Maaf kak, hanya pengurus redaksi yang dapat menambahkan issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }

          //insest command
          elseif($cmdMsg == 'insert'){
            $insMsg = explode(' ', $usrMsg, 4);
            $insTypeMsg = strtolower($insMsg[3]);

            //insert type product
            if($insMsg[2] == 'type'){
              if($role == 'PIMRED'){
                if(strpos($insTypeMsg,' ') == false){
                  if(!in_array($insTypeMsg, $typelist)){
                    $this->tebakkode_m->setProduct($insTypeMsg);
                    $msg = "Tipe produk berhasil ditambahkan.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                  else{
                    $msg = "Tipe produk sudah terdaftar.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                }
                else{
                  $msg = "Tipe produk gagal ditambahkan, gunakan (-) untuk mengganti (spasi). Contoh (mini-info)";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Maaf kak, perintah ini hanya bisa dilakukan oleh Pimpinan Redaksi.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }
          }

          //delete command
          elseif($cmdMsg == 'delete'){
            $insMsg = explode(' ', $usrMsg, 4);
            $expTwoMsg = strtolower($insMsg[2]);
            $expThreeMsg = strtolower($insMsg[3]);
            $ucThreeMsg = ucfirst($insMsg[3]);
            $jdlMsg = $insMsg[3];
            $issue = $this->tebakkode_m->getAllIssue();
            $isulist = array();
            foreach ($issue as $is) {
              array_push($isulist, $is['issue_name']);
            }

            //delete type product
            if($expTwoMsg == 'type'){
              if($role == 'PIMRED'){  
                if(in_array($expThreeMsg, $typelist)){
                  $this->tebakkode_m->delProduct($expThreeMsg);
                  $msg = "Tipe produk berhasil dihapus.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
                else{
                  $msg = "Tipe produk tidak ditemukan, gunakan product(spasi)show(spasi)type untuk melihat jenis product yang tersedia.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Maaf kak, perintah ini hanya bisa dilakukan oleh Pimpinan Redaksi.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //delete issue
            elseif($expTwoMsg == 'issue'){
              if($role == 'PIMRED'){
                if(in_array($jdlMsg, $isulist)){
                  $this->tebakkode_m->delIssue($jdlMsg);
                  $msg = "Issue berhasil dihapus.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
                else{
                  $msg = "Issue tidak ditemukan, gunakan product(spasi)show(spasi)all untuk melihat seluruh issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Maaf kak, perintah ini hanya bisa dilakukan oleh Pimpinan Redaksi.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }
          }

          //show command
          elseif($cmdMsg == 'show'){
            $expShowMsg = explode(' ', $usrMsg, 4);
            $expShowMsg2 = explode(' ', $usrMsg, 3);
            $secMsg2 = strtolower($expShowMsg2[2]);
            $secMsg = strtolower($expShowMsg[2]);
            $trdMsg = strtolower($expShowMsg[3]);

            //show all issue
            if($secMsg2 == 'all'){
              $msg = "List issue :";
              foreach($proType as $pt){
                $upPT = strtoupper($pt['prod_id']);
                $lowPT = strtolower($pt['prod_id']);
                $msg .= "\n".$upPT;
                $tpIssue = $this->tebakkode_m->getTypeIssue($lowPT);
                foreach($tpIssue as $tis){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n";
                $i = 1;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all issue in category
            elseif(in_array($secMsg2, $typelist)){
              $tpIssue = $this->tebakkode_m->getTypeIssue($secMsg2);
              $msg = "List ".ucfirst($secMsg2)." :";
              foreach($tpIssue as $tis){
                $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                $msg .= "PJ : ".$tis['issue_holder'].", ";
                $msg .= $tis['issue_holder2']."\n";
                $msg .= "Editor : ".$tis['issue_editor']."\n";
                $msg .= "Status : ".$tis['issue_stats']."\n";
                $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                $i++;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all pending issue
            elseif($secMsg2 == 'pending'){
              $msg = "List pending issue :";
              foreach($proType as $pt){
                $upPT = strtoupper($pt['prod_id']);
                $lowPT = strtolower($pt['prod_id']);
                $msg .= "\n".$upPT;
                $tpIssue = $this->tebakkode_m->getTypeIssue($lowPT);
                foreach($tpIssue as $tis) if($tis['issue_stats'] !== 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n";
                $i = 1;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all pending in category
            elseif($secMsg == 'pending'){
              if(in_array($trdMsg, $typelist)){
                $tpIssue = $this->tebakkode_m->getTypeIssue($trdMsg);
                $msg = "List pending ".ucfirst($trdMsg)." :";
                foreach($tpIssue as $tis) if($tis['issue_stats'] !== 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n#DobrakApatisme";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
              else{
                $msg = "Tipe produk tidak dikenali, gunakan product(spasi)show(spasi)type untuk mengetahui tipe produk yang tersedia.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //show all publish issue
            elseif($secMsg2 == 'publish'){
              $msg = "List published issue :";
              foreach($proType as $pt){
                $upPT = strtoupper($pt['prod_id']);
                $lowPT = strtolower($pt['prod_id']);
                $msg .= "\n".$upPT;
                $tpIssue = $this->tebakkode_m->getTypeIssue($lowPT);
                foreach($tpIssue as $tis) if($tis['issue_stats'] == 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n";
                $i = 1;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all pending in category
            elseif($secMsg == 'publish'){
              if(in_array($trdMsg, $typelist)){
                $tpIssue = $this->tebakkode_m->getTypeIssue($trdMsg);
                $msg = "List published ".ucfirst($trdMsg)." :";
                foreach($tpIssue as $tis) if($tis['issue_stats'] == 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n#DobrakApatisme";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
              else{
                $msg = "Tipe produk tidak dikenali, gunakan product(spasi)show(spasi)type untuk mengetahui tipe produk yang tersedia.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //show tipe produk
            elseif($secMsg2 == 'type'){
              $msg = "List tipe produk yang tersedia :";
                foreach($typelist as $tis) {
                  $ucTis = ucfirst($tis);
                  $msg .= "\n".$i.". ".$ucTis.".";
                  $i++;
                }
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }

          //change command
          elseif($cmdMsg == 'change'){
            
            //change status issue
            if($typeMsg == 'status'){
              $jdlMsg = $expProMsg[3];
              if(in_array($jdlMsg, $ilist)){
                $idata = $this->tebakkode_m->getOneIssue($jdlMsg);
                $pdiv = $profile['divisi'];
                $pholder = $profile['call_name'];
                $iholder = $idata['issue_holder'];
                $iholder2 = $idata['issue_holder2'];
                if($pholder == $iholder || $pholder == $iholder2 || $pdiv == 'PIMRED' || $pdiv == 'EDITOR'){
                  $this->tebakkode_m->setIssueStatus($ttlMsg,$jdlMsg);
                  $msg = "Status pengerjaan issue telah terupdate.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  if($ttlMsg == 'Publish'){
                    $this->tebakkode_m->setPubTime($jdlMsg);
                  }
                }
                else{
                  $msg = "Kakak tidak memiliki wewenang untuk merubah status issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Issue tidak ditemukan.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //change pj product
            elseif($typeMsg == 'pj'){
              $jdlMsg = $expProMsg[3];
              if(in_array($jdlMsg, $ilist)){
                $pdiv = $profile['divisi'];
                if($pdiv == 'PIMRED' || $pdiv =='MULTIMEDIA' || $pdiv =='KDP' || $pdiv =='SASTRA' || $pdiv =='REPORTASE'){
                  if(in_array($ttlMsg, $namelist)){
                    $this->tebakkode_m->setIssueHolder($ttlMsg, $jdlMsg);
                    $msg = "PJ issue berhasil diubah.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                  else{
                    $msg = "Nama anggota tidak ditemukan, gunakan look(spasi)all untuk melihat seluruh anggota yang berteman dengan Alan.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                }
                else{
                  $msg = "Kakak tidak memiliki wewenang untuk merubah PJ issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Issue tidak ditemukan.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //change pj product2
            elseif($typeMsg == 'pj2'){
              $jdlMsg = $expProMsg[3];
              if(in_array($jdlMsg, $ilist)){
                $pdiv = $profile['divisi'];
                if($pdiv == 'PIMRED' || $pdiv =='MULTIMEDIA' || $pdiv =='KDP' || $pdiv =='SASTRA' || $pdiv =='REPORTASE'){
                  if(in_array($ttlMsg, $namelist)){
                    $this->tebakkode_m->setIssueHolder2($ttlMsg, $jdlMsg);
                    $msg = "PJ issue berhasil diganti.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                  else{
                    $msg = "Nama anggota tidak ditemukan, gunakan look(spasi)all untuk melihat seluruh anggota yang berteman dengan Alan.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                }
                else{
                  $msg = "Kakak tidak memiliki wewenang untuk merubah PJ issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Issue tidak ditemukan.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //change editor product
            elseif($typeMsg == 'editor'){
              $jdlMsg = $expProMsg[3];
              if(in_array($jdlMsg, $ilist)){
                $pdiv = $profile['divisi'];
                if($pdiv == 'PIMRED' || $pdiv =='EDITOR'){
                  if(in_array($ttlMsg, $enamelist)){
                    $this->tebakkode_m->setIssueEditor($ttlMsg, $jdlMsg);
                    $msg = "Editor issue berhasil diganti.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                  else{
                    $msg = "Nama anggota tidak ditemukan dalam bagian editor, gunakan look(spasi)editor untuk melihat editor yang berteman dengan Alan.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                }
                else{
                  $msg = "Kakak tidak memiliki wewenang untuk merubah editor issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Issue tidak ditemukan.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }
          }

          //set command
          elseif($cmdMsg == 'set'){

            //set pj2
            if($typeMsg == 'pj2'){
              $jdlMsg = $expProMsg[3];
              if(in_array($jdlMsg, $ilist)){
                $pdiv = $profile['divisi'];
                if($pdiv == 'PIMRED' || $pdiv =='MULTIMEDIA' || $pdiv =='KDP' || $pdiv =='SASTRA' || $pdiv =='REPORTASE'){
                  if(in_array($ttlMsg, $namelist)){
                    $this->tebakkode_m->setIssueHolder2($ttlMsg, $jdlMsg);
                    $msg = "PJ issue berhasil ditambahkan.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                  else{
                    $msg = "Nama anggota tidak ditemukan, gunakan look(spasi)all untuk melihat seluruh anggota yang berteman dengan Alan.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                }
                else{
                  $msg = "Kakak tidak memiliki wewenang untuk menambahkan PJ issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Issue tidak ditemukan.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //set editor
            elseif($typeMsg == 'editor'){
              $jdlMsg = $expProMsg[3];
              if(in_array($jdlMsg, $ilist)){
                $pdiv = $profile['divisi'];
                if($pdiv == 'PIMRED' || $pdiv =='EDITOR'){
                  if(in_array($ttlMsg, $enamelist)){
                    $idata = $this->tebakkode_m->getOneIssue($jdlMsg);
                    $iedit = $idata['issue_editor'];
                    if(is_null($iedit)){
                      $this->tebakkode_m->setIssueEditor($ttlMsg, $jdlMsg);
                      $msg = "Editor issue berhasil ditambahkan.";
                      $msgbuild = new TextMessageBuilder($msg);
                      $this->bot->replyMessage($event['replyToken'], $msgbuild);
                    }
                    else{
                      $msg = "Issue sudah diedit oleh Kak ".$iedit.", gunakan product(spasi)change(spasi)editor(spasi)judulissue(spasi)editorbaru untuk mengganti editor.";
                      $msgbuild = new TextMessageBuilder($msg);
                      $this->bot->replyMessage($event['replyToken'], $msgbuild);
                    }
                  }
                  else{
                    $msg = "Nama anggota tidak ditemukan dalam bagian editor, gunakan look(spasi)editor untuk melihat seluruh editor yang berteman dengan Alan.";
                    $msgbuild = new TextMessageBuilder($msg);
                    $this->bot->replyMessage($event['replyToken'], $msgbuild);
                  }
                }
                else{
                  $msg = "Kakak tidak memiliki wewenang untuk mengatur editor issue.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Issue tidak ditemukan.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }
          }

          //take edit
          elseif($cmdMsg == 'edit'){
            $ePMsg = explode(' ', $usrMsg, 3);
            $ucTypeMsg = $ePMsg[2];
            if(in_array($ucTypeMsg, $ilist)){
              $pdiv = $profile['divisi'];
              $pname = $profile['call_name'];
              if($pdiv == 'EDITOR'){
                $idata = $this->tebakkode_m->getOneIssue($ucTypeMsg);
                $iedit = $idata['issue_editor'];
                if(is_null($iedit)){
                  $this->tebakkode_m->setIssueEditor($pname, $ucTypeMsg);
                  $msg = "Selamat mengedit Kak ".$profile['call_name'].".";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
                else{
                  $msg = "Issue sudah diedit oleh Kak ".$iedit.", gunakan product(spasi)change(spasi)editor(spasi)judulissue(spasi)editorbaru untuk mengganti editor.";
                  $msgbuild = new TextMessageBuilder($msg);
                  $this->bot->replyMessage($event['replyToken'], $msgbuild);
                }
              }
              else{
                $msg = "Kakak tidak memiliki wewenang untuk mengedit issue.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }
            else{
              $msg = "Issue tidak ditemukan.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }

          else{
            $message = "Halo kak ".$profile['call_name']." !\n";
            $message .= "Silahkan ketik help untuk mengetahui fitur yang dimiliki Alan...";
            $tm = new TextMessageBuilder($message);
            $sm = new StickerMessageBuilder(1,124);
            $mm = new MultiMessageBuilder();
            $mm->add($sm);
            $mm->add($tm);
            $this->bot->replyMessage($event['replyToken'], $mm);
          }
        }

        else{
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $profile = $res->getJSONDecodedBody();
            $message = "Halo kak ".$profile['displayName']." !\n";
            $message .= "Silahkan ketik help untuk mengetahui fitur yang dimiliki Alan...";
            $tm = new TextMessageBuilder($message);
            $sm = new StickerMessageBuilder(1,124);
            $mm = new MultiMessageBuilder();
            $mm->add($sm);
            $mm->add($tm);
            $this->bot->replyMessage($event['replyToken'], $mm);
          }
        }
      }

      else{
        //liat info pribadi
        if($lowUsrMsg == 'mystats'){
          if(!is_null($profile['call_name'])){
            $cond = "issue_holder='".$profile['call_name']."' OR issue_holder2='".$profile['call_name']."'";
            $count1 = 0;
            $count2 = 0;
            $findjml = $this->tebakkode_m->getCondIssue($cond);
            foreach ($findjml as $fj) if($fj['issue_name'] !== null){
              $count1++;
            }
            foreach ($findjml as $fj2) if($fj2['issue_stats'] == 'Publish'){
              $count2++;
            }
            $msg = "Nama : ".$profile['call_name']."\n";
            $msg .= "Nama Line : ".$profile['line_name']."\n";
            $msg .= "Bagian : ".$profile['divisi']."\n";
            $msg .= "Jumlah PJ : ".$count1."\n";
            $msg .= "Jumlah Publish : ".$count2;
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          elseif(is_null($profile['call_name'])){
            $msg = "Alan belum mengetahui nama panggilan kakak, ketik \"setname(spasi)nama_anda\" untuk mengatur bagaimana kakak ingin dipanggil oleh Alan.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //help code
        elseif($lowUsrMsg == 'help'){
          $pdiv = $profile['divisi'];
          if($pdiv == 'PIMRED'){
            $helpmsg = "Silahkan masukkan pesan sesuai panduan dibawah untuk menggunakan fitur dari Alan :\n\n";
            $helpmsg .= "join(spasi)namabagian : untuk mendata bagian kakak.\nContoh : join staf-redaksi\n\n";
            $helpmsg .= "setname(spasi)namakakak : untuk mengatur nama panggilan kakak.\nContoh : setname Alan\n\n";
            $helpmsg .= "chname(spasi)namalain : untuk mengubah nama panggilan kakak.\nContoh : chname Alanganteng\n\n";
            $helpmsg .= "mystats : untuk mendapat info profile kakak.\nContoh: mystats\n\n";
            $helpmsg .= "look(spasi)namabagian : untuk menlihat anggota bagian.\nContoh: look editor\n\n";
            $helpmsg .= "look(spasi)all : untuk melihat seluruh anggota.\nContoh: look all\n\n";
            $helpmsg .= "namabagian(spasi)pesan : untuk memberikan broadcast message.\nContoh: multimedia hebat\n\n";
            $helpmsg .= "all(spasi)pesan : untuk memberikan broadcast message.\nContoh: all penting\n\n";
            $helpmsg .= "bagian(spasi)add/delete(spasi)namabagian : untuk menambahkan dan menghapus bagian.\nContoh: bagian add reportase atau bagian delete reportase\n\n";
            $helpmsg .= "chbag(spasi)namaanggota(spasi)bagian : untuk mengubah bagian anggota.\nContoh: chbag intan sastra\n\n";

            $helpmsg .= "Masukkan pesan sesuai gaya ketikan kakak, karena Alan tidak case sensitif.";
            $helpmsgbuild = new TextMessageBuilder($helpmsg);
            $this->bot->replyMessage($event['replyToken'], $helpmsgbuild);
          }
          else{
            $helpmsg = "Silahkan masukkan pesan sesuai panduan dibawah untuk menggunakan fitur dari Alan :\n\n";
            $helpmsg .= "join(spasi)namabagian : untuk mendata bagian kakak.\nContoh : join staf-redaksi\n\n";
            $helpmsg .= "setname(spasi)namakakak : untuk mengatur nama panggilan kakak.\nContoh : setname Alan\n\n";
            $helpmsg .= "chname(spasi)namalain : untuk mengubah nama panggilan kakak.\nContoh : chname Alanganteng\n\n";
            $helpmsg .= "mystats : untuk mendapat info profile kakak.\nContoh: mystats\n\n";
            $helpmsg .= "look(spasi)namabagian : untuk mendapat info anggota bagian.\nContoh: look editor\n\n";
            $helpmsg .= "look(spasi)all : untuk melihat seluruh anggota.\nContoh: look all\n\n";
            $helpmsg .= "namabagian(spasi)pesan : untuk memberikan broadcast message.\nContoh: multimedia hebat\n\n";
            $helpmsg .= "all(spasi)pesan : untuk memberikan broadcast message.\nContoh: all penting\n\n";
            $helpmsg .= "Masukkan pesan sesuai gaya ketikan kakak, karena Alan tidak case sensitif.";
            $helpmsgbuild = new TextMessageBuilder($helpmsg);
            $this->bot->replyMessage($event['replyToken'], $helpmsgbuild);
          }
        }

        else{
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $profile = $res->getJSONDecodedBody();
            $message = "Halo kak ".$profile['displayName']." !\n";
            $message .= "Silahkan ketik help untuk mengetahui fitur yang dimiliki Alan...";
            $tm = new TextMessageBuilder($message);
            $sm = new StickerMessageBuilder(1,124);
            $mm = new MultiMessageBuilder();
            $mm->add($sm);
            $mm->add($tm);
            $this->bot->replyMessage($event['replyToken'], $mm);
          }
        }
      }
    }

    //group chat
    elseif($srcType == 'group'){
      if(strpos($usrMsg, ' ') !== false){
        $expMsg = explode(' ', $usrMsg, 2);
        $headMsg = $expMsg[0];
        $tailMsg = $expMsg[1];
        $lowHeadMsg = strtolower($headMsg);
        $lowTailMsg = strtolower($tailMsg);
        $upHeadMsg = strtoupper($headMsg);
        $upTailMsg = strtoupper($tailMsg);
        $ucHeadMsg = ucfirst($headMsg);
        $ucTailMsg = ucfirst($tailMsg);
        $cName = $this->tebakkode_m->getCallMember();
        $namelist = array();
        foreach ($cName as $nl) {
          array_push($namelist, $nl['call_name']);
        }

        //look member divisi
        if($lowHeadMsg == 'look'){
          if(in_array($upTailMsg, $divlist)){
            $lookdiv = $this->tebakkode_m->getDiv($upTailMsg);
            if($upTailMsg == 'PU' || $upTailMsg == 'PIMPER' || $upTailMsg == 'PIMRED'){
              $msg = $upTailMsg." DISPLAY adalah :\n";
              foreach ($lookdiv as $name) {
                $msg .= $i.". ".$name['call_name']."\n";
                $i++;
              }
              $msg .= "\nPastikan kakak sudah melakukan join dan setname di roomchat Alan agar nama dapat terlihat di dalam bagian kakak.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
            else{
              $msg = "Bagian dari ".$upTailMsg." yang berteman dengan Alan adalah :\n";
              foreach ($lookdiv as $name) {
                $msg .= $i.". ".$name['call_name']."\n";
                $i++;
              }
              $msg .= "\nPastikan kakak sudah melakukan join dan setname di roomchat Alan agar nama dapat terlihat di dalam bagian kakak.";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }
          }
          elseif($lowTailMsg == 'all'){
            $lookall = $this->tebakkode_m->getCallMember();
            $msg = "Anggota DISPLAY yang berteman dengan Alan adalah :\n";
            foreach ($lookall as $name) {
              $msg .= $i.". ".$name['call_name']."\n";
              $i++;
            }
            $msg .= "\nPastikan kakak sudah melakukan setname di roomchat Alan agar nama dapat terlihat di list.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          else{
            $msg = "Bagian tidak tersedia.\n";
            $msg .= "List bagian yang tersedia :";
            foreach ($divisi as $div){
                $msg .= "\n".$i.". ".$div['div_name'];
                $i++;
            }
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //lihat status orang lain
        if($lowHeadMsg == 'shstats'){
          if(in_array($ucTailMsg, $namelist)){
            $lookcall = $this->tebakkode_m->getCall($ucTailMsg);
            foreach ($lookcall as $name) {
              $cond = "issue_holder='".$name['call_name']."' OR issue_holder2='".$name['call_name']."'";
              $count1 = 0;
              $count2 = 0;
              $findjml = $this->tebakkode_m->getCondIssue($cond);
              foreach ($findjml as $fj) if($fj['issue_name'] !== null){
                $count1++;
              }
              foreach ($findjml as $fj2) if($fj2['issue_stats'] == 'Publish'){
                $count2++;
              }
              $msg .= "Nama : ".$name['call_name']."\n";
              $msg .= "Nama Line : ".$name['line_name']."\n";
              $msg .= "Bagian : ".$name['divisi']."\n";
              $msg .= "Jumlah PJ : ".$count1."\n";
              $msg .= "Jumlah Publish : ".$count2;
            }
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          else{
            $msg .= "Nama tidak dikenal.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //call
        elseif(in_array($upHeadMsg, $divlist)){
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $prof = $res->getJSONDecodedBody();
            $cid = $this->tebakkode_m->getDiv($upHeadMsg);
            foreach ($cid as $id) {
              $lowDiv = strtolower($id['divisi']);
              $smsgbuild = new StickerMessageBuilder(2,150);
              $msg = "Dari : ".$prof['displayName'].".\n";
              $msg .= "Pesan untuk seluruh bagian ".strtoupper($lowDiv)." :\n".$tailMsg.".";
              $msgbuild = new TextMessageBuilder($msg);
              $mmsgbuild = new MultiMessageBuilder();
              $mmsgbuild->add($smsgbuild);
              $mmsgbuild->add($msgbuild);
              $this->bot->pushMessage($id['member_id'],$mmsgbuild);
            }
            $msgg = "Alan telah mengirimkan pesan ke ".$upHeadMsg.".";
            $msggbuild = new TextMessageBuilder($msgg);
            $this->bot->replyMessage($event['replyToken'], $msggbuild); 
          }
        }
        elseif($lowHeadMsg == 'all'){
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $prof = $res->getJSONDecodedBody();
            $aid = $this->tebakkode_m->getAllMember();
            foreach ($aid as $id) {
              $smsgbuild = new StickerMessageBuilder(2,150); 
              $msg = "Dari : ".$prof['displayName'].".\n";
              $msg .= "Pesan untuk semua :\n".$tailMsg.".";
              $msgbuild = new TextMessageBuilder($msg);
              $mmsgbuild = new MultiMessageBuilder();
              $mmsgbuild->add($smsgbuild);
              $mmsgbuild->add($msgbuild);
              $this->bot->pushMessage($id['member_id'],$mmsgbuild);
            }
            $msgg = "Alan telah mengirimkan pesan ke semua anggota DISPLAY.";
            $msggbuild = new TextMessageBuilder($msgg);
            $this->bot->replyMessage($event['replyToken'], $msggbuild);
          }
        }

        // product func
        elseif($lowHeadMsg == 'product'){
          $expProMsg = explode(' ', $usrMsg, 5);
          $headMsg = strtolower($expProMsg[0]);
          $cmdMsg = strtolower($expProMsg[1]);
          $typeMsg = strtolower($expProMsg[2]);
          $pjMsg = ucfirst($expProMsg[3]);
          $ttlMsg = ucfirst($expProMsg[4]);
          $proType = $this->tebakkode_m->getTypeName();
          $typelist = array();
          foreach ($proType as $pt) {
            array_push($typelist, $pt['prod_id']);
          }
          $num = 1;
          $cName = $this->tebakkode_m->getCallMember();
          $namelist = array();
          foreach ($cName as $nl) {
            array_push($namelist, $nl['call_name']);
          }
          $ecName = $this->tebakkode_m->getEcallMember();
          $enamelist = array();
          foreach ($ecName as $enl) {
            array_push($enamelist, $enl['call_name']);
          }
          $role = $profile['divisi'];
          $issue = $this->tebakkode_m->getAllIssue();
          $ilist = array();
          foreach ($issue as $isp) {
            array_push($ilist, $isp['issue_name']);
          }

          //show command
          if($cmdMsg == 'show'){
            $expShowMsg = explode(' ', $usrMsg, 4);
            $expShowMsg2 = explode(' ', $usrMsg, 3);
            $secMsg2 = strtolower($expShowMsg2[2]);
            $secMsg = strtolower($expShowMsg[2]);
            $trdMsg = strtolower($expShowMsg[3]);

            //show all issue
            if($secMsg2 == 'all'){
              $msg = "List issue :";
              foreach($proType as $pt){
                $upPT = strtoupper($pt['prod_id']);
                $lowPT = strtolower($pt['prod_id']);
                $msg .= "\n".$upPT;
                $tpIssue = $this->tebakkode_m->getTypeIssue($lowPT);
                foreach($tpIssue as $tis){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n";
                $i = 1;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all issue in category
            elseif(in_array($secMsg2, $typelist)){
              $tpIssue = $this->tebakkode_m->getTypeIssue($secMsg2);
              $msg = "List ".ucfirst($secMsg2)." :";
              foreach($tpIssue as $tis){
                $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                $msg .= "PJ : ".$tis['issue_holder'].", ";
                $msg .= $tis['issue_holder2']."\n";
                $msg .= "Editor : ".$tis['issue_editor']."\n";
                $msg .= "Status : ".$tis['issue_stats']."\n";
                $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                $i++;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all pending issue
            elseif($secMsg2 == 'pending'){
              $msg = "List pending issue :";
              foreach($proType as $pt){
                $upPT = strtoupper($pt['prod_id']);
                $lowPT = strtolower($pt['prod_id']);
                $msg .= "\n".$upPT;
                $tpIssue = $this->tebakkode_m->getTypeIssue($lowPT);
                foreach($tpIssue as $tis) if($tis['issue_stats'] !== 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n";
                $i = 1;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all pending in category
            elseif($secMsg == 'pending'){
              if(in_array($trdMsg, $typelist)){
                $tpIssue = $this->tebakkode_m->getTypeIssue($trdMsg);
                $msg = "List pending ".ucfirst($trdMsg)." :";
                foreach($tpIssue as $tis) if($tis['issue_stats'] !== 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n#DobrakApatisme";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
              else{
                $msg = "Tipe produk tidak dikenali, gunakan product(spasi)show(spasi)type untuk mengetahui tipe produk yang tersedia.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }

            //show all publish issue
            elseif($secMsg2 == 'publish'){
              $msg = "List published issue :";
              foreach($proType as $pt){
                $upPT = strtoupper($pt['prod_id']);
                $lowPT = strtolower($pt['prod_id']);
                $msg .= "\n".$upPT;
                $tpIssue = $this->tebakkode_m->getTypeIssue($lowPT);
                foreach($tpIssue as $tis) if($tis['issue_stats'] == 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n";
                $i = 1;
              }
              $msg .= "\n#DobrakApatisme";
              $msgbuild = new TextMessageBuilder($msg);
              $this->bot->replyMessage($event['replyToken'], $msgbuild);
            }

            //show all pending in category
            elseif($secMsg == 'publish'){
              if(in_array($trdMsg, $typelist)){
                $tpIssue = $this->tebakkode_m->getTypeIssue($trdMsg);
                $msg = "List published ".ucfirst($trdMsg)." :";
                foreach($tpIssue as $tis) if($tis['issue_stats'] == 'Publish'){
                  $msg .= "\n".$i.". ".$tis['issue_name']."\n";
                  $msg .= "PJ : ".$tis['issue_holder'].", ";
                  $msg .= $tis['issue_holder2']."\n";
                  $msg .= "Editor : ".$tis['issue_editor']."\n";
                  $msg .= "Status : ".$tis['issue_stats']."\n";
                  $msg .= "Tanggal Mulai : ".$tis['issue_date']."\n";
                  $msg .= "Tanggal Publish : ".$tis['issue_endate']."\n";
                  $i++;
                }
                $msg .= "\n#DobrakApatisme";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
              else{
                $msg = "Tipe produk tidak dikenali, gunakan product(spasi)show(spasi)type untuk mengetahui tipe produk yang tersedia.";
                $msgbuild = new TextMessageBuilder($msg);
                $this->bot->replyMessage($event['replyToken'], $msgbuild);
              }
            }
          }

          else{
            $message = "Halo kak ".$profile['call_name']." !\n";
            $message .= "Silahkan ketik alan -help untuk mengetahui fitur yang dimiliki Alan...";
            $tm = new TextMessageBuilder($message);
            $sm = new StickerMessageBuilder(1,124);
            $mm = new MultiMessageBuilder();
            $mm->add($sm);
            $mm->add($tm);
            $this->bot->replyMessage($event['replyToken'], $mm);
          }
        }

        //help code
        elseif($lowUsrMsg == 'alan -help'){
          $helpmsg = "Silahkan masukkan pesan sesuai panduan dibawah untuk menggunakan fitur dari Alan :\n\n";
          $helpmsg .= "look(spasi)namabagian : untuk mendapat info anggota bagian.\nContoh: look editor\n\n";
          $helpmsg .= "namabagian(spasi)pesan : untuk memberikan broadcast message ke seluruh anggota bagian.\nContoh: multimedia hebat\n\n";
          $helpmsg .= "namabagian : untuk membrikan notifikasi ke seluruh anggota bagian.\nContoh : multimedia\n\n";
          $helpmsg .= "all(spasi)pesan : untuk memberikan broadcast message ke seluruh anggota redaksi.\nContoh: all penting\n\n";
          $helpmsg .= "all : untuk memberikan notifikasi ke seluruh anggota redaksi.\n\n";
          $helpmsg .= "Masukkan pesan sesuai gaya ketikan kakak, karena Alan tidak case sensitif.";
          $helpmsgbuild = new TextMessageBuilder($helpmsg);
          $this->bot->replyMessage($event['replyToken'], $helpmsgbuild);
        }
      }

      else{
        //call
        if(in_array($upUsrMsg, $divlist)){
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $prof = $res->getJSONDecodedBody();
            $cid = $this->tebakkode_m->getDiv($upUsrMsg);
            foreach ($cid as $id) {
              $lowDiv = strtolower($id['divisi']);
              $msg = "Halo kak ".$id['call_name'].", ".strtoupper($lowDiv)." telah dipanggil di group oleh ".$prof['displayName'].".";
              $smsgbuild = new StickerMessageBuilder(1,17);
              $msgbuild = new TextMessageBuilder($msg);
              $mmsgbuild = new MultiMessageBuilder();
              $mmsgbuild->add($smsgbuild);
              $mmsgbuild->add($msgbuild);
              $this->bot->pushMessage($id['member_id'],$mmsgbuild);
            }
            $msgg = "Alan telah mengirimkan pesan ke ".strtoupper($lowUsrMsg).".";
            $msggbuild = new TextMessageBuilder($msgg);
            $this->bot->replyMessage($event['replyToken'], $msggbuild); 
          }
        }
        elseif($lowUsrMsg == 'all'){
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $prof = $res->getJSONDecodedBody();
            $aid = $this->tebakkode_m->getAllMember();
            foreach ($aid as $id) {
              $msg = "Halo kak ".$id['call_name'].", semua anggota DISPLAY telah dipanggil di group oleh ".$prof['displayName'].".";
              $smsgbuild = new StickerMessageBuilder(1,17);
              $msgbuild = new TextMessageBuilder($msg);
              $mmsgbuild = new MultiMessageBuilder();
              $mmsgbuild->add($smsgbuild);
              $mmsgbuild->add($msgbuild);
              $this->bot->pushMessage($id['member_id'],$mmsgbuild);
            }
            $msgg = "Alan telah mengirimkan pesan ke semua anggota DISPLAY.";
            $msggbuild = new TextMessageBuilder($msgg);
            $this->bot->replyMessage($event['replyToken'], $msggbuild); 
          }
        }

        //mystats
        if($lowUsrMsg == 'mystats'){
          if(!is_null($profile['call_name'])){
            $cond = "issue_holder='".$profile['call_name']."' OR issue_holder2='".$profile['call_name']."'";
            $count1 = 0;
            $count2 = 0;
            $findjml = $this->tebakkode_m->getCondIssue($cond);
            foreach ($findjml as $fj) if($fj['issue_name'] !== null){
              $count1++;
            }
            foreach ($findjml as $fj2) if($fj2['issue_stats'] == 'Publish'){
              $count2++;
            }
            $msg = "Nama : ".$profile['call_name']."\n";
            $msg .= "Nama Line : ".$profile['line_name']."\n";
            $msg .= "Bagian : ".$profile['divisi']."\n";
            $msg .= "Jumlah PJ : ".$count1."\n";
            $msg .= "Jumlah Publish : ".$count2;
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
          elseif(is_null($profile['call_name'])){
            $msg = "Alan belum mengetahui nama panggilan kakak, ketik \"setname(spasi)nama_anda\" untuk mengatur bagaimana kakak ingin dipanggil oleh Alan.";
            $msgbuild = new TextMessageBuilder($msg);
            $this->bot->replyMessage($event['replyToken'], $msgbuild);
          }
        }

        //call alan
        elseif($lowUsrMsg == 'alan'){
          $res = $this->bot->getProfile($event['source']['userId']);
          if($res->isSucceeded()){
            $profile = $res->getJSONDecodedBody();
            $message = "Halo kak ".$profile['displayName']." !\n";
            $message .= "Silahkan ketik \"alan -help\" untuk mengetahui fitur yang dimiliki Alan...";
            $tm = new TextMessageBuilder($message);
            $sm = new StickerMessageBuilder(1,124);
            $mm = new MultiMessageBuilder();
            $mm->add($sm);
            $mm->add($tm);
            $this->bot->replyMessage($event['replyToken'], $mm);
          }
        }
      }
    }
  }

  private function stickerMessage($event){}

  public function sendQuestion($replyToken, $questionNum=1){}

  private function checkAnswer($message, $replyToken){}

}
