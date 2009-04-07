<?php
// token.php
// admin page for looking up login tokens
// i.e. missing confirmation email etc...

// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/misc.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';
require_once '../../phplib/rabx.php';
require_once '../phplib/adm.php';


$email = get_http_var( 'email', '' );
$action = get_http_var( 'action' );

admPageHeader();
print "<h2>Look up confirmation tokens</h2>\n";

switch( $action )
{
    case 'toklookup':
        EmitTokenLookupForm( $email );
        if( $email )
        {
            print "<hr>\n";
            LookupToken( $email );
        }
        break;
    default:
        EmitTokenLookupForm( $email );
        break;
}

admPageFooter();

/********************************/

function EmitTokenLookupForm( $email )
{

?>

<p>
Look for confirmation tokens issued to an email address.
</p>
<p>
Often, confirmation emails will get classified as junk by the
users mail system (or even worse, just silently dropped by whatever
dodgy webmail provider they use...)
</p>

<p>
So, if a user complains about not receiving a confirmation
email, you can look up the tokens they've been issued here,
and manually resend them a confirmation link.
</p>

<form method="get" action="">
<input type="hidden" name="action" value="toklookup" />
Email address: <input type="text" name="email" size="40" value="<?php echo $email; ?>" /><br />
<input type="submit" name="submit" value="Look up" />
</form>
<?php

}

function LookupToken( $email )
{

    $sql = <<<EOT
SELECT token,created,data
    FROM token
    WHERE scope='login' AND encode( data, 'escape' ) ilike ?
    ORDER BY created DESC
EOT;

    $q = db_query( $sql, '%'.$email.'%' );

    $cnt = db_num_rows($q);
    if( $cnt == 0 )
    {
        print( "<p>No tokens found for <code>{$email}</code> (maybe they used a different email address?)</p>\n" );
    }
    else
    {
        print("<p>Found {$cnt} tokens for <code>{$email}</code> (most recent first)</p>\n" );

        print( "<table border=1>\n" );
        print( "<tr><th>when issued</th><th>email</th><th>confirmation link</th><th>stashed url</th></tr>\n" );

        while( $r = db_fetch_array($q) )
        {
            $t = strtotime($r['created']);
            $issued = strftime('%R %a %e %B %Y',$t);
            $token = $r['token'];
            $confirmation_url = OPTION_BASE_URL . "/login?t={$token}";

            $stashed_url = '????';
            $email = '????';

            $pos = 0;
            $res = rabx_wire_rd( &$r['data'], &$pos );
            if( !rabx_is_error( $res ) ) {
                $email = $res['email'];
                $stashed_url = db_getOne( "SELECT url FROM requeststash WHERE key=?", $res['stash'] );
                if( !$stashed_url ) {
                    $stashed_url = '-none- (which probably means they clicked the link)';
                }
            }
?>
<tr>
  <td><?php echo $issued;?></td>
  <td><code><?php echo $email; ?></code></td>
  <td><code><?php echo $confirmation_url; ?></code></td>
  <td><code><?php echo $stashed_url; ?></code></td>
</tr>
<?php
        }
        print( "</table>\n" );
    }
}


