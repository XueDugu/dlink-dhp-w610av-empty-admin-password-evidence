# D-Link DHP-W610AV - HTTP management account "Admin" accepts an empty password when the device-data password is missing or set to the sentinel

D-Link DHP-W610AV firmware 1.02EUb03 pre-provisions an administrative account "Admin" with an empty password
(`etc/defnodes/defaultvalue.xml`), and the initialisation script `etc/defnodes/defaultvalue.php` keeps that
empty password whenever the device-data password is missing, equal to the sentinel "00000000", or shorter
than 8 bytes:

    $password = query("/runtime/tmpdevdata/password");
    if($password=="00000000" || strlen($password)<8) {
        set("/device/account/entry/password", "");        // empty-password branch
    } else {
        set("/device/account/entry/password", $password);
    }

At boot, `service DEVICE.ACCOUNT start` writes the account into the runtime database that the HTTP
management server actually uses ("Admin" "" "0" "0" -> /tmp/passwd -> /var/passwd, plus
`/var/etc/hnapasswd` = `Admin:` for the HNAP path). The HTTP management service starts by default when the
bridge interface comes up, and its `session.cgi` handler (`/htdocs/cgibin`) authenticates through the
`login_prepare_hnap` / `login_hnap` challenge-response protocol. With the empty-password branch active, an
unauthenticated attacker who can reach the management interface completes the normal login with the
built-in account "Admin" and an empty password and receives an authorised management session
(AUTHORIZED_GROUP = 0). CAPTCHA is disabled by default (`/device/session/captcha = 0`).

The condition matters: a unit carrying a valid unique device-data password (>= 8 bytes, not "00000000")
uses that value instead and is not affected by this branch.

## Verification

The empty password completes the shipped challenge-response login:

    POST /session.cgi  ACTION=login_prepare_hnap&USER=Admin   -> 200 SUCCESS (Challenge/PublicKey/Cookie)
    md5("") = d41d8cd98f00b204e9800998ecf8427e
    POST /session.cgi  ACTION=login_hnap&USER=Admin&PASSWD=<derived>&CAPTCHA=&UID=<cookie>
        -> 200 <RESULT>SUCCESS</RESULT><AUTHORIZED_GROUP>0</AUTHORIZED_GROUP>

Negative control: the same flow with a wrong password returns
`<RESULT>INVALIDPASSWD</RESULT><AUTHORIZED_GROUP>-1</AUTHORIZED_GROUP>`, so the acceptance of the empty
password is a real credential check performed by the vendor CGI and not a blanket authentication bypass.
The vendor CGI also wrote a 376-byte authenticated session record for the successful login.

Evidence: [`evidence/03-runtime-verification.txt`](evidence/03-runtime-verification.txt),
[`evidence/04-http-capture-summary.txt`](evidence/04-http-capture-summary.txt) and the packet capture
[`evidence/login_hnap.pcap`](evidence/login_hnap.pcap).

## Proof of concept

```sh
python3 poc/poc_dhp610av_login.py http://<device-ip> Admin ""               # SUCCESS, AUTHORIZED_GROUP=0
python3 poc/poc_dhp610av_login.py http://<device-ip> Admin notthepassword   # INVALIDPASSWD, -1
```

## Firmware

- Product: DHP-W610AV (PowerLine AV2 600 + Wi-Fi, hardware revision A1), firmware 1.02EUb03
- Package: `DHP-W610AV_fw_reva_102eub03_EU_multi_20180504.zip`
  - sha256 `092f435e2c98685da166505eec7fb151669c6c1bf7f8b70b8b8f07b4df21247c`
  - download <https://ftp.dlink.de/dhp/dhp-w610av/driver_software/DHP-W610AV_fw_reva_102eub03_EU_multi_20180504.zip>
  - inner image `DHPW610AVA1_FW102EUb03.bin`, sha256 `6143d9ea3f1d46a8427d2e25ccc075357f663c0aef2d5aa2996b73243a1a7e16`
    (D-Link DLOB container, signature `hpw610av_dlob_w610av_EU`, SquashFS at 0x160090)
- Previous release `1.01EUb07` (`DHP-W610AV_fw_reva_101eub07_EU_en_20171019.zip`,
  sha256 `dadc661d6ce376024b7130b46b3857c9211dea2de2823ab846bdca2b7b0794b0`) stores the account password as
  `d41d8cd98f00b204e9800998ecf8427e` (= MD5 of the empty string) with `<encrypted>1</encrypted>`, i.e. it
  ships an empty administrator password unconditionally.

## Classification

CWE-258 Empty Password in Configuration File; CWE-1392 Use of Default Credentials; CWE-1188 Insecure
Default Initialization. CVSS 3.1: `CVSS:3.1/AV:A/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H` (8.8).

## Vendor coordination

Reported to D-Link SIRT (security@dlink.com). The model is listed by D-Link as end-of-life / out of support.
