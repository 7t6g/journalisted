<?php
// sigh... stupid php include-path trainwreck...
chdir( dirname(dirname(__FILE__)) );

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/misc.php';
require_once '../phplib/cache.php';
require_once '../../phplib/db.php';
require_once '../phplib/adm.php';

require_once '../phplib/drongo-forms/forms.php';


// validator to ensure a journo is in the DB
class JournoValidator {
    public $msg = 'Please enter a valid journo ref';
    public $code = 'journo';
    function execute($value) {
        $journo_id = db_getOne("SELECT id FROM journo WHERE ref=?",$value);
        if(is_null($journo_id)) {
            $params = array();
            throw new ValidationError(vsprintf($this->msg,$params), $this->code, $params );
        }
    }
}

class PickJournoForm extends Form
{

    function __construct() {
        parent::__construct($_GET,array(),array());
        $this->fields['from_ref'] = new CharField(array(
            'max_length'=>200,
            'required'=>TRUE,
            'label'=>'Journo to split',
            'help_text'=>'eg fred-bloggs',
            'validators'=>array(array(new JournoValidator(),"execute")))
        );
    }
}



class SplitJournoForm extends Form
{

    function __construct($from_ref) {

        // get all publications this journo has written for, with article counts
        $pub_choices = array();
        $sql = <<<EOT
SELECT o.id, o.shortname, count(*)
    FROM (((organisation o
        INNER JOIN article a on o.id=a.srcorg)
        INNER JOIN journo_attr attr ON attr.article_id=a.id)
        INNER JOIN journo j ON j.id=attr.journo_id)
        WHERE j.ref=?
        GROUP BY o.id,o.shortname
        ORDER BY count DESC
EOT;
        foreach(db_getAll($sql, $from_ref) as $row) {
            $pub_choices[$row['id']] = sprintf("%s (%d articles)", $row['shortname'], $row['count']);
        }

        parent::__construct($_GET,array(),array());
        $this->fields['from_ref'] = new CharField(array(
            'max_length'=>200,
            'required'=>TRUE,
            'label'=>'Journo to split',
            'help_text'=>'eg fred-bloggs',
            'validators'=>array(array(new JournoValidator(),"execute")))
        );
        $this->fields['split_pubs'] = new MultipleChoiceField(array('choices'=>$pub_choices,'widget'=>'CheckboxSelectMultiple'));
        $this->fields['to_ref'] = new CharField(array(
            'max_length'=>200,
            'required'=>FALSE,
            'label'=>'Destination journo',
            'help_text'=>'eg fred-bloggs or leave blank to create a new journo',)
        );
    }
}


function view()
{
    $action = get_http_var('action');
    switch($action) {
        case 'preview':
            break;
        case 'confirm':
            break;
        default:
            $f = new PickJournoForm();
            if($f->is_valid()) {
                $from_ref = $f->cleaned_data['from_ref'];
                $f = new SplitJournoForm($from_ref);
                $f->is_valid();
                admPageHeader();
?>
<form action="" method="get">
<table>
<?= $f->as_table(); ?>
</table>
<input type="submit" />
</form>
<?php
                admPageFooter();
            } else {
                admPageHeader();
?>
<form action="" method="get">
<table>
<?= $f->as_table(); ?>
</table>
<input type="submit" />
</form>
<?php
                admPageFooter();
            }
            break;
    }

    

//    $vars = array();
//    template($vars);
}


function template($vars)
{
    extract($vars);

    admPageHeader();
?>
<h2>Split Journo</h2>
<?php

    if( $params['action'] == 'preview' )
    	EmitPreview( $params );
    elseif( $params['action'] == 'confirm' )
    	SplitJourno( $params );
    else
	    EmitForm( $params );

admPageFooter();
}



function FormParamsFromHTTPVars()
{
	$params = array();

	$params['from_ref'] = get_http_var( 'from_ref', '' );
	$params['new_from_ref'] = get_http_var( 'new_from_ref', '' );
	$params['split_orgids'] = get_http_var( 'split_orgids', array() );
	$params['to_ref'] = get_http_var( 'to_ref', '' );
	$params['action'] = get_http_var( 'action', '' );
	return $params;
}


function aget( $ar, $key, $defaultval=null )
{
	if( array_key_exists( $key, $ar ) )
		return $ar[$key];
	else
		return $defaultval;
}



function EmitForm( $params )
{
/*
	print"<pre>\n";
	print_r( $params );
	print"</pre>\n";
*/
?>
<form>
Which journo do you want to split up?<br />
<small>(use journo ref, eg 'fred-smith')</small><br />
<input type="text" name="from_ref" value="<?=$params['from_ref'];?>" /><br />
<br />
Articles from which outlets should be split out to the new journo?<br />
<?php

	$orgs = get_org_names();
	foreach( $orgs as $orgid=>$orgname )
	{
		if( in_array( $orgid, $params['split_orgids'] ) )
			$sel = 'checked';
		else
			$sel = '';
?>
<input type="checkbox" name="split_orgids[]" <?=$sel;?> value="<?=$orgid;?>" /> <?=$orgname;?><br />
<?php

	}

?>
<br />
Move to existing journo? (leave blank to create a new journo):<br />
<small>(eg 'fred-smythe')</small><br />
<input type="text" name="to_ref" value="<?=$params['to_ref'];?>" /><br />
<input type="hidden" name="action" value="preview" />
<input type="submit" value="Preview" /><br />
</form>
<?php

}


/* return a ref, stripped of it's number postfix (if any) */
function RefBase( $ref )
{
	$m = array();
	if( preg_match( '/^(.*?)(-\d+)?$/', $ref, &$m ) > 0 )
	{
		return $m[1];
	}
	return null;
}

/* return the numeric postfix of a ref, or null if none */
function RefNum( $ref )
{
	$m = array();
	if( preg_match( '/^(.*)-(\d+)$/', $ref, &$m ) > 0 )
	{
		return (int)$m[2];
	}
	return null;
}

/* search for an unused ref based on $baseref */
function NextFreeRef( $baseref, $startnum )
{
	$n = $startnum;
	while(1)
	{
		$ref = sprintf("%s-%d", $baseref,$n );
		if( db_getRow( "SELECT id FROM journo WHERE ref=?",$ref ) )
			++$n;			/* it's used */
		else
			return $ref;	/* it's free! */
	}
	/* never gets here... */
}

function EmitPreview( $params )
{
	$orgs = get_org_names();
	$journo=null;
	if( $params['from_ref'] )
		$journo = db_getRow( "SELECT id,prettyname FROM journo WHERE ref=?", $params['from_ref'] );
	if( !$journo )
	{
		printf( "<p>Can't find journo '%s'</p>\n", $params['from_ref'] );
		return;
	}

	if( $params['to_ref'] )
	{
		/* if a to_ref was set, make sure it exists! */
		if( !db_getRow( "SELECT id FROM journo WHERE ref=?", $params['to_ref'] ) )
		{
			printf( "<p>Can't find destination journo '%s'</p>\n", $params['to_ref'] );
			return;
		}
	}
	else
	{
		/* no to_ref, so we need to:
		 *
		 * a) add a number postfix (if it doesn't already
		 *    have one) to rename from_ref.
		 */
		$baseref = RefBase( $params['from_ref'] );
		$num = RefNum( $params['from_ref'] );
		if( $num === null )
		{
			/* add number postfix */
			$params['new_from_ref'] = NextFreeRef( $baseref, 1 );
			$num = RefNum( $params['new_from_ref'] ) + 1;
		}

		/*
		 * b) calculate an appropriate new ref for the (new)
		 *    dest journo.
		 */
		$params['to_ref'] = NextFreeRef( $baseref, $num );
	}

	printf("<h2>%s</h2>\n", $journo['prettyname'] );
	$r = db_query( "SELECT a.srcorg as orgid, COUNT(*) as numarticles ".
		"FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id) ".
		"WHERE attr.journo_id=? ".
		"GROUP BY a.srcorg",
		$journo['id'] );
	print( "<table border=1>\n" );
	print( "<tr><th>Outlet</th><th>Num articles</th><th>split out to new journo?</th></tr>\n" );
	while( $row = db_fetch_array( $r ) )
	{
		$orgid = $row['orgid'];
		if( in_array( $orgid, $params['split_orgids'] ) )
			$yesno = "YES";
		else
			$yesno = 'no';
		printf("<tr><td>%s</td><td>%d</td><td>%s</td></tr>\n", $orgs[$orgid], $row['numarticles'], $yesno );
	}
	print( "</table>\n" );


	print( "<ul>\n" );
	if( $params['new_from_ref'] )
	{
		printf( "<li>This will rename '%s' to '%s'</li>\n",
			$params['from_ref'], $params['new_from_ref'] );
	}
	if( !db_getRow( "SELECT id FROM journo WHERE ref=?", $params['to_ref'] ) )
	{
		printf( "<li>This will create new journo: '%s'</li>\n",
			$params['to_ref'] );
	}

	print( "</ul>\n" );

?>
<form>
<input type="hidden" name="from_ref" value="<?=$params['from_ref'];?>" />
<input type="hidden" name="new_from_ref" value="<?=$params['new_from_ref'];?>" />
<input type="hidden" name="to_ref" value="<?=$params['to_ref'];?>" />
<?php
	foreach( $params['split_orgids'] as $idx=>$val )
	{
?>
<input type="hidden" name="split_orgids[<?=$idx;?>]" value="<?=$val;?>" />
<?php
	}
?>
<input type="hidden" name="action" value="confirm" />
<input type="submit" value="Do it!" /><br />
</form>
<?php

}

/*
 * adds an 'id' field to $j
 */
function journoCreate( &$j )
{
	db_do( "INSERT INTO journo (ref,prettyname,firstname,lastname,firstname_metaphone,lastname_metaphone,status,created) VALUES (?,?,?,?,?,?,?,NOW())",
		$j['ref'],
		$j['prettyname'],
		$j['firstname'],
		$j['lastname'],
		metaphone($j['firstname'],4),
        metaphone($j['lastname'],4),
		$j['status'] );
	$j['id'] = db_getOne( "SELECT currval( 'journo_id_seq' )" );

// deprecated
	// TODO: should handle multiple aliases
//	$alias = $j['alias'];

//	db_do( "INSERT INTO journo_alias (journo_id,alias) VALUES (?,?)",
//		$j['id'], $alias );
}


function SplitJourno( $params )
{

	if( !$params['to_ref'] )
	{
		print "<p>ABORTED: no destination journo specified</p>\n";
		return;
	}

    print( "<div class=\"action_summary\">\nDone!\n<ul>\n" );

	/* do we want to change the ref of the from journo? */
	if( $params['new_from_ref'] )
	{
		db_do( "UPDATE journo SET ref=? WHERE ref=?",
			$params['new_from_ref'],
			$params['from_ref'] );
		$params['from_ref'] = $params['new_from_ref'];
	}


	$fromj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname,status FROM journo WHERE ref=?", $params['from_ref'] );

	$toj = db_getRow( "SELECT id,ref,prettyname,lastname,firstname,status FROM journo WHERE ref=?", $params['to_ref'] );
	if( !$toj )
	{
		// to_ref doesn't exist - Create New Journo!

		// just take a copy of 'from' journo
		$toj = $fromj;
		unset( $toj['id'] );
		$toj['ref'] = $params['to_ref'];

		// copy alias too (should be array really)
//		$alias = db_getOne( "SELECT alias FROM journo_alias WHERE journo_id=?", $fromj['id'] );
//		$toj['alias'] = $alias;

		// create the new journo
		journoCreate( $toj );

		printf("<li>Created new journo, '%s' (id=%d)</li>\n", $toj['ref'], $toj['id'] );
	}

	// move articles
	$orglist = implode( ',', $params['split_orgids'] );

    if( $orglist )
    {
    	$sql = <<<EOD
UPDATE journo_attr SET journo_id=?
	WHERE journo_id=? AND article_id IN
		(
		SELECT a.id
			FROM (article a INNER JOIN journo_attr attr ON a.id=attr.article_id)
			WHERE journo_id=? AND a.srcorg IN ({$orglist})
		)
EOD;

    	db_do( $sql, $toj['id'], $fromj['id'], $fromj['id'] );

    	// update jobtitles (could create dupes, but hey)
    	db_do( "UPDATE journo_jobtitle SET journo_id=? WHERE journo_id=? AND org_id in ({$orglist})", $toj['id'], $fromj['id'] );

    	// TODO: other data to move??? links? email?
    }

	db_commit();

	// Clear the htmlcache for the to and from journos
	cache_clear( 'j'.$fromj['id'] );
	cache_clear( 'j'.$toj['id'] );


	print( "<li>Journo split!<br />\n" );

	printf( "from: <a href=\"/%s\">%s (id %d)</a>\n", $fromj['ref'],$fromj['ref'], $fromj['id'] );
    printf( "[<a href=\"/adm/journo?journo_id=%d\">admin</a>]<br />\n", $fromj['id'] );

	printf( "to: <a href=\"/%s\">%s (id %d)</a>\n", $toj['ref'],$toj['ref'], $toj['id'] );
    printf( "[<a href=\"/adm/journo?journo_id=%d\">admin</a>]<br />\n", $toj['id'] );
    print( "</li>" );
    print( "</ul>\n</div>\n" );
}

view();

