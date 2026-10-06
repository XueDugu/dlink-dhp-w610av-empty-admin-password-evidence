#!/usr/bin/env python3
"""DHP-W610AV (1.02EUb03) session.cgi login_hnap PoC - vendor challenge/response, no bypass.

Mirrors htdocs/web/js/postxml.js + htdocs/webinc/templates.php:
  key            = PublicKey + hex_md5(plain_password)
  PrivateKey     = HMAC_MD5(key=key,        msg=Challenge).upper()
  Login_Passwd   = HMAC_MD5(key=PrivateKey, msg=Challenge).upper()
  POST session.cgi REPORT_METHOD=xml&ACTION=login_hnap&USER=..&PASSWD=<Login_Passwd>&CAPTCHA=&UID=<Cookie>

Usage:
  poc_dhp610av_login.py <base-url> [<source-ip>] [user] [password]
  poc_dhp610av_login.py http://192.168.0.50            Admin ""              # SUCCESS
  poc_dhp610av_login.py http://192.168.0.50            Admin notthepassword  # INVALIDPASSWD
"""
import hashlib
import hmac
import re
import sys

import requests


def hmac_md5_hex(key: str, data: str) -> str:
    return hmac.new(key.encode(), data.encode(), hashlib.md5).hexdigest()


def main(base, user, password, source_ip=None, verbose=True):
    s = requests.Session()
    s.headers.update({"Referer": base + "/"})

    class BoundHTTPAdapter(requests.adapters.HTTPAdapter):
        def init_poolmanager(self, *a, **k):
            k["source_address"] = (source_ip, 0)
            return super().init_poolmanager(*a, **k)

    if source_ip:
        s.mount("http://", BoundHTTPAdapter())

    r = s.get(base + "/", timeout=10)
    if verbose:
        print(f"[1] GET /                       -> {r.status_code}")

    r = s.post(base + "/session.cgi",
               data={"REPORT_METHOD": "xml", "ACTION": "login_prepare_hnap", "USER": user},
               timeout=10)
    xml = r.text
    if verbose:
        print(f"[2] login_prepare_hnap          -> {r.status_code} {xml.replace(chr(10), ' ')[:150]}")

    def g(tag):
        m = re.search(rf"<{tag}>([^<]*)</{tag}>", xml)
        return m.group(1) if m else None

    result, challenge, cookie, public_key = g("RESULT"), g("Challenge"), g("Cookie"), g("PublicKey")
    if result != "SUCCESS":
        raise SystemExit(f"login_prepare failed: {result}")

    if verbose:
        print(f"    Challenge={challenge}  PublicKey={public_key}  Cookie={cookie}")

    key = public_key + hashlib.md5(password.encode()).hexdigest()
    private_key = hmac_md5_hex(key, challenge).upper()
    login_passwd = hmac_md5_hex(private_key, challenge).upper()
    if verbose:
        print(f"    md5(password)={hashlib.md5(password.encode()).hexdigest()}  PrivateKey={private_key}")
        print(f"    LOGIN PASSWD={login_passwd}")

    r = s.post(base + "/session.cgi",
               data={"REPORT_METHOD": "xml", "ACTION": "login_hnap", "USER": user,
                     "PASSWD": login_passwd, "CAPTCHA": "", "UID": cookie},
               timeout=10)
    xml2 = r.text
    if verbose:
        print(f"[3] login_hnap {user}/{password!r:<9} -> {r.status_code} {xml2.replace(chr(10), ' ')[:180]}")

    res2 = re.search(r"<RESULT>([^<]*)</RESULT>", xml2)
    grp = re.search(r"<AUTHORIZED_GROUP>([^<]*)</AUTHORIZED_GROUP>", xml2)
    ok = res2 and res2.group(1) == "SUCCESS"
    print(f"\n==> {user}/{password!r}: {'AUTHENTICATED' if ok else 'REJECTED'}"
          + (f", AUTHORIZED_GROUP={grp.group(1)}" if grp else ""))
    return ok


if __name__ == "__main__":
    base = sys.argv[1] if len(sys.argv) > 1 else "http://192.168.0.50"
    src = sys.argv[2] if len(sys.argv) > 2 else None
    user = sys.argv[3] if len(sys.argv) > 3 else "Admin"
    pw = sys.argv[4] if len(sys.argv) > 4 else ""
    sys.exit(0 if main(base, user, pw, src) else 1)
