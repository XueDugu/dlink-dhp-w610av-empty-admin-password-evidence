<?
include "/htdocs/phplib/trace.php";
include "/htdocs/phplib/xnode.php";

function startcmd($cmd) {fwrite(a,$_GLOBALS["START"], $cmd."\n");}
function stopcmd($cmd)  {fwrite(a,$_GLOBALS["STOP"],  $cmd."\n");}

fwrite("w", $START, "#!/bin/sh\n");
fwrite("w", $STOP, "#!/bin/sh\n");

/* Create root account */
startcmd("addgroup -g 0 root");
startcmd("adduser -D -H -S -G root root");

/* Create nobody */
startcmd("addgroup -g 500 nobody");
startcmd("adduser -H -D -G nobody nobody");

startcmd("event DEVICE.ACCOUNT.DONE");
startcmd("exit 0");


stopcmd("exit 0");

/* prepare the passwd file: /tmp/passwd that will be removed as the zipped /var/passwd generated */
$file = "/tmp/passwd";
fwrite("w", $file, "");

$cnt = query("/device/account/count");
$i = 0;

while ($i < $cnt)
{
	$i++;
	$name	= get("s", "/device/account/entry:".$i."/name");
	$pwd	= get("s", "/device/account/entry:".$i."/password");
	$encrypted	= get("s", "/device/account/entry:".$i."/encrypted");
	if($encrypted != "1") {
		$encrypted = "0";		
	}
	$grp	= get("s", "/device/account/entry:".$i."/group");
	TRACE_debug('  DEVICE.ACCOUNT:'.$cnt.'.'.$i.' "'.$name.'" "'.$pwd.'" "'.$encrypted.'" "'.$grp.'"');
	fwrite("a", $file, '"'.$name.'" "'.$pwd.'" "'.$grp.'" "'.$encrypted.'"\n');
	if(query("/inf:1/hnap") == "1" || query("/inf:2/hnap") == "1") {
		fwrite("w", "/var/etc/hnapasswd", $name.":".$pwd."\n");// Generate the password for HNAP Joseph_Chao
	}
}
event("PASSWD.ZIP");    // /tmp/passwd is saved into zip format in /var/passwd and removed.
/* prepare the session config file: /var/session/sesscfg */


$file = "/var/session/sesscfg";
anchor("/device/session");
fwrite("w", $file,
			"\"".query("timeout")       ."\" ".
			"\"".query("maxsession")    ."\" ".
			"\"".query("maxauthorized") ."\" ".
			"\"".query("captcha")       ."\"");
?>
