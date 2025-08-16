<?php
$SMTP_HOST = "mail.privateemail.com";
$SMTP_USER = "info@globalibmc.com";
$SMTP_PASS = "London@123";

$TO        = "info@globalibmc.com";
$FROM      = "info@globalibmc.com";
$FROM_NAME = "INTERNATIONAL BUSINESS MANAGEMENT CONSULTANT INC.";
$SUBJECT   = "New Website Enquiry — globalibmc.com";

$LOG_FILE  = __DIR__ . "/smtp_log.txt";
$DEBUG     = true;

function logmsg($s){ global $LOG_FILE,$DEBUG; if($DEBUG) file_put_contents($LOG_FILE,date('c')." | ".$s."\n",FILE_APPEND); }
function clean($v){ return trim(str_replace(["\r","\n"],[' ',' '], htmlspecialchars($v ?? '', ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))); }
if (!empty($_POST['botcheck'])) { header("Location: error.html"); exit; }

$name        = clean($_POST['name'] ?? '');
$email       = clean($_POST['email'] ?? '');
$phone       = clean($_POST['phone'] ?? '');
$company_in  = clean($_POST['company'] ?? '');
$origin      = clean($_POST['origin'] ?? '');
$destination = clean($_POST['destination'] ?? '');
$product     = clean($_POST['product'] ?? '');
$message     = clean($_POST['message'] ?? $_POST['notes'] ?? '');

if ($name===''||$email===''||$product===''||$origin===''||$destination===''){ header("Location: error.html"); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)){ header("Location: error.html"); exit; }

$time = date('Y-m-d H:i:s');
$body =
"Time: $time\nName: $name\nEmail: $email\nPhone: $phone\nCompany: $company_in\nOrigin: $origin\nDestination: $destination\nProduct: $product\nMessage:\n$message\n";

function smtp_send($host,$port,$secure,$user,$pass,$from,$to,$subject,$body,$reply_to=null,$from_name=null){
  logmsg("Connecting via $secure:$port");
  $transport = ($secure==='ssl') ? "ssl://".$host : $host;
  $fp = @fsockopen($transport,$port,$errno,$errstr,25);
  if(!$fp){ logmsg("fsockopen failed: $errno $errstr"); return false; }

  $read  = function() use ($fp){ return fgets($fp,4096); };
  $cmd   = function($c) use ($fp){ fputs($fp,$c."\r\n"); };
  $readEHLO = function() use ($read){
    $buf='';
    while($line=$read()){
      $buf.=$line;
      if (strncmp($line,"250 ",4)===0) break;
      if (strncmp($line,"250-",4)!==0) break;
    }
    return $buf;
  };

  $greet=$read(); logmsg("S: ".trim($greet));
  $cmd("EHLO ".$host); $ehlo1=$readEHLO(); logmsg("EHLO:\n".trim($ehlo1));

  if($secure==='tls'){
    $cmd("STARTTLS"); $resp=$read(); logmsg("S: ".trim($resp));
    if(strpos($resp,'220')!==0){ logmsg("STARTTLS failed"); fclose($fp); return false; }
    @stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $cmd("EHLO ".$host); $ehlo2=$readEHLO(); logmsg("EHLO(after TLS):\n".trim($ehlo2));
  }

  $cmd("AUTH LOGIN"); $line=$read(); logmsg("AUTH1 S: ".trim($line));
  if(strpos($line,'334')!==0){ logmsg("AUTH not accepted"); fclose($fp); return false; }
  $cmd(base64_encode($user)); $line=$read(); logmsg("AUTH2 S: ".trim($line));
  if(strpos($line,'334')!==0){ logmsg("Username not accepted"); fclose($fp); return false; }
  $cmd(base64_encode($pass)); $line=$read(); logmsg("AUTH3 S: ".trim($line));
  if(strpos($line,'235')!==0){ logmsg("Password not accepted"); fclose($fp); return false; }

  $cmd("MAIL FROM:<".$from.">"); $line=$read(); logmsg("MAIL FROM S: ".trim($line));
  foreach (explode(',',$to) as $rcpt){
    $rcpt=trim($rcpt);
    $cmd("RCPT TO:<".$rcpt.">"); $line=$read(); logmsg("RCPT TO $rcpt S: ".trim($line));
  }
  $cmd("DATA"); $line=$read(); logmsg("DATA S: ".trim($line));

  $from_name = $from_name ?: $from;
  $headers = "From: ".$from_name." <".$from.">\r\n"
           . ($reply_to ? "Reply-To: ".$reply_to."\r\n" : "")
           . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

  $cmd($headers."Subject: ".$subject."\r\n\r\n".$body."\r\n.");
  $final=$read(); logmsg("End DATA S: ".trim($final));
  $cmd("QUIT"); fclose($fp);
  return (strpos($final,'250')===0);
}

$ok = smtp_send($SMTP_HOST,587,"tls",$SMTP_USER,$SMTP_PASS,$FROM,$TO,$SUBJECT,$body,$email,$FROM_NAME);
if(!$ok) $ok = smtp_send($SMTP_HOST,465,"ssl",$SMTP_USER,$SMTP_PASS,$FROM,$TO,$SUBJECT,$body,$email,$FROM_NAME);

$auto_subject = "We received your enquiry — INTERNATIONAL BUSINESS MANAGEMENT CONSULTANT INC.";
$auto_body = "Hello $name,\n\nThank you for contacting INTERNATIONAL BUSINESS MANAGEMENT CONSULTANT INC. We will respond shortly.\n\nPhone: +1 469-317-0620\nEmail: info@globalibmc.com\nWebsite: https://globalibmc.com\n";
smtp_send($SMTP_HOST,587,"tls",$SMTP_USER,$SMTP_PASS,$FROM,$email,$auto_subject,$auto_body,"info@globalibmc.com",$FROM_NAME)
  || smtp_send($SMTP_HOST,465,"ssl",$SMTP_USER,$SMTP_PASS,$FROM,$email,$auto_subject,$auto_body,"info@globalibmc.com",$FROM_NAME);

if($ok){ header("Location: thank-you.html"); } else { header("Location: error.html"); }
exit;
?>