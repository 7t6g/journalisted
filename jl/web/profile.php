<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/misc.php';
require_once '../phplib/gatso.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



/* get journo identifier (eg 'fred-bloggs') */

$P = person_if_signed_on();
$ref = strtolower( get_http_var( 'ref' ) );

$journo = NULL;
if( $ref ) {
    $journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND ref=?", $ref );
} else {
    // no journo given - if person is logged on, see if they are associated with a journo
    if( $P ) {
        $journo_id = db_getOne( "SELECT journo_id FROM person_permission WHERE person_id=? AND permission='edit'", $P->id() );
        if( $journo_id ) {
            $journo = db_getRow( "SELECT * FROM journo WHERE status='a' AND id=?", $journo_id );
        }
    }
}

if(!$journo)
{
    if($P ) {
        page_header("");
?>
Sorry, your account hasn't yet been linked to a journalist page.
<?php
        page_footer();
        exit;
    }
    header("HTTP/1.0 404 Not Found");
    exit(1);
}

// if logged in and have access, redirect.
if( $P ) {
    // is this person allowed to edit this journo?
    if( db_getOne( "SELECT id FROM person_permission WHERE person_id=? AND journo_id=? AND permission='edit'",
        $P->id(), $journo['id'] ) ) {
        // yes - just redirect to the first profile page
        header( "Location: /profile_admired?ref={$journo['ref']}" );
        exit();
    }
}



$title = "Edit profile for " . $journo['prettyname'];
page_header( $title );

?>
<h2>Edit profile for <a href="/<?=$journo['ref'];?>"><?=$journo['prettyname'];?></a></h2>

<p>It's easy to get set up to edit your profile! But we need to know you are who you say you are...</p>
Here's how:
<ol>
<li>blah blah</li>
<li>references</li>
<li>blah blah</li>
<li>email us</li>
<li>blah blah we'll send you a login link blah blah</li>
</ol>

<p>Jumped through all the hoops and have an account already? <a href="/profile_admired?ref=<?=$journo['ref'];?>">log in now!</a>
<?php

page_footer();


?>
