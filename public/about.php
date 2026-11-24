<?php

require_once(__DIR__ . "/../includes/config.php");
require_once(__DIR__ . "/../includes/import.php");

render("about.php", [
    "title" => "About",
    "description" => "What Ethical Buy is, where its information comes from and how the scores are worked out.",
]);
