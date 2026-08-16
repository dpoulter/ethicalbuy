<?php

require_once(__DIR__ . "/../../includes/config.php");

require_admin();

$search = input_string($_GET, "q");
$brands = admin_list_brands($search);

if ($brands === false)
{
    apologize("We could not load the brand list just now. Please try again shortly.");
}

// flash messages, set by the redirect after a successful write
$flash = "";
if (isset($_GET["created"])) { $flash = "Brand created."; }
else if (isset($_GET["updated"])) { $flash = "Brand updated."; }
else if (isset($_GET["deleted"])) { $flash = "Brand deleted."; }

render("admin/list.php", [
    "title" => "Admin",
    "brands" => $brands,
    "search" => $search,
    "flash" => $flash,
]);
