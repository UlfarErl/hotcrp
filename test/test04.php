<?php
// test04.php -- HotCRP user database tests
// HotCRP is Copyright (c) 2006-2015 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

global $ConfSitePATH;
$ConfSitePATH = preg_replace(",/[^/]+/[^/]+$,", "", __FILE__);

global $Opt;
$Opt = array();

function reset_cdb($active) {
    global $Conf, $Opt;
    if ($active) {
        $Opt["contactdb_dsn"] = "mysql://hotcrp_testdb:m5LuaN23j26g@localhost/hotcrp_testdb_cdb";
        $Opt["contactdb_passwordHmacKeyid"] = "c1";
    } else
        unset($Opt["contactdb_dsn"]);
    $Conf && Contact::contactdb(true);
}

reset_cdb(true);
require_once("$ConfSitePATH/test/setup.php");

function user($email) {
    return Contact::find_by_email($email);
}

function password($email, $iscdb = false) {
    global $Conf;
    $dblink = $iscdb ? Contact::contactdb() : $Conf->dblink;
    $result = Dbl::qe($dblink, "select password from ContactInfo where email=?", $email);
    $row = Dbl::fetch_first_row($result);
    return $row[0];
}

function save_password($email, $encoded_password, $iscdb = false) {
    global $Conf, $Now;
    $dblink = $iscdb ? Contact::contactdb() : $Conf->dblink;
    Dbl::qe($dblink, "update ContactInfo set password=?, passwordTime=? where email=?", $encoded_password, $Now, $email);
    if (!$iscdb)
        Dbl::qe($dblink, "update ContactInfo set passwordIsCdb=0 where email=?", $email);
    ++$Now;
}

$marina = "marina@poema.ru";

$Opt["safePasswords"] = $Opt["contactdb_safePasswords"] = 0;
user($marina)->change_password(null, "rosdevitch", 0);
xassert_eqq(password($marina), "rosdevitch");
xassert_eqq(password($marina, true), "rosdevitch");
xassert(user($marina)->check_password("rosdevitch"));

// different password in localdb => both passwords work
save_password($marina, "crapdevitch", false);
xassert(user($marina)->check_password("crapdevitch"));
xassert(user($marina)->check_password("rosdevitch"));

// change local password => only local password changes
user($marina)->change_password("crapdevitch", "assdevitch", 0);
xassert(user($marina)->check_password("assdevitch"));
xassert(user($marina)->check_password("rosdevitch"));
xassert(!user($marina)->check_password("crapdevitch"));

// change contactdb password => both passwords change
user($marina)->change_password("rosdevitch", "dungdevitch", 0);
xassert(user($marina)->check_password("dungdevitch"));
xassert(!user($marina)->check_password("assdevitch"));
xassert(!user($marina)->check_password("rosdevitch"));
xassert(!user($marina)->check_password("crapdevitch"));

// update contactdb password => old local password useless
save_password($marina, "isdevitch", true);
xassert_eqq(password($marina), "dungdevitch");
xassert(user($marina)->check_password("isdevitch"));
xassert(!user($marina)->check_password("dungdevitch"));

// update local password only
user($marina)->change_password(null, "ncurses", Contact::CHANGE_PASSWORD_NO_CDB);
xassert_eqq(password($marina), "ncurses");
xassert_eqq(password($marina, true), "isdevitch");
xassert(user($marina)->check_password("ncurses"));
xassert(user($marina)->check_password("isdevitch"));
xassert(user($marina)->check_password("ncurses"));

// null contactdb password => can log in locally
save_password($marina, null, true);
xassert(user($marina)->check_password("ncurses"));

// restore to "this is a cdb password"
save_password($marina, "isdevitch", true);
Dbl::qe("update ContactInfo set passwordIsCdb=1 where email=?", $marina);

// start upgrading passwords
if (function_exists("password_needs_rehash")) {
    $Opt["safePasswords"] = $Opt["contactdb_safePasswords"] = 2;
    xassert(user($marina)->check_password("isdevitch"));
    xassert_eqq(substr(password($marina), 0, 2), " \$");
    xassert_eqq(substr(password($marina, true), 0, 2), " \$");
    xassert_eqq(password($marina), password($marina, true));

    save_password($marina, ' $$2y$10$/URgqlFgQHpfE6mg4NzJhOZbg9Cc2cng58pA4cikzRD9F0qIuygnm', true);
    save_password($marina, '*', false);
    xassert(user($marina)->check_password("isdevitch"));
    xassert_eqq(password($marina, true), ' $$2y$10$/URgqlFgQHpfE6mg4NzJhOZbg9Cc2cng58pA4cikzRD9F0qIuygnm');
}

// insert someone into the contactdb
$result = Dbl::qe(Contact::contactdb(), "insert into ContactInfo (firstName, lastName, email, affiliation, password) values ('Te', 'Thamrongrattanarit', 'te@_.com', 'Brandeis University', ' $$2y$10$/URgqlFgQHpfE6mg4NzJhOZbg9Cc2cng58pA4cikzRD9F0qIuygnm')");
assert(!!$result);
Dbl::free($result);
xassert(!user("te@_.com"));
$u = Contact::contactdb_find_by_email("te@_.com");
xassert(!!$u);
xassert_eqq($u->firstName, "Te");

// inserting them should succeed and borrow their data
$us = new UserStatus(array("send_email" => false));
$acct = $us->save((object) array("email" => "te@_.com"));
xassert(!!$acct);
$te = user("te@_.com");
xassert(!!$te);
xassert_eqq($te->firstName, "Te");
xassert_eqq($te->lastName, "Thamrongrattanarit");
xassert_eqq($te->affiliation, "Brandeis University");
if (function_exists("password_needs_rehash"))
    xassert($te->check_password("isdevitch"));

xassert_exit();
