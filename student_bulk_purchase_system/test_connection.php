<?php

require_once "config/database.php";

echo "DATABASE CONNECTION FILE LOADED<br>";

if ($conn) {
    echo "DATABASE CONNECTION SUCCESSFUL";
} else {
    echo "DATABASE CONNECTION FAILED";
}

?>