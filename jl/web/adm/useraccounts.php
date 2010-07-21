<?php
// page for managing user accounts

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../../phplib/person.php';
require_once '../phplib/adm.php';


admPageHeader( "User Accounts" );

$person_id = get_http_var( 'person_id' );
$action = get_http_var( 'action', $person_id ? 'view':'find' );


?>
<h2>User Accounts</h2>
<a href="/adm/useraccounts?action=find">Find account</a> |
<a href="/adm/useraccounts?action=create">Create a new account</a>
<?php

if( $action=='view' ) {
    // view an individual account
    emit_details( $person_id );
} elseif( $action == 'addperm' ) {
    // add a permission
    do_addperm();
} elseif( $action == 'removeperm' ) {
    // remove a permission
    do_removeperm();
} elseif( $action == 'changeemail' ) {
    // form for change email address
    do_changeemail();
} elseif( $action == 'reallychangeemail' ) {
    // actually set new email address
    do_reallychangeemail();
} elseif( $action == 'create' ) {
    // show form to create a new account
    do_create();
} elseif( $action == 'reallycreate' ) {
    // actually create a new account
    do_reallycreate();
} elseif( $action == 'generate_token' ) {
    // generate a login token
    do_generate_token();
} elseif( $action == 'newsletter_subscribe' ) {
    do_newsletter_subscribe();
} elseif( $action == 'newsletter_unsubscribe' ) {
    do_newsletter_unsubscribe();
} else {    // $action=='find'
    $email = get_http_var( 'email','' );
    if( $email ) {
        do_find( $email );
    }   else {
?>
<h3>Look up account</h3>
<?php
        emit_lookup_form();
    }
}

admPageFooter();


function do_find( $email )
{

?>
<h3>Find users matching '<?php echo htmlentities($email); ?>'</h3>
<?php

    $pat = '%'.$email.'%';
    $people = db_getAll( 'SELECT * FROM person WHERE email ilike ?', $pat );

?>
<p><?php echo sizeof( $people ); ?> matches:</p>
<ul>
<?php foreach( $people as $p ) { ?>
<li><a href="/adm/useraccounts?person_id=<?php echo $p['id'];?>"><?php echo $p['email'];?></a></li>
<?php } ?>
</ul>
<?php

}


function emit_lookup_form()
{
    $email = get_http_var( 'email','' );
?>
<form method="POST" action="/adm/useraccounts">
<label for="email">Email:</label>
<input type="text" id="email" name="email" value="<?php echo htmlentities( $email ); ?>" />
<button name="action" value="find">Find</button>
</form>
<?php
}


function do_create()
{
    $email = get_http_var( 'email','' );
    $name = get_http_var( 'name','' );

?>
<h3>Create a new account</h3>
<form method="POST" action="/adm/useraccounts">
<label for="email">Email:</label> <input type="text" id="email" name="email" value="<?php echo htmlentities( $email ); ?>" /><br/>
<label for="name">Name:</label> <input type="text" id="name" name="name" value="<?php echo htmlentities( $name ); ?>" />
<button name="action" value="reallycreate">Create</button>
</form>
<?php
}

function do_reallycreate()
{
    $email = get_http_var( 'email','' );
    $name = get_http_var( 'name','' );
    $person = person_get( $email );
    if( $person ) {
?>
<div class="action_error">
Already an account with that email address.<br/>
see <a href="/adm/useraccounts?person_id=<?php echo $person->id(); ?>">here</a>.
</div>
<?php
        return;
    }

    $person = person_get_or_create( $email, $name );
    db_commit();
?>
<div class="action_summary">
New account created.
</div>
<?php

    emit_details( $person->id() );
}



function emit_details( $person_id )
{
    // general
    $p = db_getRow( "SELECT * FROM person WHERE id=?", $person_id );


    $subscribed_to_newsletter = false;
    if( db_getOne( "SELECT person_id FROM person_receives_newsletter WHERE person_id=?", $person_id ) ) {
        $subscribed_to_newsletter = true;
    }

?>
<h3>viewing user: '<?php echo $p['email'];?>'</h3>
[<a href="/adm/useraccounts?person_id=<? echo $person_id;?>&action=changeemail">Change email address</a>]<br/>
<br/>
id: <?php echo $p['id']; ?><br/>
name: <?php echo $p['name'] ? $p['name'] : "-blank-"; ?><br/>
<?php if( $p['password'] ) { ?>Password is set <?php } else { ?>No password set<?php } ?><br/>
Logged in <?php echo $p['numlogins'];?> times<br/>
<?php

    // show newsletter subscription
?>
<h4>newsletter</h4>
status:
<?php if( $subscribed_to_newsletter ) { ?>
<em>Subscribed</em>
<small>[<a href="/adm/useraccounts?person_id=<?= $person_id;?>&action=newsletter_unsubscribe">unsubscribe</a>]</small>
<?php } else { ?>
<em>not subscribed</em>
<small>[<a href="/adm/useraccounts?person_id=<?= $person_id;?>&action=newsletter_subscribe">subscribe</a>]</small>
<?php } ?>
<?php

    // show alerts
    $sql = <<<EOT
SELECT j.ref,j.prettyname,j.oneliner FROM ((alert a INNER JOIN person p ON a.person_id=p.id) INNER JOIN journo j ON a.journo_id=j.id) WHERE p.id=?;
EOT;
    $alerts = db_getAll( $sql, $person_id );

?>
<h4>alerts</h4>
<?echo sizeof( $alerts ) ?> alerts set up:
<ul>
<?php foreach( $alerts as $a ) { ?>
<li><?php echo admJournoLink($a['ref'], $a['prettyname'] ); ?> (<?php echo $a['oneliner']; ?>)</li>
<?php } ?>
</ul>

<h4>Permissions</h4>
<?php
    // show permissions
    $sql = <<<EOT
SELECT p.id, p.permission, j.ref as journo_ref FROM (person_permission p LEFT JOIN journo j ON j.id=p.journo_id)
    WHERE person_id=?
EOT;
    $perms = db_getAll( $sql, $person_id );
    if( $perms ) {
?>
<ul>
<?php foreach( $perms as $perm ) { ?>
<li>
Can <em><?php echo $perm['permission']; ?></em>
 <?php echo is_null($perm['journo_ref']) ? '':admJournoLink($perm['journo_ref']); ?>
<small>[<a href="/adm/useraccounts?person_id=<? echo $person_id;?>&action=removeperm&perm_id=<?php echo $perm['id']; ?>">remove</a>]</small>
</li>
<?php } ?>
</ul>
<?php
    } else {
?>
<p>No permissions assigned</p>
<?php
    }
emit_addperm_form( $person_id );
?>
<h4>Generate a login link</h4>
<p>This creates a link to allow a user to log in directly</p>
<form method="POST" action="/adm/useraccounts">
<input type="hidden" name="person_id" value="<?php echo $person_id; ?>" />
<label for="login_dest">Login destination:</label>
<select id="login_dest" name="login_dest">
  <option selected value="/profile">/profile</option>
  <option value="/alert">/alert</option>
</select>
<button name="action" value="generate_token">Generate</button>
</form>
<?php



}



function emit_addperm_form( $person_id )
{


?>
Add edit permission:
<form method="POST" action="/adm/useraccounts">
<input type="hidden" name="person_id" value="<?php echo $person_id; ?>" />
<label for="journo_ref">journo <small>(eg "fred-bloggs")</small></label><input class="ajax-ref-lookup" type="text" id="journo_ref" name="journo_ref" value="" />
<button name="action" value="addperm">Add</button>
</form>

<script type="text/JavaScript">
$("#journo_ref").autocomplete("ajax-ref-lookup.php");
</script>
<?php
}


function do_addperm()
{
    $perm = "edit";
    $person_id = get_http_var('person_id');
    $journo_ref = get_http_var('journo_ref');

    $journo_id = db_getOne( "SELECT id FROM journo WHERE ref=?", $journo_ref );

    if( !$journo_id ) {
?>
<div class="action_error">
Journo '<?php echo $journo_ref; ?>' not found.
</div>
<?php
        return;
    }

    db_do( "INSERT INTO person_permission (person_id,journo_id,permission) VALUES (?,?,?);",
        $person_id,
        $journo_id,
        $perm );
    db_commit();

?>
<div class="action_summary">
Added '<?php echo $perm; ?>' permission on <?php echo $journo_ref; ?>.
</div>
<?php

    emit_details( $person_id );
}

function do_removeperm()
{
    $person_id = get_http_var('person_id');
    $perm_id = get_http_var('perm_id');
    $p = db_getRow( "SELECT p.permission,j.ref as journo_ref FROM ( person_permission p LEFT JOIN journo j ON j.id=p.journo_id) WHERE p.id=?", $perm_id);
    if( !$p )
        return;

    db_do( "DELETE FROM person_permission WHERE id=?", $perm_id );
    db_commit();

?>
<div class="action_summary">
Revoked '<?php echo $p['permission']; ?>' permission on <?php echo $p['journo_ref']; ?>.
</div>
<?php

    emit_details( $person_id );
}


function do_changeemail()
{
    $person_id = get_http_var('person_id');
    $person = db_getRow( "SELECT * FROM person WHERE id=?", $person_id );
?>
<h3>Change email address</h3>
<p><em>
NOTE:<br/>
remember that email from: fields can be easily faked!<br/>
Ideally, you should send a request for confirmation to the OLD address and make sure you
get a confirmation back before changing anything...
</em>
</p>

<p>Current email address: <code><?php echo htmlentities( $person['email'] ); ?></code></p>
<form method="POST" action="/adm/useraccounts">
<input type="hidden" id="person_id" name="person_id" value="<?php echo $person['id'];?>" />
<label for="new_email">New email address:</label>
<input id="new_email" name="new_email" value="<?php echo htmlentities( $person['email'] );?>" />
<button type="submit" name="action" value="reallychangeemail">Change</button>
</form>
<a href="/adm/useraccounts?person_id=<?php echo $person_id; ?>">back</a>
<?php

}

function do_newsletter_subscribe() {
    $person_id = get_http_var( "person_id" );
    db_do("DELETE FROM person_receives_newsletter WHERE person_id=?", $person_id );
    db_do("INSERT INTO person_receives_newsletter (person_id) VALUES (?)", $person_id );
    db_commit();
?>
<div class="action_summary">
subscribed to newsletter
</div>
<?php

    emit_details( $person_id );
}

function do_newsletter_unsubscribe() {
    $person_id = get_http_var( "person_id" );
    db_do("DELETE FROM person_receives_newsletter WHERE person_id=?", $person_id );
    db_commit();
?>
<div class="action_summary">
unsubscribed from newsletter
</div>
<?php

    emit_details( $person_id );
}



function do_reallychangeemail()
{
    $person_id = get_http_var( "person_id" );
    $person = db_getRow( "SELECT * FROM person WHERE id=?", get_http_var('person_id') );
    $old_email = $person['email'];
    $new_email = get_http_var( "new_email" );

    db_do("UPDATE person SET email=? WHERE id=?", $new_email, $person_id );
    db_commit();

?>
<div class="action_summary">
Changed email address<br/>from: <code><?php echo $old_email; ?><br/></code> to: <code><?php echo $new_email; ?></code>
</div>
<?php

    emit_details( $person_id );
}



// generate a login token link
function do_generate_token()
{
    $person_id = get_http_var( "person_id" );
    $login_dest = get_http_var( "login_dest" );

    $person = db_getRow( "SELECT * FROM person WHERE id=?", get_http_var('person_id') );

    $template_data = array();
    $url = person_make_signon_url(
        rabx_serialise($template_data),
        $person['email'],
        "GET",
        $login_dest,
        null );
    db_commit();
?>
<p>Here is your login link:</p>
<p><a href="<?=$url;?>"><?=$url;?></a></p>
<p>(This will log in as <code><?=$person['email'];?></code> and go the <code><?=$login_dest;?></code> page)</p>
<?php


}


?>
