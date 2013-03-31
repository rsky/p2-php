<?php
if ($_SERVER['SCRIPT_FILENAME'] === __FILE__) {
     header('Location: /');
} elseif (preg_match('@^(/js/\\w+\\.js)(\\?.*)?$@', $_SERVER['REQUEST_URI'], $matches)) {
    // JavaScript‚Ì•¶Žš‰»‚¯‚ð–h‚®‚½‚ß
    header('Content-Type: text/javascript; charset=Shift_JIS');
    readfile($_SERVER['DOCUMENT_ROOT'] . $matches[1]);
} else {
    return false;
}
