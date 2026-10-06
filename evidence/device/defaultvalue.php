<?
/*rework wifi to
1.ssid dlink+mac
2.password and wpaauto psk.
*/
include "/htdocs/phplib/xnode.php";
include "/htdocs/phplib/trace.php";
include "/htdocs/webinc/config.php";

setattr("/runtime/devdata/wifiverify" ,"get","devdata get -e wifiverify");
setattr("/runtime/devdata/ipv6logo" ,"get","devdata get -e ipv6logo");
setattr("/runtime/devdata/mfcmode" ,"get","/etc/scripts/mfcmode.sh");
setattr("/runtime/devdata/mfcbtn" ,"get","devdata get -e mfcbtn");


setattr("/runtime/tmpdevdata/wifipassword" ,"get","devdata get -e psk"); 
setattr("/runtime/tmpdevdata/ssid_2G" ,"get","devdata get -e wifissid_2g"); 
setattr("/runtime/tmpdevdata/ssid_5G" ,"get","devdata get -e wifissid_5g"); 
setattr("/runtime/tmpdevdata/wlanmac" ,"get","devdata get -e wlanmac");
setattr("/runtime/tmpdevdata/wlanmac5" ,"get","devdata get -e wlan5mac"); 
setattr("/runtime/tmpdevdata/lanmac" ,"get","devdata get -e lanmac");
setattr("/runtime/tmpdevdata/countrycode" ,"get","devdata get -e countrycode");
setattr("/runtime/tmpdevdata/isfreset" ,"get","mfc isfreset");
setattr("/runtime/tmpdevdata/plcnpw" ,"get","devdata get -e plcnpw");
setattr("/runtime/tmpdevdata/password" ,"get","devdata get -e password");
setattr("/runtime/tmpdevdata/sn" ,"get","devdata get -e sn");
setattr("/runtime/tmpdevdata/pin" ,"get","devdata get -e pin");

function changes_default_wifi($phyinfuid,$ssid,$password,$mac)
{
	$n_root_phyinf=XNODE_getpathbytarget("", "phyinf", "uid", $phyinfuid, 0);
	if($n_root_phyinf=='') {
		return;
	}
	$wifi=XNODE_getpathbytarget("/wifi", "entry", "uid", query($n_root_phyinf."/wifi"), 0);
	if($wifi=='') {
		return;
	}
	$active=query($n_root_phyinf."/active");
	TRACE_debug('changes_default_wifi:'.$phyinfuid.','.$active.','.$ssid.','.$password.','.$mac.')');

	anchor($wifi);

	if($active == 0) {
		set("authtype","WPA+2PSK");
		set("encrtype","TKIP+AES");
		set("wps/configured","1");
		set("nwkey/psk/passphrase","1");
		set("nwkey/wpa/groupintv","3600");
		set("nwkey/rekey/gtk","1800");		
		set("ssid", "");
		set("nwkey/psk/key", "");
	} else {
		if($password == "") {
			TRACE_error("the mfc do not init wifi password,using default"); 
		} else {
			if($ssid == "") {
				$n5 = cut($mac, 4, ":");
				$n6 = cut($mac, 5, ":");
				$ssidsuffix = $n5.$n6;
				$ssid           = query("ssid");
				if($ssidsuffix!="") {               
					//For dlink product, make the default SSID "dlink-mac" for 2.4GHz and "dlink-5GHz-mac" for 5GHz.
					if(substr($ssid, 0, 5) == "dlink") {
						$ssid1  = "dlink";
						$ssid2  = scut($ssid, 0, "dlink");
						$ssid   = $ssid1."-".toupper($ssidsuffix).$ssid2;
					}
				}
			}
			set("authtype","WPA+2PSK");
			set("encrtype","TKIP+AES");
			set("wps/configured","1");
			set("nwkey/psk/passphrase","1");
			set("nwkey/wpa/groupintv","3600");
			set("nwkey/rekey/gtk","1800");				
			set("ssid", $ssid);
			set("nwkey/psk/key", $password);
		}
	}
}

function default_extender()
{
	set("/device/wirelessmode", "manual");
	$brp = XNODE_getpathbytarget("", "inf", "uid", "BRIDGE-1", 0);
	if ($brp != ""){	set($brp."/dhcps4", "DHCPS4-1");}
	$ext2p = XNODE_getpathbytarget("", "phyinf", "uid", "WIFI-STA", 0);
	if ($ext2p != ""){	set($ext2p."/active", "1");		}
	$ext5p = XNODE_getpathbytarget("", "phyinf", "uid", "WIFI-STA5G", 0);
	if ($ext5p != ""){	set($ext5p."/active", "1");		}
	$wifip = XNODE_getpathbytarget("wifi", "entry", "uid", "WIFI-5", 0);
	if ($wifip != ""){
		set($wifip."/opmode", "REPEATER");
		set($wifip."/authtype", "WPA2PSK");
		set($wifip."/encrtype", "AES");
	}
}

$wifipassword = query("/runtime/tmpdevdata/wifipassword");
$wifiverify = query("/runtime/devdata/wifiverify");
if($wifiverify=="1") $wifipassword = "";

if (query("/runtime/tmpdevdata/isfreset")=="YES"
  ||query("/runtime/devdata/isfreset")=="YES"){
    $isfreset = "YES";
}

if ($isfreset=="YES"){
	$wlanmac = query("/runtime/tmpdevdata/wlanmac");
	$ssid = query("/runtime/tmpdevdata/ssid_2G");
	changes_default_wifi("BAND24G-1.1",$ssid,$wifipassword,$wlanmac);

	//here should get wlan name
	$wlanmac = query("/runtime/tmpdevdata/wlanmac5");
	$ssid = query("/runtime/tmpdevdata/ssid_5G");
	changes_default_wifi("BAND5G-1.1",$ssid,$wifipassword,$wlanmac);

	//Add repeater and client initial
	$wlanmac = query("/runtime/tmpdevdata/wlanmac");
	$ssid = "";
	$wifipassword = "";
	changes_default_wifi("WIFI-STA",$ssid,$wifipassword,$wlanmac);
}

$plcnpw = query("/runtime/tmpdevdata/plcnpw");
if($plcnpw!="00000000" && strlen($plcnpw)>=8) {
	set("/device/plcnode/networkname", $plcnpw);
	set("/device/plcnode/privateradio", "1");
}

$password = query("/runtime/tmpdevdata/password");
if($password=="00000000" || strlen($password)<8) {
	set("/device/account/entry/password", "");
} else {
	set("/device/account/entry/password", $password);
}

$sn = query("/runtime/tmpdevdata/sn");
if($sn=="00000000" || strlen($sn)<8) {
	$lanmac = query("/runtime/tmpdevdata/lanmac");
	set("/runtime/devdata/sn" , $lanmac);
} else {
	set("/runtime/devdata/sn" , $sn);
}

$pin = query("/runtime/tmpdevdata/pin");
if($pin=="00000000" || strlen($pin)<8) {
	set("/runtime/devdata/pin" , "");
} else {
	set("/runtime/devdata/pin" , $pin);
}

/*remove links*/
del("/runtime/tmpdevdata");
?>

