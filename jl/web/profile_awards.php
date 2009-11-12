<?php

require_once '../conf/general';
require_once '../phplib/page.php';
require_once '../phplib/journo.php';
require_once '../phplib/editprofilepage.php';
require_once '../../phplib/db.php';
require_once '../../phplib/utility.php';



class AwardsPage extends EditProfilePage
{

    function __construct() {
        $this->pageName = "awards";
        $this->pagePath = "/profile_awards";
        $this->pageTitle = "Awards";
        $this->pageParams = array( 'head_extra_fn'=>array( &$this, 'extra_head' ) );
        parent::__construct();
    }


    function extra_head()
    {
        // TODO: use compressed jquery.autocompete

?>
<link type="text/css" rel="stylesheet" href="/profile.css" /> 
<script type="text/javascript" src="/js/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<? /*
<script type="text/javascript" src="/js/jquery.autocomplete.js"></script>
<link type="text/css" rel="stylesheet" href="/css/jquery.autocomplete.css" />
*/
?>
<script type="text/javascript" src="/js/jquery.form.js"></script>
<script type="text/javascript" src="/js/jl-fancyforms.js"></script>
<script type="text/javascript">
    $(document).ready( function() {
        fancyForms( '.award' );
    });
</script>
<?php
    }




    function handleActions()
    {
        // submitting new entries?
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $added = $this->handleSubmit();
        }

        if( get_http_var('remove_id') ) {
            $this->handleRemove();
        }
        return TRUE;
    }


    function displayMain()
    {
?><h2>Have you won any awards?</h2><?php
        $awards = db_getAll( "SELECT * FROM journo_awards WHERE journo_id=? ORDER BY YEAR DESC", $this->journo['id'] );
        foreach( $awards as $a ) {
            $this->showForm( "edit", $a );
        }
        if( !$awards )
            $this->showForm( "creator", null );
        $this->showForm( "template", null );

    }

    function ajax()
    {
        header( "Cache-Control: no-cache" );
        $action = get_http_var( "action" );
        if( $action == "submit" ) {
            $entry_id = $this->handleSubmit();
            $result = array( 'status'=>'success',
                'id'=>$entry_id,
                'editlinks_html'=>$this->genEditLinks($entry_id),
            );
            print json_encode( $result );
        }
    }

    function showForm( $formtype, $award )
    {
        static $uniq=0;
        ++$uniq;
        if( is_null( $award ) )
            $award = array( 'award'=>'', 'year'=>'' );
        $formclasses = 'award';
        if( $formtype == 'template' )
            $formclasses .= " template";
        if( $formtype == 'creator' )
            $formclasses .= " creator";

?>
<form class="<?= $formclasses; ?>" method="POST" action="<?= $this->pagePath; ?>">
<table border="0">
 <tr>
  <th><label for="award_<?= $uniq; ?>">Award:</label></th>
  <td><input type="text" size="60" name="award" id="award_<?= $uniq; ?>" value="<?= h($award['award']); ?>" /></td>
 </tr>
 <tr>
  <th><label for="year_<?= $uniq; ?>">Year:</label></th>
  <td><input type="text" size="4" name="year" id="year_<?= $uniq; ?>" value="<?= h($award['year']); ?>" /></td>
 </tr>
</table>
<input type="hidden" name="ref" value="<?=$this->journo['ref'];?>" />
<input type="hidden" name="action" value="submit" />
<button class="submit" type="submit">Save</button>
<?php if( $formtype=='edit' ) { ?>
<input type="hidden" name="id" value="<?= $award['id']; ?>" />
<?= $this->genEditLinks($award['id']); ?>
<?php } ?>
</form>

<?php

    }


    function handleSubmit()
    {
        $fieldnames = array( 'award', 'year' );
        $item = $this->genericFetchItemFromHTTPVars( $fieldnames );
        if( !$item['year'] )
            $item['year'] = null;

        $this->genericStoreItem( "journo_awards", $fieldnames, $item );
        return $item['id'];
    }

    function handleRemove() {
        $id = get_http_var("remove_id");

        // include journo id, to stop people zapping other journos entries!
        db_do( "DELETE FROM journo_awards WHERE id=? AND journo_id=?", $id, $this->journo['id'] );
        db_commit();
    }
}



$page = new AwardsPage();
$page->run();


