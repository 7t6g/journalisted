<?php

/* helper functions for admin pages */

// Error display
require_once "../../phplib/error.php";
function admin_display_error($num, $message, $file, $line, $context) {
    print "<p><strong>$message</strong> in $file:$line</p>";
}
err_set_handler_display('admin_display_error');



function admPageHeader()
{
	header( 'Content-Type: text/html; charset=utf-8' );

?>
<html>
<head>
<title>journa-list admin</title>
</head>
<body>
<h1>journa-list admin</h1>
<a href="article">Find articles</a> |
<a href="scrape">Scrape</a>
<hr>
<?php

}




function admPageFooter()
{
?>

</body>
</html>
<?php
}


